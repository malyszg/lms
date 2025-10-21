<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * User Service
 * Implementation of user management operations
 */
class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository
    ) {
    }

    public function createUser(
        string $email,
        string $username,
        string $plainPassword,
        array $roles = ['ROLE_USER'],
        ?string $firstName = null,
        ?string $lastName = null
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setRoles($roles);
        
        if ($firstName) {
            $user->setFirstName($firstName);
        }
        
        if ($lastName) {
            $user->setLastName($lastName);
        }

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findOneByEmail($email);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->userRepository->findOneByUsername($username);
    }

    public function updateLastLogin(User $user): void
    {
        $user->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();
    }
}

