<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateCustomerDto;
use App\DTO\CreateLeadRequest;
use App\DTO\CustomerDto;
use App\DTO\DeleteLeadResponse;
use App\DTO\FiltersDto;
use App\DTO\PropertyDto;
use App\DTO\UpdateLeadRequest;
use App\DTO\UpdateLeadResponse;
use App\Exception\LeadNotFoundException;
use App\Exception\ValidationException;
use App\Leads\EventServiceInterface;
use App\Leads\LeadRequestTransformerInterface;
use App\Leads\LeadServiceInterface;
use App\Leads\ValidationServiceInterface;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lead API Controller
 * Handles lead creation and management endpoints
 */
class LeadController extends AbstractController
{
    public function __construct(
        private readonly LeadServiceInterface $leadService,
        private readonly ValidationServiceInterface $validationService,
        private readonly EventServiceInterface $eventService,
        private readonly LeadRequestTransformerInterface $requestTransformer,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Create new lead
     * POST /api/leads
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/leads', name: 'api_leads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        // Validate Content-Type
        if ($request->headers->get('Content-Type') !== 'application/json') {
            throw new ValidationException([
                'content_type' => 'Content-Type must be application/json'
            ]);
        }

        // Decode JSON request
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException([
                'json' => 'Invalid JSON: ' . json_last_error_msg()
            ]);
        }

        // Extract application_name for transformation
        $applicationName = $data['application_name'] ?? '';

        // Transform request data based on application source
        // For Homsters: hms_* fields → standard fields
        $data = $this->requestTransformer->transformRequestData($data, $applicationName);

        // Create DTOs from request data
        $createLeadRequest = $this->createLeadRequestFromArray($data);

        // Validate request
        $validationErrors = $this->validationService->validateCreateLeadRequest($createLeadRequest);
        
        if (!empty($validationErrors)) {
            throw new ValidationException($validationErrors);
        }

        // Create lead (pass IP and user agent for logging)
        $response = $this->leadService->createLead(
            $createLeadRequest,
            $ipAddress,
            $userAgent
        );

