<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Leads\EventServiceInterface;
use App\Model\User;
use App\Service\UserServiceInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Login Success Listener
 * Handles operations after successful login
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly UserServiceInterface $userService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        
        // Update last login timestamp
        $this->userService->updateLastLogin($user);
        
        // Log successful login
        $this->eventService->logLoginAttempt(
            username: $user->getUserIdentifier(),
            success: true,
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent')
        );
    }
}

