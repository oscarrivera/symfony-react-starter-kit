<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;

final class WorkOrderAuthorizationTest extends ApiTestCase
{
    public function testTechnicianCannotReadAnotherUsersWorkOrder(): void
    {
        $users = new UserRepository($this->kernel->pdo());
        $users->insert('other@example.test', (new PasswordHasher())->hash('OtherPass_now-1'), 'tech');

        $ownerLogin = $this->request('POST', '/api/login', [
            'email' => 'tech@example.test',
            'password' => 'ChangeMe_now-1',
        ]);
        self::assertSame(200, $ownerLogin->getStatusCode());
        $ownerToken = (string) $this->json($ownerLogin)['token'];

        $created = $this->request('POST', '/api/work-orders', [
            'title' => 'Acometida calle 12',
            'description' => 'Sustituir caja de derivación',
        ], ['Authorization' => 'Bearer ' . $ownerToken]);
        self::assertSame(201, $created->getStatusCode());
        $orderId = (int) $this->json($created)['id'];

        $otherLogin = $this->request('POST', '/api/login', [
            'email' => 'other@example.test',
            'password' => 'OtherPass_now-1',
        ]);
        $otherToken = (string) $this->json($otherLogin)['token'];

        $forbidden = $this->request(
            'GET',
            '/api/work-orders/' . $orderId,
            headers: ['Authorization' => 'Bearer ' . $otherToken],
        );
        self::assertSame(404, $forbidden->getStatusCode());
        self::assertSame('not_found', $this->json($forbidden)['error']);

        $list = $this->request('GET', '/api/work-orders', headers: ['Authorization' => 'Bearer ' . $otherToken]);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame([], $this->json($list)['items']);

        $ownList = $this->request('GET', '/api/work-orders', headers: ['Authorization' => 'Bearer ' . $ownerToken]);
        self::assertCount(1, $this->json($ownList)['items']);
    }

    public function testOwnerCanPatchStatus(): void
    {
        $login = $this->request('POST', '/api/login', [
            'email' => 'tech@example.test',
            'password' => 'ChangeMe_now-1',
        ]);
        $token = (string) $this->json($login)['token'];
        $headers = ['Authorization' => 'Bearer ' . $token];

        $created = $this->request('POST', '/api/work-orders', [
            'title' => 'Revisión de cuadro',
        ], $headers);
        $id = (int) $this->json($created)['id'];

        $patched = $this->request('PATCH', '/api/work-orders/' . $id, [
            'status' => 'in_progress',
        ], $headers);
        self::assertSame(200, $patched->getStatusCode());
        self::assertSame('in_progress', $this->json($patched)['status']);
    }
}
