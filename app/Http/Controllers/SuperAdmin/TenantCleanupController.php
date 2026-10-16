<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TenantCleanup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantCleanupController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $query = TenantCleanup::query()
            ->with([
                'tenant:id,name,slug,status,locked_at',
                'subscription:id,plan_id,status,expires_at',
                'subscription.plan:id,name',
            ])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $cleanups = $query->paginate(25)
            ->withQueryString()
            ->through(fn (TenantCleanup $cleanup) => [
                'id' => $cleanup->id,
                'tenant' => $cleanup->tenant?->name,
                'tenant_slug' => $cleanup->tenant?->slug,
                'tenant_status' => $cleanup->tenant?->status,
                'plan' => $cleanup->subscription?->plan?->name,
                'status' => $cleanup->status,
                'expires_at' => $cleanup->expires_at?->toDateString(),
                'grace_ends_at' => $cleanup->grace_ends_at?->toDateString(),
                'days_remaining' => $cleanup->grace_ends_at
                    ? (int) ceil(max(0, $cleanup->grace_ends_at->getTimestamp() - now()->getTimestamp()) / 86400)
                    : null,
                'retention_ends_at' => $cleanup->retention_ends_at?->toDateString(),
                'retention_days_remaining' => $cleanup->retention_ends_at
                    ? (int) ceil(max(0, $cleanup->retention_ends_at->getTimestamp() - now()->getTimestamp()) / 86400)
                    : null,
                'deletion_status' => $cleanup->deletion_status,
                'deleted_at' => $cleanup->deleted_at?->toDateTimeString(),
                'restricted_at' => $cleanup->restricted_at?->toDateTimeString(),
                'reason' => $cleanup->reason,
                'attempts' => $cleanup->attempts,
                'last_error' => $cleanup->last_error,
            ]);

        return Inertia::render('SuperAdmin/TenantCleanup/Index', [
            'cleanups' => $cleanups,
            'filters' => ['status' => $status],
            'counts' => [
                'grace' => TenantCleanup::where('status', TenantCleanup::STATUS_GRACE)->count(),
                'restricted' => TenantCleanup::where('status', TenantCleanup::STATUS_RESTRICTED)->count(),
                'flagged' => TenantCleanup::where('status', TenantCleanup::STATUS_FLAGGED)->count(),
                'cancelled' => TenantCleanup::where('status', TenantCleanup::STATUS_CANCELLED)->count(),
                'pending_deletion' => TenantCleanup::where('status', TenantCleanup::STATUS_PENDING_DELETION)->count(),
                'deleted' => TenantCleanup::where('status', TenantCleanup::STATUS_DELETED)->count(),
            ],
            'purgeEnabled' => (bool) config('billing.tenant_cleanup.purge_enabled', false),
        ]);
    }
}
