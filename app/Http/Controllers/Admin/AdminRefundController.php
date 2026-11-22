<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Tenant;
use App\Services\ImageService;
use App\Services\OrderRefundService;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminRefundController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly OrderRefundService $refundService,
        private readonly ImageService $imageService,
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('refunds.view')) {
            abort(403, 'Unauthorized');
        }

        $filters = $request->only(['status', 'search']);

        $query = OrderRefund::query()
            ->with([
                'order:id,invoice_number,first_name,last_name,phone,total_amount,paid_amount,payment_status',
            ])
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('refund_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($order) use ($search) {
                            $order->where('invoice_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();

        $resolved = $this->resolvePerPage($request);

        $refunds = $resolved['should_paginate']
            ? $query->paginate($resolved['per_page'])->withQueryString()
            : $this->createLengthAwarePaginator($query->get(), $query->count(), $request);

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $refunds,
            'filters' => $filters,
            'totals' => [
                'pending' => (float) $this->baseQuery()->where('status', OrderRefund::STATUS_PENDING)->sum('amount'),
                'completed' => (float) $this->baseQuery()->where('status', OrderRefund::STATUS_COMPLETED)->sum('amount'),
                'failed' => (float) $this->baseQuery()->where('status', OrderRefund::STATUS_FAILED)->sum('amount'),
            ],
            'permissions' => [
                'manage' => auth()->user()->can('refunds.manage'),
                'process' => auth()->user()->can('refunds.process'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('refunds.manage')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'order_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:30',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'evidence' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $order = Order::query()
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->findOrFail($validated['order_id']);

        $data = [
            'method' => $validated['method'] ?? 'manual',
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'actor_role' => 'merchant',
        ];

        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $this->imageService->upload($request->file('evidence'), 'refund-evidence');
        }

        try {
            $this->refundService->create($order, (float) $validated['amount'], $data, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund record created.');
    }

    public function process(Request $request, OrderRefund $refund)
    {
        if (!auth()->user()->can('refunds.process')) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->refundService->markProcessing($refund, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund marked as processing.');
    }

    public function complete(Request $request, OrderRefund $refund)
    {
        if (!auth()->user()->can('refunds.process')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'evidence' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $data = [
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $this->imageService->upload($request->file('evidence'), 'refund-evidence');
        }

        try {
            $this->refundService->complete($refund, $request->user(), $data);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund completed and the order payment status updated.');
    }

    public function fail(Request $request, OrderRefund $refund)
    {
        if (!auth()->user()->can('refunds.process')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $this->refundService->fail($refund, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund marked as failed.');
    }

    private function baseQuery()
    {
        return OrderRefund::query()
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
