<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
// use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lead Details Controller
 * Handles lead details slider display
 * 
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_USER')]
class LeadDetailsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
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
    public function updateStatus(int $id): Response
    {
        // TODO: Implement status update
        return $this->json([
            'success' => true,
            'message' => 'Status został zaktualizowany (funkcja w przygotowaniu)'
        ]);
    }
    
}

