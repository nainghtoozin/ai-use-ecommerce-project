<?php

namespace App\Services;

use App\Models\AfterSalesHistory;
use Illuminate\Database\Eloquent\Model;

class AfterSalesHistoryService
{
    public function record(
        Model $subject,
        ?Model $actor,
        ?string $actorRole,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        bool $customerVisible = true
    ): AfterSalesHistory {
        return AfterSalesHistory::withoutTenantScope()->create([
            'tenant_id' => $subject->tenant_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actorRole,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'is_customer_visible' => $customerVisible,
        ]);
    }

    public function timeline(Model $subject, bool $customerOnly = false)
    {
        $query = AfterSalesHistory::withoutTenantScope()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->orderBy('created_at');

        if ($customerOnly) {
            $query->where('is_customer_visible', true);
        }

        return $query->get();
    }
}
