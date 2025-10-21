<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * System Configuration View Controller (Stub)
 * TODO: Implement full functionality
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_ADMIN')]
class ConfigViewController extends AbstractController
{
    #[Route('/config', name: 'config_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/index.html.twig', [
            'message' => 'Widok konfiguracji będzie wkrótce dostępny'
        ]);
    }
}

