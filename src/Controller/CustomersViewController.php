<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CustomerFiltersDto;
use App\DTO\UpdatePreferencesRequest;
use App\Exception\CustomerNotFoundException;
use App\Exception\ValidationException;
use App\Model\User;
use App\Service\CustomerViewServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Customers View Controller
 * Handles customer management interface
 */
#[IsGranted('ROLE_USER')]
class CustomersViewController extends AbstractController
{
    public function __construct(
        private readonly CustomerViewServiceInterface $customerViewService,
        private readonly SerializerInterface $serializer
    ) {}

    #[Route('/customers', name: 'customers_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = CustomerFiltersDto::fromRequest($request);
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);

        $customersList = $this->customerViewService->getCustomersList($filters, $page, $limit);
        $stats = $this->customerViewService->getCustomerStats();

        return $this->render('customers/index.html.twig', [
            'customers' => $customersList->customers,
            'pagination' => $customersList->pagination,
            'filters' => $filters,
            'stats' => $stats,
            'limit' => $limit
        ]);
    }

    #[Route('/customers/stats', name: 'customers_stats', methods: ['GET'])]
    public function stats(): Response
    {
        try {
            $stats = $this->customerViewService->getCustomerStats();
            
            return $this->render('customers/_stats.html.twig', [
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return new Response('Błąd podczas ładowania statystyk', 500);
        }
    }

    #[Route('/customers/table', name: 'customers_table', methods: ['GET'])]
    public function table(Request $request): Response
    {
        try {
            $filters = CustomerFiltersDto::fromRequest($request);
            $page = (int) $request->query->get('page', 1);
            $limit = (int) $request->query->get('limit', 20);

            $customersList = $this->customerViewService->getCustomersList($filters, $page, $limit);

            return $this->render('customers/_table.html.twig', [
                'customers' => $customersList->customers,
                'pagination' => $customersList->pagination,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log('Customer table error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new Response('Błąd podczas ładowania tabeli: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/customers/{id}/preferences', name: 'customers_update_preferences', methods: ['PUT', 'POST'])]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function updatePreferences(int $id, Request $request): Response
    {
        try {
            // Handle both JSON and form data
            $data = [];
            if ($request->headers->get('Content-Type') === 'application/json') {
                $data = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse(['error' => 'Nieprawidłowy format JSON'], 400);
                }
            } else {
                $data = $request->request->all();
            }

            $preferencesRequest = new UpdatePreferencesRequest(
                priceMin: isset($data['price_min']) && $data['price_min'] !== '' ? (float) $data['price_min'] : null,
                priceMax: isset($data['price_max']) && $data['price_max'] !== '' ? (float) $data['price_max'] : null,
                location: isset($data['location']) && $data['location'] !== '' ? (string) $data['location'] : null,
                city: isset($data['city']) && $data['city'] !== '' ? (string) $data['city'] : null
            );

            $user = $this->getUser();
            $userId = $user instanceof User ? $user->getId() : null;
            
            $updatedPreferences = $this->customerViewService->updateCustomerPreferences(
                $id,
                $preferencesRequest,
                $userId,
                $request->getClientIp(),
                $request->headers->get('User-Agent')
            );

            // Check if request is from HTMX
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                // Get customer details to render the form
                $customer = $this->customerViewService->getCustomerDetails($id);
                
                return $this->render('customers/_preferences_form.html.twig', [
                    'customerId' => $id,
                    'preferences' => [
                        'priceMin' => $customer->preferences->priceMin,
                        'priceMax' => $customer->preferences->priceMax,
                        'location' => $customer->preferences->location,
                        'city' => $customer->preferences->city
                    ],
                    'success' => true,
                    'message' => 'Preferencje zostały pomyślnie zapisane'
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'preferences' => $updatedPreferences,
                'message' => 'Preferencje zostały zaktualizowane'
            ]);
        } catch (ValidationException $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                // Get customer details to render the form with errors
                try {
                    $customer = $this->customerViewService->getCustomerDetails($id);
                    
                    return $this->render('customers/_preferences_form.html.twig', [
                        'customerId' => $id,
                        'preferences' => [
                            'priceMin' => $customer->preferences->priceMin,
                            'priceMax' => $customer->preferences->priceMax,
                            'location' => $customer->preferences->location,
                            'city' => $customer->preferences->city
                        ],
                        'errors' => $e->getErrors()
                    ], new Response('', Response::HTTP_BAD_REQUEST));
                } catch (\Exception $fetchError) {
                    return $this->render('customers/_preferences_form.html.twig', [
                        'customerId' => $id,
                        'preferences' => [
                            'priceMin' => null,
                            'priceMax' => null,
                            'location' => null,
                            'city' => null
                        ],
                        'success' => false,
                        'message' => 'Błąd podczas ładowania preferencji',
                        'errors' => $e->getErrors()
                    ], new Response('', Response::HTTP_BAD_REQUEST));
                }
            }
            
            return new JsonResponse([
                'error' => 'Błąd walidacji',
                'details' => $e->getErrors()
            ], 422);
        } catch (CustomerNotFoundException $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                return $this->render('customers/_preferences_form.html.twig', [
                    'customerId' => $id,
                    'preferences' => [
                        'priceMin' => null,
                        'priceMax' => null,
                        'location' => null,
                        'city' => null
                    ],
                    'success' => false,
                    'message' => 'Klient nie został znaleziony'
                ], new Response('', Response::HTTP_NOT_FOUND));
            }
            
            return new JsonResponse(['error' => 'Klient nie został znaleziony'], 404);
        } catch (\Exception $e) {
            $isHtmxRequest = $request->headers->get('HX-Request') === 'true';
            
            if ($isHtmxRequest) {
                return $this->render('customers/_preferences_form.html.twig', [
                    'customerId' => $id,
                    'preferences' => [
                        'priceMin' => null,
                        'priceMax' => null,
                        'location' => null,
                        'city' => null
                    ],
                    'success' => false,
                    'message' => 'Wystąpił błąd podczas zapisywania preferencji'
                ], new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));
            }
            
            return new JsonResponse(['error' => 'Wystąpił błąd podczas aktualizacji preferencji'], 500);
        }
    }

    #[Route('/customers/{id}/leads', name: 'customers_leads', methods: ['GET'])]
    public function leads(int $id): Response
    {
        try {
            $customer = $this->customerViewService->getCustomerDetails($id);
            
            return $this->render('customers/_leads_section.html.twig', [
                'leads' => $customer->leads,
                'totalLeads' => $customer->totalLeads,
                'newLeads' => $customer->newLeads,
                'contactedLeads' => $customer->contactedLeads,
                'qualifiedLeads' => $customer->qualifiedLeads,
                'convertedLeads' => $customer->convertedLeads
            ]);
        } catch (CustomerNotFoundException $e) {
            return new Response('Klient nie został znaleziony', 404);
        } catch (\Exception $e) {
            return new Response('Błąd podczas ładowania leadów', 500);
        }
    }

    #[Route('/customers/{id}', name: 'customers_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        try {
            $customer = $this->customerViewService->getCustomerDetails($id);
            
            return $this->render('customers/_details_slider.html.twig', [
                'customer' => $customer,
                'isEditable' => $this->isGranted('ROLE_CALL_CENTER')
            ]);
        } catch (CustomerNotFoundException $e) {
            return new JsonResponse(['error' => 'Klient nie został znaleziony'], 404);
        }
    }
}

