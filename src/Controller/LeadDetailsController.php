<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UpdateLeadRequest;
use App\Exception\LeadNotFoundException;
use App\Exception\ValidationException;
use App\Leads\LeadServiceInterface;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lead Details Controller
 * Handles lead details slider display and status updates
 */
class LeadDetailsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LeadServiceInterface $leadService
    ) {}
    
    #[Route('/leads/{id}/details', name: 'lead_details', methods: ['GET'])]
    public function details(int $id): Response
    {
        // Find lead by ID with related entities
        $lead = $this->entityManager->getRepository(Lead::class)
            ->createQueryBuilder('l')
            ->leftJoin('l.customer', 'c')
            ->leftJoin('l.property', 'p')
            ->where('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$lead) {
            return $this->render('components/_error_message.html.twig', [
                'message' => 'Lead nie został znaleziony.'
            ], new Response('', Response::HTTP_NOT_FOUND));
        }
        
        // Map status to color
        $statusColors = [
            'new' => 'info',
            'contacted' => 'primary',
            'qualified' => 'success',
            'converted' => 'success',
            'rejected' => 'secondary',
        ];
        
        // Map status to label
        $statusLabels = [
            'new' => 'Nowy',
            'contacted' => 'Skontaktowany',
            'qualified' => 'Zakwalifikowany',
            'converted' => 'Skonwertowany',
            'rejected' => 'Odrzucony',
        ];
        
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();
        
        // Build lead data array for template
        $leadData = [
            'id' => $lead->getId(),
            'leadUuid' => $lead->getLeadUuid(),
            'status' => $lead->getStatus(),
            'statusLabel' => $statusLabels[$lead->getStatus()] ?? $lead->getStatus(),
            'statusColor' => $statusColors[$lead->getStatus()] ?? 'secondary',
            'createdAt' => $lead->getCreatedAt(),
            'applicationName' => $lead->getApplicationName(),
            'cdpDeliveryStatus' => 'pending', // TODO: Get from FailedDelivery or Event log
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
            ],
            'property' => $property ? [
                'propertyId' => $property->getPropertyId(),
                'developmentId' => $property->getDevelopmentId(),
                'partnerId' => $property->getPartnerId(),
                'propertyType' => $property->getPropertyType(),
                'price' => $property->getPrice(),
                'location' => $property->getLocation(),
                'city' => $property->getCity(),
            ] : null,
        ];
        
        return $this->render('leads/_details_slider.html.twig', [
            'lead' => $leadData
        ]);
    }
    
    #[Route('/leads/{id}/update-preferences', name: 'lead_update_preferences', methods: ['PUT'])]
    public function updatePreferences(int $id): Response
    {
        // TODO: Implement preferences update
        return $this->json([
            'success' => true,
            'message' => 'Preferencje zostały zapisane (funkcja w przygotowaniu)'
        ]);
    }
    
    #[Route('/leads/{id}/update-status', name: 'lead_update_status', methods: ['PUT'])]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function updateStatus(int $id, Request $request): Response
    {
        try {
            // Find lead by ID
            $lead = $this->entityManager->getRepository(Lead::class)->find($id);
            if (!$lead) {
                return $this->json([
                    'success' => false,
                    'message' => 'Lead nie został znaleziony'
                ], Response::HTTP_NOT_FOUND);
            }

            // Get request data - support both JSON and form data
            $data = [];
            
            // Try to get JSON data first
            $contentType = $request->headers->get('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $data = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Nieprawidłowy format JSON'
                    ], Response::HTTP_BAD_REQUEST);
                }
            } else {
                // Fallback to form data
                $data = $request->request->all();
            }

            // Validate status field
            if (!isset($data['status'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Pole status jest wymagane'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create UpdateLeadRequest DTO
            $updateRequest = new UpdateLeadRequest(status: (string) $data['status']);

            // Update lead status using LeadService
            $updatedLead = $this->leadService->updateLeadStatus(
                $lead->getLeadUuid(),
                $updateRequest,
                $request->getClientIp(),
                $request->headers->get('User-Agent')
            );

            // Map status to label for response
            $statusLabels = [
                'new' => 'Nowy',
                'contacted' => 'Skontaktowany',
                'qualified' => 'Zakwalifikowany',
                'converted' => 'Skonwertowany',
                'rejected' => 'Odrzucony',
            ];

            // Map status to CSS class
            $statusBadges = [
                'new' => 'fluent-badge-info',
                'contacted' => 'fluent-badge-primary',
                'qualified' => 'fluent-badge-success',
                'converted' => 'fluent-badge-success',
                'rejected' => 'fluent-badge-neutral',
            ];

            // Check if request is from HTMX
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                // Return HTML for HTMX
                $statusLabel = $statusLabels[$updatedLead->getStatus()] ?? $updatedLead->getStatus();
                $statusClass = $statusBadges[$updatedLead->getStatus()] ?? 'fluent-badge-neutral';
                
                return new Response(
                    sprintf(
                        '<span id="status-badge" class="fluent-badge %s">%s</span>',
                        $statusClass,
                        htmlspecialchars($statusLabel)
                    )
                );
            }

            // Return JSON for regular API calls
            return $this->json([
                'success' => true,
                'message' => 'Status leada został pomyślnie zaktualizowany',
                'data' => [
                    'id' => $updatedLead->getId(),
                    'lead_uuid' => $updatedLead->getLeadUuid(),
                    'status' => $updatedLead->getStatus(),
                    'status_label' => $statusLabels[$updatedLead->getStatus()] ?? $updatedLead->getStatus(),
                    'updated_at' => $updatedLead->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (ValidationException $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                return new Response(
                    '<span id="status-badge" class="fluent-badge fluent-badge-error">Błąd walidacji</span>',
                    Response::HTTP_BAD_REQUEST
                );
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Błąd walidacji',
                'errors' => $e->getErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (LeadNotFoundException $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                return new Response(
                    '<span id="status-badge" class="fluent-badge fluent-badge-error">Lead nie znaleziony</span>',
                    Response::HTTP_NOT_FOUND
                );
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Lead nie został znaleziony'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                return new Response(
                    '<span id="status-badge" class="fluent-badge fluent-badge-error">Błąd serwera</span>',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas aktualizacji statusu leada'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
}

