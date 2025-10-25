<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\FiltersDto;
use App\Service\LeadViewServiceInterface;
use App\Service\StatsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
// use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Leads View Controller
 * Handles lead list display and filtering for the dashboard
 * 
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_USER')]
class LeadsViewController extends AbstractController
{
    public function __construct(
        private readonly LeadViewServiceInterface $leadViewService,
        private readonly StatsServiceInterface $statsService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}
    
    /**
     * Main dashboard - leads list with filters
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/', name: 'leads_index', methods: ['GET'])]
    #[Route('/leads', name: 'leads_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            // Parse filters from query params
            $filters = FiltersDto::fromRequest($request);
            
            // Get pagination params
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            
            // Call service to get leads (with cached AI scores)
            $response = $this->leadViewService->getLeadsList($filters, $page, $limit);
            
            // AI scores are now loaded from database cache - no API calls needed!
            // To score unscored leads, run: php bin/console app:score-leads
            
            // Check if this is HTMX request (partial update)
            if ($request->headers->get('HX-Request')) {
                // Return only the table partial
                return $this->render('leads/_table.html.twig', [
                    'leads' => $response->data,
                ]);
            }
            
            // Check if page is out of range (only for full page render)
            if ($page > $response->pagination->lastPage && $response->pagination->lastPage > 0) {
                return $this->redirectToRoute('leads_index', array_merge(
                    $request->query->all(),
                    ['page' => $response->pagination->lastPage]
                ));
            }
            
            // Get stats (only for full page render)
            $stats = $this->statsService->getDashboardStats();
            
            // Get new leads count (since last check)
            $lastCheck = $request->query->get('last_check', (new \DateTime('-30 seconds'))->format('c'));
            $newLeadsCount = $this->leadViewService->countNewLeadsSince($lastCheck);
            
            // Full page render
            return $this->render('leads/index.html.twig', [
                'leads' => $response->data,
                'pagination' => $response->pagination,
                'filters' => $filters,
                'stats' => $stats,
                'newLeadsCount' => $newLeadsCount,
                'lastCheckTimestamp' => (new \DateTime())->format('c')
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch leads', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->headers->get('HX-Request')) {
                return $this->render('components/_error_message.html.twig', [
                    'message' => 'Wystąpił błąd podczas pobierania leadów. Spróbuj ponownie.'
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Get dashboard statistics (for HTMX polling)
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/leads/stats', name: 'leads_stats', methods: ['GET'])]
    public function stats(Request $request): Response
    {
        // This endpoint should only be accessed via HTMX
        // If accessed directly, redirect to main leads page
        if (!$request->headers->get('HX-Request')) {
            return $this->redirectToRoute('leads_index');
        }
        
        try {
            $stats = $this->statsService->getDashboardStats();
            
            return $this->render('leads/_stats.html.twig', [
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch stats', [
                'error' => $e->getMessage()
            ]);
            
            // Return empty stats on error
            return $this->render('leads/_stats.html.twig', [
                'stats' => new \App\DTO\StatsDto(0, 0, 0)
            ]);
        }
    }
    
    /**
     * Count new leads since timestamp (for HTMX polling)
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/leads/new-count', name: 'leads_new_count', methods: ['GET'])]
    public function newCount(Request $request): Response
    {
        // This endpoint should only be accessed via HTMX
        // If accessed directly, redirect to main leads page
        if (!$request->headers->get('HX-Request')) {
            return $this->redirectToRoute('leads_index');
        }
        
        try {
            $since = $request->query->get('since', (new \DateTime('-30 seconds'))->format('c'));
            $newLeadsCount = $this->leadViewService->countNewLeadsSince($since);
            
            return $this->render('leads/_new_leads_notification.html.twig', [
                'newLeadsCount' => $newLeadsCount,
                'lastCheckTimestamp' => (new \DateTime())->format('c')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to count new leads', [
                'error' => $e->getMessage()
            ]);
            
            // Return 0 on error
            return $this->render('leads/_new_leads_notification.html.twig', [
                'newLeadsCount' => 0,
                'lastCheckTimestamp' => (new \DateTime())->format('c')
            ]);
        }
    }
    
    /**
     * Load delete modal data for specific lead
     * GET /leads/{id}/delete-modal
     *
     * @param int $id Lead ID
     * @param Request $request
     * @return Response
     */
    #[Route('/leads/{id}/delete-modal', name: 'leads_delete_modal', methods: ['GET'])]
    public function deleteModal(int $id, Request $request): Response
    {
        // This endpoint should only be accessed via HTMX
        if (!$request->headers->get('HX-Request')) {
            return $this->redirectToRoute('leads_index');
        }
        
        try {
            // Find lead by ID
            $lead = $this->entityManager->getRepository(\App\Model\Lead::class)->find($id);
            
            if (!$lead) {
                return $this->render('components/_error_message.html.twig', [
                    'message' => 'Lead nie został znaleziony'
                ]);
            }
            
            // Convert Lead entity to LeadDto for template
            $customer = $lead->getCustomer();
            $property = $lead->getProperty();
            
            $leadDto = new \App\DTO\LeadDto(
                id: $lead->getId(),
                leadUuid: $lead->getLeadUuid(),
                status: $lead->getStatus(),
                createdAt: $lead->getCreatedAt(),
                customer: new \App\DTO\CustomerDto(
                    id: $customer->getId(),
                    email: $customer->getEmail(),
                    phone: $customer->getPhone(),
                    firstName: $customer->getFirstName(),
                    lastName: $customer->getLastName(),
                    createdAt: $customer->getCreatedAt()
                ),
                applicationName: $lead->getApplicationName(),
                property: $property ? new \App\DTO\PropertyDto(
                    propertyId: $property->getPropertyId(),
                    developmentId: $property->getDevelopmentId(),
                    partnerId: $property->getPartnerId(),
                    propertyType: $property->getPropertyType(),
                    price: $property->getPrice(),
                    location: $property->getLocation(),
                    city: $property->getCity()
                ) : new \App\DTO\PropertyDto(
                    propertyId: null,
                    developmentId: null,
                    partnerId: null,
                    propertyType: null,
                    price: null,
                    location: null,
                    city: null
                )
            );
            
            return $this->render('leads/_delete_modal.html.twig', [
                'lead' => $leadDto
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load delete modal', [
                'lead_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->render('components/_error_message.html.twig', [
                'message' => 'Wystąpił błąd podczas ładowania danych modala'
            ]);
        }
    }
}

