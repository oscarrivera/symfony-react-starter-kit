<?php

declare(strict_types=1);

namespace App\Http;

final class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $status,
        string $error,
        public readonly array $payload = [],
    ) {
        parent::__construct($error, $status);
    }
}
