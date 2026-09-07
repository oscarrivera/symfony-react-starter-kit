<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;
use PDO;

final class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxAttempts = 5,
        private readonly int $windowSeconds = 900,
    ) {
    }

    public function assertAllowed(string $ip): void
    {
        $this->purge($ip);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at >= :since'
        );
        $stmt->execute([
            'ip' => $ip,
            'since' => $this->since(),
        ]);
        $count = (int) $stmt->fetchColumn();
        if ($count >= $this->maxAttempts) {
            throw new HttpException(429, 'rate_limited');
        }
    }

    public function record(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip, attempted_at) VALUES (:ip, :at)'
        );
        $stmt->execute([
            'ip' => $ip,
            'at' => gmdate('c'),
        ]);
    }

    private function purge(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM login_attempts WHERE ip = :ip AND attempted_at < :since'
        );
        $stmt->execute([
            'ip' => $ip,
            'since' => $this->since(),
        ]);
    }

    private function since(): string
    {
        return (new \DateTimeImmutable('-' . $this->windowSeconds . ' seconds', new \DateTimeZone('UTC')))->format('c');
    }
}
