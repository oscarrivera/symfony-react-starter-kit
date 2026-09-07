<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\AuthenticatedUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 900,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 characters.');
        }
    }

    /**
     * @return array{0: string, 1: string} token and expiresAt (ISO-8601 UTC)
     */
    public function issue(AuthenticatedUser $user, ?int $ttl = null): array
    {
        $ttl ??= $this->ttl;
        $now = time();
        $exp = $now + $ttl;
        $payload = [
            'sub' => (string) $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $exp,
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');
        $expiresAt = (new \DateTimeImmutable('@' . $exp))->setTimezone(new \DateTimeZone('UTC'))->format('c');

        return [$token, $expiresAt];
    }

    public function parse(string $token): AuthenticatedUser
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        if (!isset($decoded->sub, $decoded->email, $decoded->role)) {
            throw new \UnexpectedValueException('Incomplete JWT payload.');
        }

        return new AuthenticatedUser((int) $decoded->sub, (string) $decoded->email, (string) $decoded->role);
    }
}
