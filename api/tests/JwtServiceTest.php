<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\AuthenticatedUser;
use App\Security\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    public function testIssueAndParseRoundTrip(): void
    {
        $jwt = new JwtService(str_repeat('s', 32), 900);
        $user = new AuthenticatedUser(7, 'tech@example.test', 'tech');
        [$token, $expiresAt] = $jwt->issue($user);

        self::assertNotSame('', $token);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $expiresAt);

        $parsed = $jwt->parse($token);
        self::assertSame(7, $parsed->id);
        self::assertSame('tech@example.test', $parsed->email);
        self::assertSame('tech', $parsed->role);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $jwt = new JwtService(str_repeat('s', 32), 900);
        $user = new AuthenticatedUser(1, 'tech@example.test', 'tech');
        [$token] = $jwt->issue($user, -30);

        $this->expectException(ExpiredException::class);
        $jwt->parse($token);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $jwt = new JwtService(str_repeat('s', 32), 900);
        $other = new JwtService(str_repeat('x', 32), 900);
        $user = new AuthenticatedUser(1, 'tech@example.test', 'tech');
        [$token] = $jwt->issue($user);

        $this->expectException(SignatureInvalidException::class);
        $other->parse($token);
    }
}
