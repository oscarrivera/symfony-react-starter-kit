<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
        public string $role,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
