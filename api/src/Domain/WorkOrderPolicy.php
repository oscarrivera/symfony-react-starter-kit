<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Ownership: a technician only sees their assigned orders. Admin bypasses the filter.
 * Deliberately returns the same boolean for "not found" and "not yours" at the HTTP layer
 * so the API does not leak the existence of another technician's work order.
 */
final class WorkOrderPolicy
{
    public function canAccess(AuthenticatedUser $user, WorkOrder $order): bool
    {
        return $user->isAdmin() || strcasecmp($user->email, $order->assignedEmail) === 0;
    }
}
