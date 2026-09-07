<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\WorkOrder;
use PDO;

final class WorkOrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<WorkOrder>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, title, description, status, assigned_email, created_at, updated_at
             FROM work_orders ORDER BY id DESC'
        );

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    /**
     * @return list<WorkOrder>
     */
    public function forEmail(string $email): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, description, status, assigned_email, created_at, updated_at
             FROM work_orders WHERE assigned_email = :email ORDER BY id DESC'
        );
        $stmt->execute(['email' => strtolower($email)]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function find(int $id): ?WorkOrder
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, description, status, assigned_email, created_at, updated_at
             FROM work_orders WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(string $title, string $description, string $status, string $assignedEmail): WorkOrder
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO work_orders (title, description, status, assigned_email, created_at, updated_at)
             VALUES (:title, :description, :status, :email, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'email' => strtolower($assignedEmail),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find((int) $this->pdo->lastInsertId())
            ?? throw new \RuntimeException('Failed to persist work order.');
    }

    public function update(int $id, string $title, string $description, string $status): WorkOrder
    {
        $stmt = $this->pdo->prepare(
            'UPDATE work_orders
             SET title = :title, description = :description, status = :status, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'updated_at' => gmdate('c'),
            'id' => $id,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Work order disappeared after update.');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): WorkOrder
    {
        return new WorkOrder(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['status'],
            (string) $row['assigned_email'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
