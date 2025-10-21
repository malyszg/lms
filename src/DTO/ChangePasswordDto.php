<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Change Password DTO
 * Used for changing password of authenticated user
 */
class ChangePasswordDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Obecne hasło jest wymagane')]
        public readonly string $currentPassword,
        
        #[Assert\NotBlank(message: 'Nowe hasło jest wymagane')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Hasło musi mieć minimum {{ limit }} znaków'
        )]
        public readonly string $newPassword,
        
        #[Assert\NotBlank(message: 'Potwierdzenie hasła jest wymagane')]
        #[Assert\EqualTo(
            propertyPath: 'newPassword',
            message: 'Hasła nie są identyczne'
        )]
        public readonly string $newPasswordConfirm
    ) {
    }
}

