<?php

declare(strict_types=1);

namespace App\Tests;

use App\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashUsesArgon2idAndVerifies(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('ChangeMe_now-1');
        $info = password_get_info($hash);

        self::assertSame('argon2id', $info['algoName']);
        self::assertTrue($hasher->verify('ChangeMe_now-1', $hash));
        self::assertFalse($hasher->verify('wrong-password', $hash));
    }
}
