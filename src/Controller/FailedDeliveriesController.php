<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiClient\CDPDeliveryServiceInterface;
use App\DTO\ApiResponseDto;
use App\DTO\ErrorResponseDto;
use App\DTO\FailedDeliveryDto;
use App\DTO\FailedDeliveriesListResponse;
use App\DTO\PaginationDto;
use App\DTO\RetryDeliveryResponse;
use App\Leads\FailedDeliveryServiceInterface;
use App\Model\FailedDelivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Failed Deliveries API Controller
 * Handles failed CDP delivery operations for administrators
 */
#[Route('/api/failed-deliveries')]
#[IsGranted('ROLE_ADMIN')]
class FailedDeliveriesController extends AbstractController
{
    public function __construct(
        private readonly FailedDeliveryServiceInterface $failedDeliveryService,
        private readonly CDPDeliveryServiceInterface $cdpDeliveryService,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Get list of failed deliveries
     * GET /api/failed-deliveries
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('', name: 'api_failed_deliveries_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int)($request->query->get('page', 1)));
        $limit = min(100, max(1, (int)($request->query->get('limit', 20))));
        $status = $request->query->get('status');
        $cdpSystemName = $request->query->get('cdp_system_name');

        // Build query
        $repository = $this->entityManager->getRepository(FailedDelivery::class);
        $qb = $repository->createQueryBuilder('fd');

        // Apply filters
        if ($status) {
            $qb->andWhere('fd.status = :status')->setParameter('status', $status);
        }
        if ($cdpSystemName) {
            $qb->andWhere('fd.cdpSystemName = :cdpSystemName')
               ->setParameter('cdpSystemName', $cdpSystemName);
        }

        // Get total count
        $total = (int)$qb->select('COUNT(fd.id)')->getQuery()->getSingleScalarResult();

        // Pagination
        $offset = ($page - 1) * $limit;
        $failedDeliveries = $qb->select('fd')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('fd.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Convert to DTOs
        $data = array_map(
            fn(FailedDelivery $fd) => $this->convertToDto($fd),
            $failedDeliveries
        );

        // Build pagination
        $lastPage = (int)ceil($total / $limit);
        $from = $total > 0 ? (($page - 1) * $limit) + 1 : 0;
        $to = min($page * $limit, $total);
        $hasNext = $page < $lastPage;
        $hasPrevious = $page > 1;
        
        $pagination = new PaginationDto(
            currentPage: $page,
            perPage: $limit,
            total: $total,
            lastPage: $lastPage,
            from: $from,
            to: $to,
            hasNext: $hasNext,
            hasPrevious: $hasPrevious
        );

        $response = new FailedDeliveriesListResponse($data, $pagination);
        
        return $this->json($response, Response::HTTP_OK);
    }

    /**
     * Retry failed delivery
     * POST /api/failed-deliveries/{id}/retry
     *
     * @param int $id Failed delivery ID
     * @return JsonResponse
     */
    #[Route('/{id}/retry', name: 'api_failed_deliveries_retry', methods: ['POST'])]
    public function retry(int $id): JsonResponse
    {
        $failedDelivery = $this->failedDeliveryService->findById($id);

        if (!$failedDelivery) {
            return $this->json(
                new ErrorResponseDto(
                    error: 'not_found',
                    message: 'Failed delivery not found'
                ),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($failedDelivery->isResolved()) {
            return $this->json(
                new ErrorResponseDto(
                    error: 'already_resolved',
                    message: 'This delivery is already resolved'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$failedDelivery->canRetry()) {
            return $this->json(
                new ErrorResponseDto(
                    error: 'cannot_retry',
                    message: 'Cannot retry: retry limit exceeded or status invalid'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Attempt retry
            $this->cdpDeliveryService->retryFailedDelivery($failedDelivery);

            // Build response
            $response = new RetryDeliveryResponse(
                id: $failedDelivery->getId(),
                status: $failedDelivery->getStatus(),
                retryCount: $failedDelivery->getRetryCount(),
                nextRetryAt: $failedDelivery->getNextRetryAt(),
                message: 'Retry initiated'
            );

            return $this->json($response, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json(
                new ErrorResponseDto(
                    error: 'retry_failed',
                    message: $e->getMessage()
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Convert FailedDelivery entity to DTO
     *
     * @param FailedDelivery $fd
     * @return FailedDeliveryDto
     */
    private function convertToDto(FailedDelivery $fd): FailedDeliveryDto
    {
        $lead = $fd->getLead();
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();

        return new FailedDeliveryDto(
            id: $fd->getId(),
            leadUuid: $lead->getLeadUuid(),
            cdpSystemName: $fd->getCdpSystemName(),
            errorCode: $fd->getErrorCode(),
            errorMessage: $fd->getErrorMessage(),
            retryCount: $fd->getRetryCount(),
            maxRetries: $fd->getMaxRetries(),
            nextRetryAt: $fd->getNextRetryAt(),
            status: $fd->getStatus(),
            createdAt: $fd->getCreatedAt(),
            lead: new \App\DTO\LeadSummaryDto(
                id: $lead->getId(),
                leadUuid: $lead->getLeadUuid(),
                status: $lead->getStatus(),
                applicationName: $lead->getApplicationName(),
                createdAt: $lead->getCreatedAt()
            )
        );
    }
}

