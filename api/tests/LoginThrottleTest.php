<?php

declare(strict_types=1);

namespace App\Tests;

final class LoginThrottleTest extends ApiTestCase
{
    public function testSixthLoginFromSameIpIsRateLimited(): void
    {
        $body = ['email' => 'tech@example.test', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; ++$i) {
            $response = $this->request('POST', '/api/login', $body, ip: '198.51.100.20');
            self::assertSame(401, $response->getStatusCode(), 'attempt ' . ($i + 1));
            self::assertSame('invalid_credentials', $this->json($response)['error']);
        }

        $blocked = $this->request('POST', '/api/login', $body, ip: '198.51.100.20');
        self::assertSame(429, $blocked->getStatusCode());
        self::assertSame('rate_limited', $this->json($blocked)['error']);
    }

    public function testDifferentIpIsNotThrottledByOtherAddress(): void
    {
        $body = ['email' => 'tech@example.test', 'password' => 'wrong-password'];
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/login', $body, ip: '198.51.100.21');
        }

        $other = $this->request('POST', '/api/login', $body, ip: '198.51.100.22');
        self::assertSame(401, $other->getStatusCode());
    }
}
