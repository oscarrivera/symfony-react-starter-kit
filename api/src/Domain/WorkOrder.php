<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class WorkOrder
{
    public const STATUSES = ['open', 'in_progress', 'done'];

    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public string $status,
        public string $assignedEmail,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'assignedEmail' => $this->assignedEmail,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
