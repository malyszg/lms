<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Help View Controller
 * TODO: Re-enable authentication when auth system is implemented
 */
// #[IsGranted('ROLE_USER')]
class HelpViewController extends AbstractController
{
    #[Route('/help', name: 'help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }
}

