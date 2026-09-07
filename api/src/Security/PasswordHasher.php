<?php

declare(strict_types=1);

namespace App\Security;

final class PasswordHasher
{
    private readonly string $dummyHash;

    public function __construct()
    {
        // Constant-time path for unknown emails: still run Argon2id verify.
        $this->dummyHash = password_hash('timing-dummy', PASSWORD_ARGON2ID);
    }

    public function hash(string $plain): string
    {
        $hash = password_hash($plain, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new \RuntimeException('password_hash failed.');
        }

        return $hash;
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function verifyUnknown(string $plain): void
    {
        password_verify($plain, $this->dummyHash);
    }
}
