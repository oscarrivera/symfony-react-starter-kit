<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;
use PDO;

final class RefreshTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
    ) {
    }

    public function issue(int $userId, int $ttlSeconds): string
    {
        $raw = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('+' . $ttlSeconds . ' seconds', new \DateTimeZone('UTC')))->format('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'hash' => hash('sha256', $raw),
            'expires_at' => $expires,
            'created_at' => gmdate('c'),
        ]);

        return $raw;
    }

    public function consume(string $raw): ?User
    {
        $hash = hash('sha256', $raw);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, expires_at FROM refresh_tokens WHERE token_hash = :hash'
            );
            $stmt->execute(['hash' => $hash]);
            $row = $stmt->fetch();
            if ($row === false) {
                $this->pdo->rollBack();

                return null;
            }

            $delete = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE id = :id');
            $delete->execute(['id' => $row['id']]);

            $expires = new \DateTimeImmutable((string) $row['expires_at']);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            if ($expires < $now) {
                $this->pdo->commit();

                return null;
            }

            $user = $this->users->findById((int) $row['user_id']);
            $this->pdo->commit();

            return $user;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteByRaw(string $raw): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE token_hash = :hash');
        $stmt->execute(['hash' => hash('sha256', $raw)]);
    }
}
