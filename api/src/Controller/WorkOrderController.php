<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\AuthenticatedUser;
use App\Domain\WorkOrder;
use App\Domain\WorkOrderPolicy;
use App\Http\HttpException;
use App\Http\Json;
use App\Repository\WorkOrderRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WorkOrderController
{
    private readonly ValidatorInterface $validator;

    public function __construct(
        private readonly WorkOrderRepository $orders,
        private readonly WorkOrderPolicy $policy,
    ) {
        $this->validator = Validation::createValidator();
    }

    public function list(AuthenticatedUser $user): Response
    {
        $items = $user->isAdmin()
            ? $this->orders->all()
            : $this->orders->forEmail($user->email);

        return Json::ok([
            'items' => array_map(static fn (WorkOrder $o): array => $o->toArray(), $items),
        ]);
    }

    public function create(Request $request, AuthenticatedUser $user): Response
    {
        $data = Json::decode($request);
        $this->assertValid($data, requiredTitle: true);

        $title = trim((string) $data['title']);
        $description = isset($data['description']) ? (string) $data['description'] : '';
        $status = isset($data['status']) ? (string) $data['status'] : 'open';
        $assigned = $user->email;
        if ($user->isAdmin() && isset($data['assignedEmail']) && is_string($data['assignedEmail']) && $data['assignedEmail'] !== '') {
            $assigned = strtolower($data['assignedEmail']);
        }

        $order = $this->orders->create($title, $description, $status, $assigned);

        return Json::ok($order->toArray(), 201);
    }

    public function get(string $id, AuthenticatedUser $user): Response
    {
        $order = $this->loadAuthorized((int) $id, $user);

        return Json::ok($order->toArray());
    }

    public function patch(Request $request, string $id, AuthenticatedUser $user): Response
    {
        $order = $this->loadAuthorized((int) $id, $user);
        $data = Json::decode($request);
        if ($data === []) {
            throw new HttpException(400, 'validation_failed');
        }
        $this->assertValid($data, requiredTitle: false);

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $order->title;
        $description = array_key_exists('description', $data) ? (string) $data['description'] : $order->description;
        $status = array_key_exists('status', $data) ? (string) $data['status'] : $order->status;

        $updated = $this->orders->update($order->id, $title, $description, $status);

        return Json::ok($updated->toArray());
    }

    private function loadAuthorized(int $id, AuthenticatedUser $user): WorkOrder
    {
        $order = $this->orders->find($id);
        if ($order === null || !$this->policy->canAccess($user, $order)) {
            throw new HttpException(404, 'not_found');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertValid(array $data, bool $requiredTitle): void
    {
        $fields = [
            'title' => $requiredTitle
                ? [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(min: 3, max: 120)]
                : new Assert\Optional([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(min: 3, max: 120)]),
            'description' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 2000)]),
            'status' => new Assert\Optional([new Assert\Choice(choices: WorkOrder::STATUSES)]),
            'assignedEmail' => new Assert\Optional([new Assert\Type('string'), new Assert\Email()]),
        ];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'fields' => $fields,
            'allowExtraFields' => false,
        ]));
        if (count($violations) === 0) {
            return;
        }

        $details = [];
        foreach ($violations as $violation) {
            $details[] = [
                'field' => trim($violation->getPropertyPath(), '[]'),
                'message' => $violation->getMessage(),
            ];
        }

        throw new HttpException(400, 'validation_failed', ['fields' => $details]);
    }
}
