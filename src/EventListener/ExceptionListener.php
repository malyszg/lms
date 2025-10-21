<?php

declare(strict_types=1);

namespace App\EventListener;

use App\DTO\ErrorResponseDto;
use App\Exception\LeadAlreadyExistsException;
use App\Exception\ValidationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Global exception handler for API
 * Converts exceptions to JSON responses with appropriate HTTP status codes
 * Logs errors to EventService for monitoring and debugging
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener
{
    public function __construct(
        private readonly \App\Leads\EventServiceInterface $eventService
    ) {}

    /**
     * Handle exception and convert to JSON response
     *
     * @param ExceptionEvent $event
     * @return void
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle JSON API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $this->createJsonResponse($exception, $request);
        $event->setResponse($response);
    }

    /**
     * Create JSON response based on exception type
     * Also logs the error to EventService for monitoring
     *
     * @param \Throwable $exception
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return JsonResponse
     */
    private function createJsonResponse(\Throwable $exception, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $statusCode = $this->getStatusCodeFromException($exception);
        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        // Handle ValidationException (400 Bad Request)
        if ($exception instanceof ValidationException) {
            $errorResponse = new ErrorResponseDto(
                error: 'Validation Error',
                message: 'Invalid request data',
                details: $exception->getErrors()
            );

            // Log validation error
            $this->logApiError($request, $statusCode, 'Validation Error', $exception->getErrors(), $ipAddress, $userAgent);

            return new JsonResponse($errorResponse, Response::HTTP_BAD_REQUEST);
        }

        // Handle LeadAlreadyExistsException (409 Conflict)
        if ($exception instanceof LeadAlreadyExistsException) {
            $errorResponse = new ErrorResponseDto(
                error: 'Conflict',
                message: $exception->getMessage(),
                details: ['existing_lead_id' => $exception->getExistingLeadId()]
            );

            // Log conflict
            $this->logApiError($request, $statusCode, $exception->getMessage(), ['existing_lead_id' => $exception->getExistingLeadId()], $ipAddress, $userAgent);

            return new JsonResponse($errorResponse, Response::HTTP_CONFLICT);
        }

        // Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $errorResponse = new ErrorResponseDto(
                error: Response::$statusTexts[$exception->getStatusCode()] ?? 'Error',
                message: $exception->getMessage(),
                details: null
            );

            // Log HTTP exception
            $this->logApiError($request, $statusCode, $exception->getMessage(), null, $ipAddress, $userAgent);

            return new JsonResponse($errorResponse, $exception->getStatusCode());
        }

        // Handle all other exceptions as 500 Internal Server Error
        // In production, don't expose internal error details
        $isDebug = $_ENV['APP_DEBUG'] ?? false;

        $errorResponse = new ErrorResponseDto(
            error: 'Internal Server Error',
            message: $isDebug ? $exception->getMessage() : 'An unexpected error occurred',
            details: $isDebug ? [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null
        );

        // Log internal server error with full details
        $this->logApiError($request, $statusCode, $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ], $ipAddress, $userAgent);

        return new JsonResponse($errorResponse, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Get HTTP status code from exception
     *
     * @param \Throwable $exception
     * @return int
     */
    private function getStatusCodeFromException(\Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            return Response::HTTP_BAD_REQUEST;
        }

        if ($exception instanceof LeadAlreadyExistsException) {
            return Response::HTTP_CONFLICT;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Log API error to EventService
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $statusCode
     * @param string $errorMessage
     * @param array|null $details
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return void
     */
    private function logApiError(
        \Symfony\Component\HttpFoundation\Request $request,
        int $statusCode,
        string $errorMessage,
        ?array $details,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        try {
            $this->eventService->logApiRequest(
                endpoint: $request->getPathInfo(),
                method: $request->getMethod(),
                statusCode: $statusCode,
                details: array_merge([
                    'error' => $errorMessage,
                ], $details ?? []),
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $errorMessage
            );
        } catch (\Exception $e) {
            // Silently fail if logging fails - don't break the error response
        }
    }
}


