<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderCancellation;
use App\Models\OrderReturn;
use App\Models\Tenant;
use App\Services\AfterSalesHistoryService;
use App\Services\OrderReturnService;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminReturnController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly OrderReturnService $returnService,
        private readonly AfterSalesHistoryService $historyService,
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('returns.view')) {
            abort(403, 'Unauthorized');
        }

        $filters = $request->only(['status', 'search']);

        $query = OrderReturn::query()
            ->with([
                'order:id,invoice_number,first_name,last_name,phone,total_amount,paid_amount,payment_status,order_status,delivered_at',
                'items',
                'refund:id,refund_number,amount,status',
            ])
            ->when($this->tenantId(), fn ($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('rma_number', 'like', "%{$search}%")
                        ->orWhere('reason_label', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($order) use ($search) {
                            $order->where('invoice_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();

        $resolved = $this->resolvePerPage($request);

        $returns = $resolved['should_paginate']
            ? $query->paginate($resolved['per_page'])->withQueryString()
            : $this->createLengthAwarePaginator($query->get(), $query->count(), $request);

        return Inertia::render('Admin/Returns/Index', [
            'returns' => $returns,
            'filters' => $filters,
            'counts' => [
                'requested' => $this->baseQuery()->where('status', OrderReturn::STATUS_REQUESTED)->count(),
                'awaiting_shipment' => $this->baseQuery()->where('status', OrderReturn::STATUS_AWAITING_SHIPMENT)->count(),
                'received' => $this->baseQuery()->whereIn('status', [OrderReturn::STATUS_RECEIVED, OrderReturn::STATUS_INSPECTION])->count(),
                'completed' => $this->baseQuery()->where('status', OrderReturn::STATUS_COMPLETED)->count(),
            ],
        ]);
    }

    public function show(OrderReturn $return)
    {
        if (!auth()->user()->can('returns.view')) {
            abort(403, 'Unauthorized');
        }

        $return->load([
            'order.items.product',
            'order.items.variant',
            'items.orderItem.product',
            'items.orderItem.variant',
            'refund',
            'replacementOrder',
        ]);

        return Inertia::render('Admin/Returns/Show', [
            'returnRequest' => $return->append('evidence_urls'),
            'tracking' => $return->trackingPayload(),
            'timeline' => $this->historyService->timeline($return),
            'permissions' => [
                'approve' => auth()->user()->can('returns.approve'),
                'reject' => auth()->user()->can('returns.reject'),
                'receive' => auth()->user()->can('returns.receive'),
                'inspect' => auth()->user()->can('returns.inspect'),
                'in_transit' => auth()->user()->can('returns.receive'),
                'update_tracking' => auth()->user()->can('returns.receive'),
            ],
        ]);
    }

    public function approve(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.approve')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate(['note' => 'nullable|string|max:1000']);

        try {
            $this->returnService->approve($return, $request->user(), $validated['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return approved.');
    }

    public function reject(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.reject')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->returnService->reject($return, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return rejected.');
    }

    public function markInTransit(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.receive')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'carrier_name' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => ['nullable', 'string', 'max:500', $this->trackingUrlRule()],
            'tracking_note' => 'nullable|string|max:500',
            'shipped_at' => 'nullable|date',
        ]);

        try {
            $this->returnService->markInTransit(
                $return,
                $request->user(),
                OrderCancellation::ROLE_MERCHANT,
                $validated
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return marked as in transit.');
    }

    public function updateTracking(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.receive')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'carrier_name' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => ['nullable', 'string', 'max:500', $this->trackingUrlRule()],
            'tracking_note' => 'nullable|string|max:500',
            'shipped_at' => 'nullable|date',
        ]);

        try {
            $this->returnService->updateTracking($return, $request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tracking details updated.');
    }

    private function trackingUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            if (OrderReturn::sanitizeTrackingUrl((string) $value) === null) {
                $fail('The tracking URL must be a valid http or https link.');
            }
        };
    }

    public function receive(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.receive')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.received_quantity' => 'nullable|integer|min:0',
        ]);

        try {
            $this->returnService->receive($return, $request->user(), $validated['items'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return marked as received.');
    }

    public function inspect(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.inspect')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|min:1',
            'items.*.condition' => 'required|in:resellable,damaged',
            'items.*.restock' => 'boolean',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        $items = [];
        foreach ($validated['items'] as $value) {
            $items[(int) $value['id']] = $value;
        }

        try {
            $this->returnService->inspect($return, $request->user(), $items);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Inspection saved. Resellable items were restocked.');
    }

    public function complete(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.approve') && !auth()->user()->can('returns.inspect')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'method' => 'nullable|string|max:30',
            'merchant_note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->returnService->complete($return, $request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return completed.');
    }

    public function cancel(Request $request, OrderReturn $return)
    {
        if (!auth()->user()->can('returns.approve')) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->returnService->cancel($return, $request->user(), 'merchant');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return cancelled.');
    }

    private function baseQuery()
    {
        return OrderReturn::query()
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
