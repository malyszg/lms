<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\EventFiltersDto;
use App\Service\EventViewServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Events View Controller
 * Handles events list display and filtering for administrators
 */
class EventsViewController extends AbstractController
{
    public function __construct(
        private readonly EventViewServiceInterface $eventViewService,
        private readonly LoggerInterface $logger
    ) {}
    
    #[Route('/events', name: 'events_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        try {
            // Parse filters from query params
            $filters = EventFiltersDto::fromRequest($request);
            
            // Get pagination params
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(200, max(1, (int) $request->query->get('limit', 50)));
            
            // Call service to get events
            $response = $this->eventViewService->getEventsList($filters, $page, $limit);
            
            // Check if this is HTMX request (partial update)
            if ($request->headers->get('HX-Request')) {
                // Return only the table partial
                return $this->render('events/_table.html.twig', [
                    'events' => $response->data,
                    'pagination' => $response->pagination,
                ]);
            }
            
            // Check if page is out of range (only for full page render)
            if ($page > $response->pagination->lastPage && $response->pagination->lastPage > 0) {
                return $this->redirectToRoute('events_index', array_merge(
                    $request->query->all(),
                    ['page' => $response->pagination->lastPage]
                ));
            }
            
            // Full page render
            return $this->render('events/index.html.twig', [
                'events' => $response->data,
                'pagination' => $response->pagination,
                'filters' => $filters->toArray(),
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->headers->get('HX-Request')) {
                return $this->render('components/_error_message.html.twig', [
                    'message' => 'Wystąpił błąd podczas pobierania eventów. Spróbuj ponownie.'
                ]);
            }
            
            throw $e;
        }
    }
}

