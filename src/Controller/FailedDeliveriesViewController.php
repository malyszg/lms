<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Failed Deliveries View Controller (Stub)
 * TODO: Implement full functionality
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_USER')]
class FailedDeliveriesViewController extends AbstractController
{
    #[Route('/failed-deliveries', name: 'failed_deliveries_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('failed_deliveries/index.html.twig', [
            'message' => 'Widok nieudanych dostaw będzie wkrótce dostępny'
        ]);
    }
    
    #[Route('/failed-deliveries/count', name: 'failed_deliveries_count', methods: ['GET'])]
    public function count(): Response
    {
        // TODO: Implement actual counting
        return new Response('<span class="badge bg-danger ms-2">0</span>');
    }
}

