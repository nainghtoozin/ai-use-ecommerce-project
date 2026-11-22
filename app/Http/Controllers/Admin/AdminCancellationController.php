<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderCancellation;
use App\Models\Tenant;
use App\Services\OrderCancellationService;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCancellationController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly OrderCancellationService $cancellationService,
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('cancellations.view')) {
            abort(403, 'Unauthorized');
        }

        $filters = $request->only(['status', 'search']);

        $query = OrderCancellation::query()
            ->with([
                'order:id,invoice_number,first_name,last_name,phone,total_amount,paid_amount,payment_status,order_status,created_at',
                'refund:id,refund_number,amount,status',
            ])
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('cancellation_number', 'like', "%{$search}%")
                        ->orWhere('reason_label', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($order) use ($search) {
                            $order->where('invoice_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];

        $cancellations = $resolved['should_paginate']
            ? $query->paginate($perPage)->withQueryString()
            : $this->createLengthAwarePaginator($query->get(), $query->count(), $request);

        return Inertia::render('Admin/Cancellations/Index', [
            'cancellations' => $cancellations,
            'filters' => $filters,
            'counts' => [
                'requested' => $this->baseQuery()->where('status', OrderCancellation::STATUS_REQUESTED)->count(),
                'approved' => $this->baseQuery()->where('status', OrderCancellation::STATUS_APPROVED)->count(),
                'completed' => $this->baseQuery()->where('status', OrderCancellation::STATUS_COMPLETED)->count(),
                'rejected' => $this->baseQuery()->where('status', OrderCancellation::STATUS_REJECTED)->count(),
            ],
            'canApprove' => auth()->user()->can('cancellations.approve'),
            'canReject' => auth()->user()->can('cancellations.reject'),
        ]);
    }

    public function approve(Request $request, OrderCancellation $cancellation)
    {
        if (!auth()->user()->can('cancellations.approve')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->cancellationService->approve($cancellation, $request->user(), $validated['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cancellation approved and the order was cancelled.');
    }

    public function reject(Request $request, OrderCancellation $cancellation)
    {
        if (!auth()->user()->can('cancellations.reject')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $this->cancellationService->reject($cancellation, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cancellation request rejected.');
    }

    private function baseQuery()
    {
        return OrderCancellation::query()
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('tenant_id', $tenantId));
    }

    private function tenantId(): ?int
    {
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return null;
        }

        return Tenant::getCurrent()?->id ?? $user?->tenant_id;
    }
}
