<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class Json
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'invalid_json');
        }

        if (!is_array($data)) {
            throw new HttpException(400, 'invalid_json');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(string $code, int $status, array $details = []): JsonResponse
    {
        $body = ['error' => $code];
        if ($details !== []) {
            $body['details'] = $details;
        }

        return new JsonResponse($body, $status);
    }
}
