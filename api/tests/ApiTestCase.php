<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends TestCase
{
    protected Kernel $kernel;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbPath = tempnam(sys_get_temp_dir(), 'parte_') . '.sqlite';
        putenv('APP_SECRET=test-secret-must-be-32-chars-min');
        putenv('APP_ENV=test');
        putenv('DATABASE_PATH=' . $this->dbPath);
        putenv('CORS_ORIGINS=http://localhost:5173');
        putenv('COOKIE_SECURE=0');
        putenv('JWT_TTL=900');
        $_ENV['APP_SECRET'] = 'test-secret-must-be-32-chars-min';
        $_ENV['DATABASE_PATH'] = $this->dbPath;
        $_ENV['COOKIE_SECURE'] = '0';
        $this->kernel = Kernel::boot(dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    protected function request(
        string $method,
        string $path,
        ?array $json = null,
        array $headers = [],
        array $cookies = [],
        string $ip = '192.0.2.10',
    ): Response {
        $server = [
            'REMOTE_ADDR' => $ip,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $content = $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR);
        $request = Request::create($path, $method, server: $server, content: $content);
        foreach ($cookies as $name => $value) {
            $request->cookies->set($name, $value);
        }

        return $this->kernel->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }
}
