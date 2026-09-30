<?php

namespace App\Http\Controllers\Admin;

use App\Auth\IdentityResolver;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use PerPageTrait;

    private const MAX_PER_PAGE = 500;

    public function __construct(
        private readonly IdentityResolver $identityResolver,
    ) {}

    private function isSuperAdmin(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    private function getTenantFilter(): mixed
    {
        if ($this->isSuperAdmin()) {
            return false;
        }
        if ($this->identityResolver->supportsAccount()) {
            return $this->identityResolver->getCurrentTenantId();
        }
        return auth()->user()->tenant_id;
    }

    private function baseCustomerQuery(mixed $tenantId)
    {
        $useAccounts = $this->identityResolver->supportsAccount();

        return $this->identityResolver->queryUsersForTenant($tenantId)
            ->when($useAccounts, fn($q) => $q->whereHas('memberships', fn($m) => $m
                ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                ->whereHas('role', fn($r) => $r->where('name', 'customer'))),
                fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'customer')));
    }

    private function findCustomerForTenant(int $id, mixed $tenantId)
    {
        $useAccounts = $this->identityResolver->supportsAccount();

        return $this->baseCustomerQuery($tenantId)
            ->where('id', $id)
            ->when($useAccounts, fn($q) => $q->with(['memberships' => fn($m) => $m
                ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                ->with('customerProfile')]))
            ->first();
    }

    private function customerPayload($customer, bool $useAccounts): array
    {
        $membership = $useAccounts ? $customer->memberships->first() : null;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $useAccounts
                ? $membership?->customerProfile?->phone
                : null,
            'status' => $membership?->status ?? $customer->status,
            'status_reason' => $membership?->status_reason ?? $customer->status_reason,
            'profile_image_url' => $customer->profile_image_url,
            'membership_id' => $membership?->id,
            'joined_at' => $membership?->joined_at ?? $customer->created_at,
            'created_at' => $customer->created_at,
        ];
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('customers.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->get('search');
        $status = $request->get('status');
        $tenantId = $this->getTenantFilter();
        $useAccounts = $this->identityResolver->supportsAccount();

        $customers = $this->baseCustomerQuery($tenantId)
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s, $useAccounts, $tenantId) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
                if ($useAccounts) {
                    $q->orWhereHas('memberships', fn($m) => $m
                        ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                        ->whereHas('customerProfile', fn($p) => $p->where('phone', 'like', "%{$s}%")));
                }
            }))
            ->when($status, fn($q, $s) => $useAccounts
                ? $q->whereHas('memberships', fn($m) => $m
                    ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                    ->where('status', $s))
                : $q->where('status', $s))
            ->orderBy('created_at', 'desc');

        if ($useAccounts) {
            $customers->with(['memberships' => fn($m) => $m
                ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                ->with('customerProfile')]);
        }

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];
        $warning = $resolved['warning'];

        if (! $resolved['should_paginate']) {
            $perPage = self::MAX_PER_PAGE;
            $warning = 'Showing up to 500 customers per page.';
        }

        $customers = $customers->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers->through(fn($user) => $this->customerPayload($user, $useAccounts)),
            'showPagination' => true,
            'warning' => $warning,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function show(int $id)
    {
        if (!auth()->user()->can('customers.view')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = $this->getTenantFilter();
        $useAccounts = $this->identityResolver->supportsAccount();

        $customer = $this->findCustomerForTenant($id, $tenantId);
        if (! $customer) {
            abort(404);
        }

        $ordersQuery = Order::withoutTenantScope()->forUser($customer)
            ->when($tenantId, fn($q, $tid) => $q->where('orders.tenant_id', $tid));

        $orderCount = (clone $ordersQuery)->count();
        $totalSpent = (float) (clone $ordersQuery)->sum('total_amount');
        $lastOrder = (clone $ordersQuery)->latest()->first();

        $orderHistory = (clone $ordersQuery)
            ->with(['items.product:id,name', 'items.variant:id,product_id,sku'])
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString()
            ->through(fn($o) => [
                'id' => $o->id,
                'order_status' => $o->order_status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float) $o->total_amount,
                'items_count' => $o->items->sum('quantity'),
                'items_summary' => $o->items->map(fn($i) => [
                    'product_name' => $i->product?->name ?? 'Item',
                    'variant_sku' => $i->variant?->sku,
                    'quantity' => $i->quantity,
                    'price' => (float) $i->price,
                ])->values(),
                'created_at' => $o->created_at,
            ]);

        $addresses = CustomerAddress::withoutTenantScope()
            ->where('user_id', $customer->id)
            ->where('user_type', $customer->getMorphClass())
            ->when($tenantId, fn($q, $tid) => $q->where('tenant_id', $tid))
            ->with(['city', 'township'])
            ->orderBy('is_default', 'desc')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'name' => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')),
                'phone' => $a->phone,
                'address_line' => $a->address_line,
                'city' => $a->city?->name,
                'township' => $a->township?->name,
                'postal_code' => $a->postal_code,
                'is_default' => (bool) $a->is_default,
            ]);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $this->customerPayload($customer, $useAccounts),
            'addresses' => $addresses,
            'orderStats' => [
                'count' => $orderCount,
                'total_spent' => $totalSpent,
            ],
            'lastOrder' => $lastOrder ? [
                'id' => $lastOrder->id,
                'order_status' => $lastOrder->order_status,
                'total_amount' => (float) $lastOrder->total_amount,
                'created_at' => $lastOrder->created_at,
            ] : null,
            'orderHistory' => $orderHistory,
        ]);
    }

    public function edit(int $id)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $customer = $this->findCustomerForTenant($id, $this->getTenantFilter());
        if (! $customer) {
            abort(404);
        }

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => $this->customerPayload($customer, $this->identityResolver->supportsAccount()),
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = $this->getTenantFilter();
        $useAccounts = $this->identityResolver->supportsAccount();

        $customer = $this->findCustomerForTenant($id, $tenantId);
        if (! $customer) {
            abort(404);
        }

        $emailTable = $useAccounts ? 'accounts' : 'users';

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', Rule::unique($emailTable, 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $changes = [];
        $updateData = [];
        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $customer->{$field}) {
                $updateData[$field] = $data[$field];
                $changes[] = $field;
            }
        }
        if (! empty($updateData)) {
            $customer->update($updateData);
        }

        if ($useAccounts && array_key_exists('phone', $data)) {
            $membership = $customer->memberships->first();
            if ($membership) {
                CustomerProfile::updateOrCreate(
                    ['tenant_membership_id' => $membership->id],
                    ['name' => $customer->name, 'phone' => $data['phone']],
                );
                $changes[] = 'phone';
            }
        }

        if (! empty($changes)) {
            $customer->logActivity('updated', 'Customer updated by admin', [
                'updated_by' => auth()->id(),
                'changes' => $changes,
            ]);
        }

        return admin_redirect('admin.customers.show', $customer->id)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(int $id)
    {
        if (!auth()->user()->can('users.delete')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = $this->getTenantFilter();
        $useAccounts = $this->identityResolver->supportsAccount();

        $customer = $this->findCustomerForTenant($id, $tenantId);
        if (! $customer) {
            abort(404);
        }

        if (auth()->id() === $customer->id) {
            return admin_redirect('admin.customers.index')
                ->with('error', 'You cannot delete your own account.');
        }

        if ($useAccounts) {
            $membership = $customer->memberships->first();
            if ($membership) {
                $membership->customerProfile?->delete();
                $membership->delete();
            }
            $customer->logActivity('deleted', 'Customer removed from store by admin', [
                'deleted_by' => auth()->id(),
                'tenant_id' => $tenantId,
            ]);

            return admin_redirect('admin.customers.index')
                ->with('success', 'Customer removed. Their order history is preserved.');
        }

        $hasOrders = Order::withoutTenantScope()->forUser($customer)
            ->when($tenantId, fn($q, $tid) => $q->where('orders.tenant_id', $tid))
            ->exists();

        if ($hasOrders) {
            return admin_redirect('admin.customers.index')
                ->with('error', 'Cannot delete this customer because order history must be preserved. Suspend instead.');
        }

        $customer->logActivity('deleted', 'Customer deleted by admin', [
            'deleted_by' => auth()->id(),
        ]);
        $customer->delete();

        return admin_redirect('admin.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function suspend(Request $request, int $id)
    {
        if (!auth()->user()->can('users.suspend')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $customer = $this->findCustomerForTenant($id, $this->getTenantFilter());
        if (! $customer) {
            abort(404);
        }

        if (auth()->id() === $customer->id) {
            return redirect()->back()->with('error', 'You cannot suspend your own account.');
        }

        if ($this->identityResolver->supportsAccount()) {
            $customer->memberships->first()?->update([
                'status' => 'suspended',
                'status_reason' => $data['reason'],
            ]);
        } else {
            $customer->update(['status' => User::STATUS_SUSPENDED, 'status_reason' => $data['reason']]);
        }
        $customer->logActivity('suspended', 'Customer suspended by admin', [
            'suspended_by' => auth()->id(),
            'reason' => $data['reason'],
        ]);

        return admin_redirect('admin.customers.index')
            ->with('success', 'Customer suspended successfully.');
    }

    public function ban(Request $request, int $id)
    {
        if (!auth()->user()->can('users.ban')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $customer = $this->findCustomerForTenant($id, $this->getTenantFilter());
        if (! $customer) {
            abort(404);
        }

        if (auth()->id() === $customer->id) {
            return redirect()->back()->with('error', 'You cannot ban your own account.');
        }

        if ($this->identityResolver->supportsAccount()) {
            $customer->memberships->first()?->update([
                'status' => 'banned',
                'status_reason' => $data['reason'],
            ]);
        } else {
            $customer->update(['status' => User::STATUS_BANNED, 'status_reason' => $data['reason']]);
        }
        $customer->logActivity('banned', 'Customer banned by admin', [
            'banned_by' => auth()->id(),
            'reason' => $data['reason'],
        ]);

        return admin_redirect('admin.customers.index')
            ->with('success', 'Customer banned successfully.');
    }

    public function activate(int $id)
    {
        if (!auth()->user()->can('users.activate')) {
            abort(403, 'Unauthorized');
        }

        $customer = $this->findCustomerForTenant($id, $this->getTenantFilter());
        if (! $customer) {
            abort(404);
        }

        if ($this->identityResolver->supportsAccount()) {
            $customer->memberships->first()?->update([
                'status' => 'active',
                'status_reason' => null,
            ]);
        } else {
            $customer->update(['status' => User::STATUS_ACTIVE, 'status_reason' => null]);
        }
        $customer->logActivity('activated', 'Customer reactivated by admin', [
            'activated_by' => auth()->id(),
        ]);

        return admin_redirect('admin.customers.index')
            ->with('success', 'Customer reactivated successfully.');
    }
}
