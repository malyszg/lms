<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Events View Controller (Stub)
 * TODO: Implement full functionality
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_ADMIN')]
class EventsViewController extends AbstractController
{
    #[Route('/events', name: 'events_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('events/index.html.twig', [
            'message' => 'Widok eventów będzie wkrótce dostępny'
        ]);
    }
}

