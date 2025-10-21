<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Leads\EventServiceInterface;
use App\Model\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logout Success Listener
 * Handles operations after logout
 */
#[AsEventListener(event: LogoutEvent::class)]
class LogoutSuccessListener
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if (!$token) {
            return;
        }
        
        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        
        // Log logout
        $this->eventService->logLogout(
            userId: $user->getId(),
            username: $user->getUserIdentifier(),
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent')
        );
    }
}

