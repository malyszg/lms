<?php

declare(strict_types=1);

namespace App\Controller;

use App\Leads\EventServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Authentication Controller
 * Handles user login, logout, and authentication-related operations
 */
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly EventServiceInterface $eventService
    ) {
    }

    /**
     * Display login form and handle authentication
     * Security component intercepts POST requests
     */
    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('leads_index');
        }

        // Get authentication error (if any)
        $error = $this->authenticationUtils->getLastAuthenticationError();
        
        // Get last username entered
        $lastUsername = $this->authenticationUtils->getLastUsername();

        // Log failed login attempt
        if ($error && $request->isMethod('POST')) {
            $this->eventService->logLoginAttempt(
                username: $lastUsername,
                success: false,
                ipAddress: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent'),
                failureReason: $error->getMessageKey()
            );
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
    
    /**
     * Logout handler
     * This method will be intercepted by security component
     */
    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(): never
    {
        // This method can be blank - it will be intercepted by the logout key on security firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}










