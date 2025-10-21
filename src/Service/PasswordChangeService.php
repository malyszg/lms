<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidPasswordException;
use App\Leads\EventServiceInterface;
use App\Model\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Password Change Service
 * Implementation of password change operations
 */
class PasswordChangeService implements PasswordChangeServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventServiceInterface $eventService
    ) {
    }

    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $ipAddress = null
    ): void {
        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new InvalidPasswordException('Obecne hasło jest nieprawidłowe');
        }

        // Check if new password is different from current
        if ($this->passwordHasher->isPasswordValid($user, $newPassword)) {
            throw new InvalidPasswordException('Nowe hasło musi różnić się od obecnego');
        }

        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        // Log password change
        $this->eventService->logPasswordChange(
            userId: $user->getId(),
            username: $user->getEmail(),
            ipAddress: $ipAddress
        );
    }
}

