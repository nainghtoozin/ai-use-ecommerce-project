<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->when($search, fn($q, $s) => $q->whereHas('tenant', fn($t) => $t
                ->where('name', 'like', "%{$s}%")
                ->orWhere('slug', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"))
            )
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $totalActive = Subscription::inGoodStanding()->count();
        $totalPastDue = Subscription::where('status', 'past_due')->count();
        $totalExpired = Subscription::where('status', 'expired')->count();
        $expiringSoon = Subscription::expiringSoon()->count();

        $plans = Plan::active()->ordered()->get(['id', 'name', 'slug', 'monthly_price', 'yearly_price']);

        return Inertia::render('SuperAdmin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => ['search' => $search, 'status' => $status],
            'stats' => [
                'active' => $totalActive,
                'past_due' => $totalPastDue,
                'expired' => $totalExpired,
                'expiring_soon' => $expiringSoon,
            ],
            'plans' => $plans,
        ]);
    }

    public function show(Subscription $subscription)
    {
        $subscription->load(['tenant', 'plan']);

        $history = Subscription::where('tenant_id', $subscription->tenant_id)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        $usage = $this->getTenantUsage($subscription->tenant);

        $plans = Plan::active()->ordered()->get(['id', 'name', 'slug', 'monthly_price', 'yearly_price']);
        $intervals = ['monthly', 'yearly'];

        return Inertia::render('SuperAdmin/Subscriptions/Show', [
            'subscription' => $subscription,
            'history' => $history,
            'usage' => $usage,
            'plans' => $plans,
            'intervals' => $intervals,
            'currentInterval' => $subscription->billing_interval ?? 'monthly',
        ]);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id' => 'required|exists:plans,id',
            'billing_interval' => 'nullable|in:monthly,yearly',
            'status' => 'nullable|in:active,trialing',
            'trial_ends_at' => 'nullable|date|after:now',
            'expires_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        $existing = Subscription::where('tenant_id', $tenant->id)
            ->inGoodStanding()
            ->exists();

        if ($existing) {
            return redirect()->route('superadmin.subscriptions.index')
                ->with('error', "Tenant \"{$tenant->name}\" already has an active subscription. Change the plan instead.");
        }

        $plan = Plan::findOrFail($validated['plan_id']);
        $billingInterval = $validated['billing_interval'] ?? $plan->defaultInterval();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_interval' => $billingInterval,
            'status' => $validated['status'] ?? 'active',
            'starts_at' => now(),
            'expires_at' => $validated['expires_at'] ?? $plan->calculateExpiryDate(now(), $billingInterval),
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($tenant->status === 'suspended') {
            $tenant->update(['status' => 'active']);
        }

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', "Subscription assigned to \"{$tenant->name}\".");
    }

    public function changePlan(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_interval' => 'nullable|in:monthly,yearly',
            'reason' => 'nullable|string|max:500',
        ]);

        $newPlan = Plan::findOrFail($validated['plan_id']);
        $oldPlan = $subscription->plan;

        if ($subscription->plan_id === $newPlan->id) {
            return redirect()->route('superadmin.subscriptions.show', $subscription)
                ->with('error', 'Tenant is already on this plan.');
        }

        $warnings = $this->checkDowngradeWarnings($subscription->tenant, $newPlan);

        if ($newPlan->isFree() || ($newPlan->monthly_price && $oldPlan && $oldPlan->monthly_price && $newPlan->monthly_price < $oldPlan->monthly_price)) {
            if (!empty($warnings)) {
                session()->flash('downgrade_warnings', $warnings);
            }
        }

        $billingInterval = $validated['billing_interval'] ?? $newPlan->defaultInterval();

        $subscription->update([
            'plan_id' => $newPlan->id,
            'billing_interval' => $billingInterval,
            'status' => 'active',
            'expires_at' => $subscription->expires_at?->isFuture()
                ? $subscription->expires_at
                : $newPlan->calculateExpiryDate(now(), $billingInterval),
            'notes' => $subscription->notes
                ? $subscription->notes . "\n[" . now() . "] Plan changed: {$oldPlan?->name} → {$newPlan->name}. {$validated['reason']}"
                : "[" . now() . "] Plan changed: {$oldPlan?->name} → {$newPlan->name}. {$validated['reason']}",
        ]);

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', "Plan changed to \"{$newPlan->name}\"." . (!empty($warnings) ? ' Warning: some limits may be exceeded.' : ''));
    }

    public function renew(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'expires_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expiresAt = Carbon::parse($validated['expires_at']);

        $subscription->renew($expiresAt, $validated['notes'] ?? null);

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', 'Subscription renewed until ' . $expiresAt->toFormattedDateString() . '.');
    }

    public function renewFromInterval(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $subscription->renewFromInterval($validated['notes'] ?? null);

        $expiry = $subscription->fresh()->expires_at;

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', $expiry
                ? 'Subscription renewed via ' . $subscription->billing_interval . ' cycle until ' . $expiry->toFormattedDateString() . '.'
                : 'Subscription renewed.');
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $note = $validated['reason']
            ? "[" . now() . "] Canceled. Reason: {$validated['reason']}"
            : "[" . now() . "] Canceled by SuperAdmin.";

        // If no future expiry, end access at cancel time.
        // If there is a future expiry, the merchant keeps access until then.
        $expiresAt = $subscription->expires_at?->isFuture()
            ? $subscription->expires_at
            : now();

        $subscription->update([
            'status' => 'canceled',
            'cancelled_at' => now(),
            'expires_at' => $expiresAt,
            'notes' => $subscription->notes ? $subscription->notes . "\n" . $note : $note,
        ]);

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', $subscription->expires_at->isFuture()
                ? 'Subscription canceled. Merchant retains access until ' . $subscription->expires_at->toFormattedDateString() . '.'
                : 'Subscription canceled. Access ended immediately.');
    }

    public function suspend(Subscription $subscription)
    {
        if ($subscription->isExpired()) {
            return redirect()->route('superadmin.subscriptions.show', $subscription)
                ->with('error', 'Subscription is already expired.');
        }

        $subscription->markAsExpired();

        return redirect()->route('superadmin.subscriptions.show', $subscription)
            ->with('success', 'Subscription suspended. Tenant access revoked.');
    }

    private function getTenantUsage(Tenant $tenant): array
    {
        return [
            'products' => $tenant->products()->count(),
            'staff' => $tenant->users()
                ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                ->count(),
        ];
    }

    private function calculateExpiryForPlan(Plan $plan): ?Carbon
    {
        return $plan->calculateExpiryDate();
    }

    private function checkDowngradeWarnings(Tenant $tenant, Plan $newPlan): array
    {
        $warnings = [];

        if ($newPlan->product_limit !== null) {
            $productCount = $tenant->products()->count();
            if ($productCount > $newPlan->product_limit) {
                $warnings[] = "Product limit ({$newPlan->product_limit}) exceeded by current count ({$productCount}).";
            }
        }

        if ($newPlan->staff_limit !== null) {
            $staffCount = $tenant->users()
                ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                ->count();
            if ($staffCount > $newPlan->staff_limit) {
                $warnings[] = "Staff limit ({$newPlan->staff_limit}) exceeded by current count ({$staffCount}).";
            }
        }

        return $warnings;
    }
}