        // Log successful API request
        $this->eventService->logApiRequest(
            endpoint: '/api/leads',
            method: 'POST',
            statusCode: 201,
            details: [
                'lead_uuid' => $response->leadUuid,
                'customer_id' => $response->customerId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        // Return 201 Created response with Location header
        return $this->json($response, Response::HTTP_CREATED, [
            'Location' => '/api/leads/' . $response->leadUuid
        ]);
    }

    /**
     * Get list of leads with filtering, sorting, and pagination
     * GET /api/leads
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/leads', name: 'api_leads_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Parse filters from request
            $filters = FiltersDto::fromRequest($request);
            
            // Get pagination params
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            
            // Build query
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l', 'c', 'lp')
                ->from(Lead::class, 'l')
                ->leftJoin('l.customer', 'c')
                ->leftJoin('l.property', 'lp');
            
            // Apply filters
            if ($filters->status !== null) {
                $qb->andWhere('l.status = :status')
                    ->setParameter('status', $filters->status);
            }
            
            if ($filters->applicationName !== null) {
                $qb->andWhere('l.applicationName = :applicationName')
                    ->setParameter('applicationName', $filters->applicationName);
            }
            
            if ($filters->customerEmail !== null) {
                $qb->andWhere('c.email LIKE :email')
                    ->setParameter('email', '%' . $filters->customerEmail . '%');
            }
            
            if ($filters->customerPhone !== null) {
                $qb->andWhere('c.phone LIKE :phone')
                    ->setParameter('phone', '%' . $filters->customerPhone . '%');
            }
            
            if ($filters->createdFrom !== null) {
                $qb->andWhere('l.createdAt >= :createdFrom')
                    ->setParameter('createdFrom', $filters->createdFrom);
            }
            
            if ($filters->createdTo !== null) {
                $qb->andWhere('l.createdAt <= :createdTo')
                    ->setParameter('createdTo', $filters->createdTo);
            }
            
            // Apply sorting
            $sortField = match($filters->sort) {
                'status' => 'l.status',
                'application_name' => 'l.applicationName',
                default => 'l.createdAt'
            };
            $qb->orderBy($sortField, strtoupper($filters->order));
            
            // Count total results (before pagination)
            $countQb = clone $qb;
            $countQb->select('COUNT(DISTINCT l.id)');
            $total = (int) $countQb->getQuery()->getSingleScalarResult();
            
            // Apply pagination
            $qb->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
            
            // Execute query
            $leads = $qb->getQuery()->getResult();
            
            // Transform to array
            $data = array_map(function (Lead $lead) {
                $customer = $lead->getCustomer();
                $property = $lead->getProperty();
                
                return [
                    'id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'status' => $lead->getStatus(),
                    'created_at' => $lead->getCreatedAt()->format('Y-m-d H:i:s'),
                    'application_name' => $lead->getApplicationName(),
                    'customer' => [
                        'id' => $customer->getId(),
                        'email' => $customer->getEmail(),
                        'phone' => $customer->getPhone(),
                        'first_name' => $customer->getFirstName(),
                        'last_name' => $customer->getLastName(),
                    ],
                    'property' => $property ? [
                        'property_id' => $property->getPropertyId(),
                        'development_id' => $property->getDevelopmentId(),
                        'partner_id' => $property->getPartnerId(),
                        'property_type' => $property->getPropertyType(),
                        'price' => $property->getPrice(),
                        'location' => $property->getLocation(),
                        'city' => $property->getCity(),
                    ] : null,
                ];
            }, $leads);
            
            // Build pagination metadata
            $lastPage = (int) ceil($total / $limit);
            $from = $total > 0 ? ($page - 1) * $limit + 1 : 0;
            $to = min($page * $limit, $total);
            
            $response = [
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $from,
                    'to' => $to,
                ]
            ];
            
            // Log API request
            $this->eventService->logApiRequest(
                endpoint: '/api/leads',
                method: 'GET',
                statusCode: 200,
                details: [
                    'total_results' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent')
            );
            
            return $this->json($response);
            
        } catch (\Exception $e) {
            // Log error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads',
                method: 'GET',
                statusCode: 500,
                details: [
                    'error' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent')
            );
            
            return $this->json([
                'error' => 'Internal Server Error',
                'message' => 'Failed to retrieve leads'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Get lead by UUID
     * GET /api/leads/{uuid}
     *
     * @param string $uuid
     * @return JsonResponse
     */
    #[Route('/api/leads/{uuid}', name: 'api_leads_show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Find lead by UUID
            $lead = $this->entityManager->getRepository(Lead::class)
                ->findOneBy(['leadUuid' => $uuid]);
            
            if (!$lead) {
                return $this->json([
                    'error' => 'Not Found',
                    'message' => 'Lead not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $customer = $lead->getCustomer();
            $property = $lead->getProperty();
            
            $response = [
                'id' => $lead->getId(),
                'lead_uuid' => $lead->getLeadUuid(),
                'status' => $lead->getStatus(),
                'created_at' => $lead->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $lead->getUpdatedAt()->format('Y-m-d H:i:s'),
                'application_name' => $lead->getApplicationName(),
                'customer' => [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'phone' => $customer->getPhone(),
                    'first_name' => $customer->getFirstName(),
                    'last_name' => $customer->getLastName(),
                    'created_at' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'property' => $property ? [
                    'id' => $property->getId(),
                    'property_id' => $property->getPropertyId(),
                    'development_id' => $property->getDevelopmentId(),
                    'partner_id' => $property->getPartnerId(),
                    'property_type' => $property->getPropertyType(),
                    'price' => $property->getPrice(),
                    'location' => $property->getLocation(),
                    'city' => $property->getCity(),
                ] : null,
            ];
            
            // Log API request
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'GET',
                statusCode: 200,
                details: [
                    'lead_id' => $lead->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: null,
                userAgent: null
            );
            
            return $this->json($response);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal Server Error',
                'message' => 'Failed to retrieve lead'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete lead by UUID
     * DELETE /api/leads/{uuid}
     *
     * @param string $uuid Lead UUID
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/leads/{uuid}', name: 'api_leads_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function delete(string $uuid, Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        try {
            // Validate UUID format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return $this->json([
                    'error' => 'Bad Request',
                    'message' => 'Invalid UUID format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Delete lead
            $this->leadService->deleteLead($uuid, $ipAddress, $userAgent);

            // Create response DTO
            $response = new DeleteLeadResponse(
                leadUuid: $uuid,
                deletedAt: new \DateTime(),
                message: 'Lead został pomyślnie usunięty'
            );

            // Log successful API request
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'DELETE',
                statusCode: 200,
                details: [
                    'lead_uuid' => $uuid,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $this->json($response);

        } catch (LeadNotFoundException $e) {
            // Log not found error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'DELETE',
                statusCode: 404,
                details: [
                    'lead_uuid' => $uuid,
                    'error' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $this->json([
                'error' => 'Not Found',
                'message' => 'Lead nie został znaleziony'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            // Log internal server error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'DELETE',
                statusCode: 500,
                details: [
                    'lead_uuid' => $uuid,
                    'error' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $e->getMessage()
            );

            return $this->json([
                'error' => 'Internal Server Error',
                'message' => 'Wystąpił błąd podczas usuwania leada'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update lead status by UUID
     * PUT /api/leads/{uuid}
     *
     * @param string $uuid Lead UUID
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/leads/{uuid}', name: 'api_leads_update', methods: ['PUT'])]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        try {
            // Validate UUID format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                return $this->json([
                    'error' => 'Bad Request',
                    'message' => 'Invalid UUID format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate Content-Type
            if ($request->headers->get('Content-Type') !== 'application/json') {
                throw new ValidationException([
                    'content_type' => 'Content-Type must be application/json'
                ]);
            }

            // Decode JSON request
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException([
                    'json' => 'Invalid JSON: ' . json_last_error_msg()
                ]);
            }

            // Create UpdateLeadRequest DTO
            if (!isset($data['status'])) {
                throw new ValidationException(['status' => 'Field is required']);
            }

            $updateRequest = new UpdateLeadRequest(status: (string) $data['status']);

            // Update lead status
            $lead = $this->leadService->updateLeadStatus($uuid, $updateRequest, $ipAddress, $userAgent);

            // Create response DTO
            $response = new UpdateLeadResponse(
                id: $lead->getId(),
                leadUuid: $lead->getLeadUuid(),
                status: $lead->getStatus(),
                statusLabel: \App\DTO\LeadItemDto::getStatusLabel($lead->getStatus()),
                updatedAt: $lead->getUpdatedAt(),
                message: 'Status leada został pomyślnie zaktualizowany'
            );

            // Log successful API request
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'PUT',
                statusCode: 200,
                details: [
                    'lead_uuid' => $uuid,
                    'new_status' => $lead->getStatus(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $this->json($response);

        } catch (LeadNotFoundException $e) {
            // Log not found error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'PUT',
                statusCode: 404,
                details: [
                    'lead_uuid' => $uuid,
                    'error' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $this->json([
                'error' => 'Not Found',
                'message' => 'Lead nie został znaleziony'
            ], Response::HTTP_NOT_FOUND);

        } catch (ValidationException $e) {
            // Log validation error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'PUT',
                statusCode: 400,
                details: [
                    'lead_uuid' => $uuid,
                    'validation_errors' => $e->getErrors(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $this->json([
                'error' => 'Bad Request',
                'message' => 'Validation failed',
                'errors' => $e->getErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            // Log internal server error
            $this->eventService->logApiRequest(
                endpoint: '/api/leads/' . $uuid,
                method: 'PUT',
                statusCode: 500,
                details: [
                    'lead_uuid' => $uuid,
                    'error' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $e->getMessage()
            );

            return $this->json([
                'error' => 'Internal Server Error',
                'message' => 'Wystąpił błąd podczas aktualizacji statusu leada'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create CreateLeadRequest DTO from array data
     *
     * @param array<string, mixed> $data
     * @return CreateLeadRequest
     * @throws ValidationException
     */
    private function createLeadRequestFromArray(array $data): CreateLeadRequest
    {
        // Validate required fields presence
        if (!isset($data['lead_uuid'])) {
            throw new ValidationException(['lead_uuid' => 'Field is required']);
        }

        if (!isset($data['application_name'])) {
            throw new ValidationException(['application_name' => 'Field is required']);
        }

        if (!isset($data['customer'])) {
            throw new ValidationException(['customer' => 'Field is required']);
        }

        if (!isset($data['customer']['email'])) {
            throw new ValidationException(['customer.email' => 'Field is required']);
        }

        if (!isset($data['customer']['phone'])) {
            throw new ValidationException(['customer.phone' => 'Field is required']);
        }

        // Create CustomerDto
        $customerDto = new CreateCustomerDto(
            email: (string) $data['customer']['email'],
            phone: (string) $data['customer']['phone'],
            firstName: isset($data['customer']['first_name']) ? (string) $data['customer']['first_name'] : null,
            lastName: isset($data['customer']['last_name']) ? (string) $data['customer']['last_name'] : null
        );

        // Create PropertyDto (all fields optional)
        $propertyData = $data['property'] ?? [];
        $propertyDto = new PropertyDto(
            propertyId: isset($propertyData['property_id']) ? (string) $propertyData['property_id'] : null,
            developmentId: isset($propertyData['development_id']) ? (string) $propertyData['development_id'] : null,
            partnerId: isset($propertyData['partner_id']) ? (string) $propertyData['partner_id'] : null,
            propertyType: isset($propertyData['property_type']) ? (string) $propertyData['property_type'] : null,
            price: isset($propertyData['price']) ? (float) $propertyData['price'] : null,
            location: isset($propertyData['location']) ? (string) $propertyData['location'] : null,
            city: isset($propertyData['city']) ? (string) $propertyData['city'] : null
        );

        // Create and return CreateLeadRequest
        return new CreateLeadRequest(
            leadUuid: (string) $data['lead_uuid'],
            applicationName: (string) $data['application_name'],
            customer: $customerDto,
            property: $propertyDto
        );
    }
}

