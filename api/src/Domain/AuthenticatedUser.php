<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $role,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id, $user->email, $user->role);
    }
}
