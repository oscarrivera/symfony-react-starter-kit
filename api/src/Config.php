<?php

declare(strict_types=1);

namespace App;

final readonly class Config
{
    /**
     * @param list<string> $corsOrigins
     */
    public function __construct(
        public string $appEnv,
        public string $appSecret,
        public string $databasePath,
        public array $corsOrigins,
        public int $jwtTtl,
        public bool $cookieSecure,
        public int $refreshTtl,
    ) {
    }

    public static function fromEnv(string $apiRoot): self
    {
        $secret = self::env('APP_SECRET') ?? '';
        if (strlen($secret) < 32) {
            throw new \RuntimeException('APP_SECRET must be at least 32 characters.');
        }

        $db = self::env('DATABASE_PATH') ?? 'var/data.sqlite';
        if ($db !== ':memory:' && !str_starts_with($db, '/')) {
            $db = $apiRoot . '/' . $db;
        }

        $originsRaw = self::env('CORS_ORIGINS') ?? 'http://localhost:5173';
        $origins = array_values(array_filter(array_map('trim', explode(',', $originsRaw)), static fn (string $o): bool => $o !== ''));

        $ttl = (int) (self::env('JWT_TTL') ?? '900');
        $refreshTtl = (int) (self::env('REFRESH_TTL') ?? '604800');

        $env = self::env('APP_ENV') ?? 'dev';
        $secureEnv = self::env('COOKIE_SECURE');
        if ($secureEnv === null) {
            $cookieSecure = $env === 'prod';
        } else {
            $cookieSecure = filter_var($secureEnv, FILTER_VALIDATE_BOOL);
        }

        return new self(
            appEnv: $env,
            appSecret: $secret,
            databasePath: $db,
            corsOrigins: $origins,
            jwtTtl: $ttl > 0 ? $ttl : 900,
            cookieSecure: $cookieSecure,
            refreshTtl: $refreshTtl > 0 ? $refreshTtl : 604800,
        );
    }

    private static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
