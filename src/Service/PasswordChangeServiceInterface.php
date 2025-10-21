<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use App\Exception\InvalidPasswordException;

/**
 * Password Change Service Interface
 * Handles password changes for authenticated users
 */
interface PasswordChangeServiceInterface
{
    /**
     * Change password for authenticated user
     * 
     * @throws InvalidPasswordException If current password is incorrect
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $ipAddress = null
    ): void;
}

