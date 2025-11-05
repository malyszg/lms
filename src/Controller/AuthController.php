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
     * Root path - redirect based on authentication status
     * Priority 10 to ensure it's matched before other routes
     */
    #[Route('/', name: 'root', methods: ['GET'], priority: 10)]
    public function root(): Response
    {
        // If logged in, go to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('leads_list');
        }
        
        // Otherwise, go to login
        return $this->redirectToRoute('auth_login');
    }
    
    /**
     * Display login form and handle authentication
     * Security component intercepts POST requests
     */
    #[Route('/login', name: 'auth_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('leads_list');
        }

        // Get authentication error (if any)
        $error = $this->authenticationUtils->getLastAuthenticationError();
        
        // Get last username entered
        $lastUsername = $this->authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
    
    /**
     * POST handler for login - Security will intercept this
     * This method should never be called if Security is working correctly
     */
    #[Route('/login', name: 'auth_login_check', methods: ['POST'])]
    public function loginCheck(): never
    {
        // This should never be called - Security should intercept POST requests
        throw new \LogicException('Security should intercept POST requests to /login before this method is called.');
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










