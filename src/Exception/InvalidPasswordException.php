<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Invalid Password Exception
 * Thrown when current password is incorrect during password change
 */
class InvalidPasswordException extends \Exception
{
    public function __construct(string $message = 'Obecne hasło jest nieprawidłowe')
    {
        parent::__construct($message);
    }
}

