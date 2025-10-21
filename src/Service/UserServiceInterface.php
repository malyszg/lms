<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;

/**
 * User Service Interface
 * Handles user management operations
 */
interface UserServiceInterface
{
    /**
     * Create a new user
     */
    public function createUser(
        string $email,
        string $username,
        string $plainPassword,
        array $roles = ['ROLE_USER'],
        ?string $firstName = null,
        ?string $lastName = null
    ): User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User;

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(User $user): void;
}

