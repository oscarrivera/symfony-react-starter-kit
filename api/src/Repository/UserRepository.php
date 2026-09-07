<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function insert(string $email, string $passwordHash, string $role): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, :role)'
        );
        $stmt->execute([
            'email' => strtolower($email),
            'hash' => $passwordHash,
            'role' => $role,
        ]);

        return new User((int) $this->pdo->lastInsertId(), strtolower($email), $passwordHash, $role);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, role FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => strtolower($email)]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, role FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return new User((int) $row['id'], (string) $row['email'], (string) $row['password_hash'], (string) $row['role']);
    }
}
