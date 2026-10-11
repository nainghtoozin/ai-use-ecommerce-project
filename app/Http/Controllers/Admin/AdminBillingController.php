<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Data\Currency;
use App\Enums\CurrencyCode;
use App\Models\BillingPaymentMethod;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionCancellationRequest;
use App\Models\Tenant;
use App\Services\FeatureGate;
use App\Services\ImageService;
use App\Services\Payment\Platform\CheckoutService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use App\Services\Payment\Platform\PaymentIntentService;
use App\Services\SubscriptionAuditService;
use App\Services\SubscriptionLimitService;
use App\Services\SubscriptionPlanChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminBillingController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription()->with('pendingPlan')->first();
        $currentPlan = $subscription?->plan;

        $usage = SubscriptionLimitService::for($tenant)->getAllLimits();

        $allPlans = Plan::active()->ordered()->get();
        $allFeatureDefs = FeatureGate::getAllFeatureDefinitions();
        $featureKeys = array_column($allFeatureDefs, 'key');

        $plans = $allPlans->map(function ($plan) use ($featureKeys, $currentPlan) {
            $enabledFeatures = $plan->getEnabledFeatures();
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'is_current' => $currentPlan && $plan->id === $currentPlan->id,
                'yearly_savings_percent' => $plan->yearlySavingsPercent(),
                'limits' => [
                    'product_limit' => $plan->productLimit(),
                    'staff_limit' => $plan->staffLimit(),
                    'storage_limit' => $plan->storageLimitMb(),
                    'orders_monthly_limit' => $plan->limitValue('orders_monthly_limit'),
                    'coupon_limit' => $plan->limitValue('coupon_limit'),
                    'promotion_limit' => $plan->limitValue('promotion_limit'),
                    'flash_sale_limit' => $plan->limitValue('flash_sale_limit'),
                    'branch_limit' => $plan->limitValue('branch_limit'),
                    'warehouse_limit' => $plan->limitValue('warehouse_limit'),
                    'pos_device_limit' => $plan->limitValue('pos_device_limit'),
                ],
                'features' => array_map(fn($key) => [
                    'key' => $key,
                    'enabled' => in_array($key, $enabledFeatures),
                ], $featureKeys),
            ];
        });

        $featureCategories = [
            ['label' => 'Product Features', 'keys' => ['single_products', 'variable_products', 'combo_products', 'digital_products']],
            ['label' => 'Analytics', 'keys' => ['reports']],
            ['label' => 'Store Features', 'keys' => ['custom_domain', 'advanced_seo', 'theme_editor', 'custom_css', 'maintenance_mode']],
            ['label' => 'Customer Features', 'keys' => ['reviews', 'wishlist', 'compare']],
            ['label' => 'Marketing', 'keys' => ['coupons', 'promotions', 'flash_sales']],
            ['label' => 'Integrations', 'keys' => ['telegram_integration', 'whatsapp_integration', 'social_media_integration', 'google_analytics', 'meta_pixel', 'mailchimp_integration']],
            ['label' => 'AI', 'keys' => ['ai_product_generator', 'ai_description', 'ai_seo', 'ai_translation']],
            ['label' => 'Payment Gateways', 'keys' => ['payment_gateways_cod', 'payment_gateways_kbzpay', 'payment_gateways_wavepay', 'payment_gateways_stripe', 'payment_gateways_paypal', 'payment_gateways_manual']],
        ];

        $featureCategories = array_map(function ($cat) use ($allFeatureDefs) {
            $cat['features'] = array_values(array_filter(array_map(function ($key) use ($allFeatureDefs) {
                $def = current(array_filter($allFeatureDefs, fn($d) => $d['key'] === $key));
                return $def ? $def : null;
            }, $cat['keys'])));
            return $cat;
        }, $featureCategories);

        $auditLogs = collect();
        if ($subscription) {
            $logs = SubscriptionAuditLog::where('subscription_id', $subscription->id)
                ->latest()
                ->take(20)
                ->get();

            $auditLogs = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'reason' => $log->reason,
                    'created_at' => $log->created_at->diffForHumans(),
                ];
            });
        }

        $pendingIntent = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->with('plan:id,name')
            ->latest()
            ->first();

        $renewalIntent = $subscription
            ? PaymentIntent::forTenant($tenant->id)
                ->where('plan_id', $subscription->plan_id)
                ->whereIn('status', ['waiting_payment', 'waiting_review'])
                ->latest()
                ->first()
            : null;

        $activeCancellation = $subscription ? $subscription->activeCancellationRequest()->first() : null;
        $latestCancellation = $subscription ? $subscription->cancellationRequests()->latest()->first() : null;

        return Inertia::render('Admin/Billing/Index', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'description' => $subscription->plan->description,
                    'monthly_price' => $subscription->plan->monthly_price,
                    'yearly_price' => $subscription->plan->yearly_price,
                    'yearly_savings_percent' => $subscription->plan->yearlySavingsPercent(),
                    'product_limit' => $subscription->plan->product_limit,
                    'staff_limit' => $subscription->plan->staff_limit,
                    'storage_limit' => $subscription->plan->storage_limit,
                    'limits' => [
                        'product_limit' => $subscription->plan->productLimit(),
                        'staff_limit' => $subscription->plan->staffLimit(),
                        'storage_limit' => $subscription->plan->storageLimitMb(),
                        'orders_monthly_limit' => $subscription->plan->limitValue('orders_monthly_limit'),
                        'coupon_limit' => $subscription->plan->limitValue('coupon_limit'),
                        'promotion_limit' => $subscription->plan->limitValue('promotion_limit'),
                        'flash_sale_limit' => $subscription->plan->limitValue('flash_sale_limit'),
                        'branch_limit' => $subscription->plan->limitValue('branch_limit'),
                        'warehouse_limit' => $subscription->plan->limitValue('warehouse_limit'),
                        'pos_device_limit' => $subscription->plan->limitValue('pos_device_limit'),
                    ],
                ] : null,
                'billing_interval' => $subscription->billing_interval,
                'price' => $subscription->billedPrice(),
                'starts_at' => $subscription->starts_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->daysLeftInTrial(),
                'cancelled_at' => $subscription->cancelled_at?->toDateString(),
                'suspended_at' => $subscription->suspended_at?->toDateString(),
                'days_until_expiry' => $subscription->daysUntilExpiry(),
                'days_since_expiry' => $subscription->daysSinceExpiry(),
                'on_trial' => $subscription->isTrialing(),
                'pending_plan' => $subscription->pendingPlan ? [
                    'id' => $subscription->pendingPlan->id,
                    'name' => $subscription->pendingPlan->name,
                    'slug' => $subscription->pendingPlan->slug,
                ] : null,
                'pending_plan_effective_at' => $subscription->pending_plan_effective_at?->toDateString(),
                'extra_renewal_used_at' => $subscription->extra_renewal_used_at?->toDateTimeString(),
                'next_billing_date' => $subscription->expires_at?->isFuture()
                    ? $subscription->expires_at->toDateString()
                    : ($subscription->plan && !$subscription->plan->isFree()
                        ? $subscription->plan->calculateExpiryDate(now(), $subscription->billing_interval ?? 'monthly')?->toDateString()
                        : null),
            ] : null,
            'usage' => $usage,
            'plans' => $plans,
            'pendingPayment' => $pendingIntent ? [
                'reference_number' => $pendingIntent->reference_number,
                'status' => $pendingIntent->status,
                'amount' => $pendingIntent->amount,
                'currency' => $pendingIntent->currency,
                'billing_cycle' => $pendingIntent->billing_cycle,
                'plan_name' => $pendingIntent->plan?->name,
                'created_at' => $pendingIntent->created_at?->toDateTimeString(),
            ] : null,
            'featureCategories' => $featureCategories,
            'allFeatureDefs' => $allFeatureDefs,
            'auditLogs' => $auditLogs,
            'trialDays' => (int) (PlatformSetting::current()->trial_days ?? 14),
            'trial' => $subscription ? [
                'is_trial' => $subscription->isTrialing(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->daysLeftInTrial(),
                'renewal_allowed' => (bool) PlatformSetting::current()->allow_trial_renewal,
                'max_renewals' => (int) PlatformSetting::current()->max_trial_renewals,
                'renewals_used' => (int) $subscription->trial_renewals_count,
                'renewals_remaining' => $subscription->trialRenewalsRemaining(),
                'can_renew' => $subscription->canRenewTrial(),
                // A trial is only "ended" when the subscription is genuinely inactive/unpaid.
                // Active paid subscriptions that were converted from a trial must never show this.
                'trial_ended' => $subscription->trial_ends_at !== null
                    && $subscription->trial_ends_at->isPast()
                    && !$subscription->isTrialing()
                    && !$subscription->isActive(),
            ] : null,
            'renewal' => $this->renewalPayload($subscription, $renewalIntent),
            'cancellation' => [
                'active' => $activeCancellation ? $this->cancellationPayload($activeCancellation, $subscription) : null,
                'latest' => $latestCancellation ? $this->cancellationPayload($latestCancellation, $subscription) : null,
                'reasons' => SubscriptionCancellationRequest::REASONS,
            ],
        ]);
    }

    public function subscription()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;

        return Inertia::render('Admin/Billing/Subscription', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'monthly_price' => $subscription->plan->monthly_price,
                    'yearly_price' => $subscription->plan->yearly_price,
                ] : null,
                'pending_plan' => $subscription->pendingPlan ? [
                    'id' => $subscription->pendingPlan->id,
                    'name' => $subscription->pendingPlan->name,
                    'slug' => $subscription->pendingPlan->slug,
                ] : null,
                'pending_plan_effective_at' => $subscription->pending_plan_effective_at?->toDateString(),
                'billing_interval' => $subscription->billing_interval,
                'price' => $subscription->billedPrice(),
                'starts_at' => $subscription->starts_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->daysLeftInTrial(),
                'cancelled_at' => $subscription->cancelled_at?->toDateString(),
                'suspended_at' => $subscription->suspended_at?->toDateString(),
                'on_trial' => $subscription->isTrialing(),
            ] : null,
        ]);
    }

    public function upgrade()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;
        $currentPlan = $subscription?->plan;

        $allPlans = Plan::active()->ordered()->get();
        $allFeatureDefs = FeatureGate::getAllFeatureDefinitions();
        $featureKeys = array_column($allFeatureDefs, 'key');

        $plans = $allPlans->map(function ($plan) use ($featureKeys, $currentPlan) {
            $enabledFeatures = $plan->getEnabledFeatures();
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'is_current' => $currentPlan && $plan->id === $currentPlan->id,
                'yearly_savings_percent' => $plan->yearlySavingsPercent(),
                'product_limit' => $plan->productLimit(),
                'staff_limit' => $plan->staffLimit(),
                'storage_limit' => $plan->storageLimitMb(),
                'limits' => [
                    'product_limit' => $plan->productLimit(),
                    'staff_limit' => $plan->staffLimit(),
                    'storage_limit' => $plan->storageLimitMb(),
                    'orders_monthly_limit' => $plan->limitValue('orders_monthly_limit'),
                    'coupon_limit' => $plan->limitValue('coupon_limit'),
                    'promotion_limit' => $plan->limitValue('promotion_limit'),
                    'flash_sale_limit' => $plan->limitValue('flash_sale_limit'),
                    'branch_limit' => $plan->limitValue('branch_limit'),
                    'warehouse_limit' => $plan->limitValue('warehouse_limit'),
                    'pos_device_limit' => $plan->limitValue('pos_device_limit'),
                ],
                'features' => array_map(fn($key) => [
                    'key' => $key,
                    'enabled' => in_array($key, $enabledFeatures),
                ], $featureKeys),
            ];
        });

        $usage = $subscription ? SubscriptionLimitService::for($tenant)->getAllLimits() : [];

        $featureCategories = [
            ['label' => 'Product Features', 'keys' => ['single_products', 'variable_products', 'combo_products', 'digital_products']],
            ['label' => 'Analytics', 'keys' => ['reports']],
            ['label' => 'Store Features', 'keys' => ['custom_domain', 'advanced_seo', 'theme_editor', 'custom_css', 'maintenance_mode']],
            ['label' => 'Customer Features', 'keys' => ['reviews', 'wishlist', 'compare']],
            ['label' => 'Marketing', 'keys' => ['coupons', 'promotions', 'flash_sales']],
            ['label' => 'Integrations', 'keys' => ['telegram_integration', 'whatsapp_integration', 'social_media_integration', 'google_analytics', 'meta_pixel', 'mailchimp_integration']],
            ['label' => 'AI', 'keys' => ['ai_product_generator', 'ai_description', 'ai_seo', 'ai_translation']],
            ['label' => 'Payment Gateways', 'keys' => ['payment_gateways_cod', 'payment_gateways_kbzpay', 'payment_gateways_wavepay', 'payment_gateways_stripe', 'payment_gateways_paypal', 'payment_gateways_manual']],
        ];

        $featureCategories = array_map(function ($cat) use ($allFeatureDefs) {
            $cat['features'] = array_values(array_filter(array_map(function ($key) use ($allFeatureDefs) {
                $def = current(array_filter($allFeatureDefs, fn($d) => $d['key'] === $key));
                return $def ? $def : null;
            }, $cat['keys'])));
            return $cat;
        }, $featureCategories);

        return Inertia::render('Admin/Billing/UpgradePlan', [
            'currentPlan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
            ] : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ] : null,
                'pending_plan' => $subscription->pendingPlan ? [
                    'id' => $subscription->pendingPlan->id,
                    'name' => $subscription->pendingPlan->name,
                    'slug' => $subscription->pendingPlan->slug,
                ] : null,
                'pending_plan_effective_at' => $subscription->pending_plan_effective_at?->toDateString(),
                'billing_interval' => $subscription->billing_interval,
                'starts_at' => $subscription->starts_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->daysLeftInTrial(),
                'on_trial' => $subscription->isTrialing(),
            ] : null,
            'plans' => $plans,
            'usage' => $usage,
            'allFeatureDefs' => $allFeatureDefs,
            'featureCategories' => $featureCategories,
            'trialDays' => (int) (PlatformSetting::current()->trial_days ?? 14),
        ]);
    }

    public function paymentHistory(Request $request)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $query = PaymentIntent::forTenant($tenant->id)
            ->with(['plan', 'evidences', 'timelineEvents', 'comments', 'reviews'])
            ->latest();

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Plan filter
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        // Reference / plan name search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('plan', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $intents = $query->paginate($perPage);

        $intents->getCollection()->transform(function ($intent) {
            $currency = CurrencyCode::tryFrom($intent->currency) ?? CurrencyCode::MMK;
            return [
                'id' => $intent->id,
                'reference_number' => $intent->reference_number,
                'billing_cycle' => $intent->billing_cycle,
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
                'currency_symbol' => $currency->symbol(),
                'gateway' => $intent->gateway,
                'status' => $intent->status,
                'expires_at' => $intent->expires_at?->toDateTimeString(),
                'created_at' => $intent->created_at->toDateTimeString(),
                'plan' => $intent->plan ? [
                    'id' => $intent->plan->id,
                    'name' => $intent->plan->name,
                    'slug' => $intent->plan->slug,
                ] : null,
                'evidences' => $intent->evidences->map(fn($ev) => [
                    'id' => $ev->id,
                    'type' => $ev->type,
                    'file_path' => ImageService::url($ev->file_path),
                    'note' => $ev->note,
                    'sender_name' => $ev->sender_name,
                    'sender_account' => $ev->sender_account,
                    'transaction_reference' => $ev->transaction_reference,
                    'transferred_amount' => $ev->transferred_amount ? (float) $ev->transferred_amount : null,
                    'transfer_date' => $ev->transfer_date?->toDateString(),
                ])->values()->all(),
                'timeline' => $intent->timelineEvents->sortBy('occurred_at')->values()->map(fn($tl) => [
                    'id' => $tl->id,
                    'type' => $tl->type,
                    'description' => $tl->description,
                    'occurred_at' => $tl->occurred_at?->toDateTimeString(),
                ])->all(),
                'comments' => $intent->comments->sortByDesc('created_at')->values()->map(fn($c) => [
                    'id' => $c->id,
                    'author_name' => $c->author_name,
                    'author_type' => $c->author_type,
                    'body' => $c->body,
                    'created_at' => $c->created_at->toDateTimeString(),
                ])->all(),
                'reviews' => $intent->reviews->map(fn($r) => [
                    'id' => $r->id,
                    'action' => $r->action,
                    'reviewer_name' => $r->reviewer_name,
                    'reason' => $r->reason,
                    'created_at' => $r->created_at?->toDateTimeString(),
                ])->all(),
                'subscription_event' => $intent->timelineEvents
                    ->sortBy('occurred_at')
                    ->first(fn($tl) => in_array($tl->type, ['subscription_activated', 'subscription_renewed']))
                    ?->type,
            ];
        });

        $subscription = $tenant->subscription;
        $plans = Plan::active()->ordered()->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Billing/PaymentHistory', [
            'intents' => $intents,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'plan_id', 'search', 'per_page']),
            'plans' => $plans,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ] : null,
            ] : null,
            'stats' => [
                'total' => PaymentIntent::forTenant($tenant->id)->count(),
                'completed' => PaymentIntent::forTenant($tenant->id)->whereIn('status', ['completed', 'approved', 'paid'])->count(),
                'pending_review' => PaymentIntent::forTenant($tenant->id)->where('status', 'waiting_review')->count(),
                'rejected' => PaymentIntent::forTenant($tenant->id)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function settings()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Billing/Settings');
    }

    public function changePlanPreview(Request $request, SubscriptionPlanChangeService $planChange)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
        ]);

        $subscription = $tenant->subscription;
        if (!$subscription || !$subscription->plan) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'No active subscription found.');
        }

        $targetPlan = Plan::active()->findOrFail($request->plan_id);

        if ($targetPlan->id === $subscription->plan_id) {
            return admin_redirect('admin.billing.upgrade')
                ->with('info', 'You are already on this plan.');
        }

        $proration = $planChange->calculateProration(
            $subscription,
            $targetPlan,
            $request->billing_interval
        );

        $pendingIntent = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();

        return Inertia::render('Admin/Billing/PlanChange', [
            'currentPlan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'monthly_price' => $subscription->plan->monthly_price,
                'yearly_price' => $subscription->plan->yearly_price,
            ],
            'targetPlan' => [
                'id' => $targetPlan->id,
                'name' => $targetPlan->name,
                'slug' => $targetPlan->slug,
                'description' => $targetPlan->description,
                'monthly_price' => $targetPlan->monthly_price,
                'yearly_price' => $targetPlan->yearly_price,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'expires_at' => $subscription->expires_at?->toDateString(),
                'billing_interval' => $subscription->billing_interval,
            ],
            'proration' => $proration,
            'isTrial' => $subscription->isTrialing(),
            'trialEndsAt' => $subscription->trial_ends_at?->toDateString(),
            'scheduledChange' => !$subscription->isTrialing() && $subscription->hasPendingDowngrade() && $subscription->pendingPlan ? [
                'plan_id' => $subscription->pendingPlan->id,
                'name' => $subscription->pendingPlan->name,
                'effective_at' => $subscription->pending_plan_effective_at?->toDateString(),
            ] : null,
            'pendingPayment' => $pendingIntent ? [
                'reference_number' => $pendingIntent->reference_number,
                'status' => $pendingIntent->status,
                'is_upgrade' => ($pendingIntent->metadata['type'] ?? null) === SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE
                    && (int) $pendingIntent->plan_id === $targetPlan->id,
            ] : null,
        ]);
    }

    public function changePlanExecute(Request $request, SubscriptionPlanChangeService $planChange)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
        ]);

        $subscription = $tenant->subscription;
        if (!$subscription || !$subscription->plan) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'No active subscription found.');
        }

        $targetPlan = Plan::active()->findOrFail($request->plan_id);

        if ($targetPlan->id === $subscription->plan_id) {
            return admin_redirect('admin.billing')
                ->with('info', 'You are already on this plan.');
        }

        $hasPendingPayment = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->exists();

        if ($hasPendingPayment) {
            return admin_redirect('admin.billing')
                ->with('error', 'You have a payment awaiting completion or review. Please resolve it before changing your plan.');
        }

        $isUpgrade = $subscription->isUpgrade($targetPlan);

        if ($isUpgrade) {
            return admin_redirect('admin.billing.upgrade')
                ->with('info', 'Upgrades require payment. Please review the prorated amount and confirm the upgrade.');
        }

        $planChange->executeDowngrade($subscription, $targetPlan, $request->billing_interval);

        $action = $subscription->hasPendingDowngrade() ? 'downgrade scheduled' : 'changed';

        return admin_redirect('admin.billing')
            ->with('success', "Your plan has been {$action} successfully.");
    }

    public function changePlanSubmit(Request $request, SubscriptionPlanChangeService $planChange)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_interval' => ['nullable', 'in:monthly,yearly'],
            'scheduled_change_action' => ['nullable', 'in:cancel,keep'],
        ]);

        $subscription = $tenant->subscription;
        if (!$subscription || !$subscription->plan) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'No active subscription found.');
        }

        $targetPlan = Plan::active()->findOrFail($validated['plan_id']);

        if ($targetPlan->id === $subscription->plan_id) {
            return admin_redirect('admin.billing')
                ->with('info', 'You are already on this plan.');
        }

        $interval = $validated['billing_interval'] ?? $subscription->billing_interval ?? 'monthly';

        $proration = $planChange->calculateProration($subscription, $targetPlan, $interval);

        if (!$proration['is_upgrade']) {
            return admin_redirect('admin.billing')
                ->with('error', 'This plan is a downgrade. Please use the downgrade flow.');
        }

        if (!$subscription->isTrialing() && $subscription->hasPendingDowngrade() && empty($validated['scheduled_change_action'])) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'You have a scheduled plan change. Choose whether to cancel or keep it before upgrading.');
        }

        $pending = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();

        if ($pending) {
            $isSameUpgrade = ($pending->metadata['type'] ?? null) === SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE
                && (int) $pending->plan_id === $targetPlan->id
                && $pending->billing_cycle === $interval;

            if ($isSameUpgrade) {
                return admin_redirect('admin.billing.payment', ['intent' => $pending->reference_number])
                    ->with('info', 'You already have a pending request for this upgrade.');
            }

            return admin_redirect('admin.billing')
                ->with('error', 'You have a payment awaiting completion or review. Please resolve it before changing your plan.');
        }

        $isTrialConversion = $subscription->isTrialing();

        $amountDue = (float) $proration['amount_due'];

        if (!$isTrialConversion && $amountDue <= 0.0) {
            if ($proration['remaining_fraction'] <= 0) {
                return admin_redirect('admin.billing.upgrade')
                    ->with('info', 'Your current period has ended. Please purchase the plan to start a new period.');
            }

            $planChange->executeUpgrade($subscription, $targetPlan, $interval);

            return admin_redirect('admin.billing')
                ->with('success', 'Your plan has been upgraded successfully.');
        }

        $metadata = [
            'source' => $isTrialConversion ? 'merchant_trial_conversion' : 'merchant_upgrade',
            'subscription_id' => $subscription->id,
            'payment_type' => $isTrialConversion ? PaymentIntent::TYPE_NEW_SUBSCRIPTION : PaymentIntent::TYPE_UPGRADE,
            'from_plan_id' => $subscription->plan?->id,
            'from_plan_name' => $subscription->plan?->name,
            'requested_amount' => $amountDue,
            'requested_at' => now()->toDateTimeString(),
            'proration' => $proration,
        ];

        if ($isTrialConversion) {
            $metadata['trial_conversion'] = true;
            $metadata['payment_reason'] = 'new_subscription';
        } else {
            $metadata['type'] = SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE;
            $metadata['payment_reason'] = 'plan_upgrade';
            $metadata['scheduled_change_action'] = $validated['scheduled_change_action'] ?? 'keep';
        }

        try {
            $currencyCode = CurrencyCode::tryFrom($tenant->websiteInfo?->currency_code ?? 'MMK') ?? CurrencyCode::MMK;

            $intent = app(CheckoutService::class)->initiateCheckout(
                tenant: $tenant,
                plan: $targetPlan,
                billingCycle: $interval,
                amount: $amountDue,
                currency: Currency::fromEnum($currencyCode),
                gateway: 'manual',
                metadata: $metadata,
            );
        } catch (\InvalidArgumentException $e) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'Unable to start the payment. Please try again.');
        }

        return admin_redirect('admin.billing.payment', ['intent' => $intent->reference_number])
            ->with('success', $isTrialConversion
                ? 'Conversion request created. Submit your payment to activate the paid plan.'
                : 'Upgrade request created. Submit your payment to complete the upgrade.');
    }

    public function cancelScheduledChange(Request $request, SubscriptionPlanChangeService $planChange)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403);
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;
        if (!$subscription || !$subscription->hasPendingDowngrade()) {
            return admin_redirect('admin.billing')
                ->with('info', 'No scheduled plan change found.');
        }

        $planChange->cancelScheduledChange($subscription);

        return admin_redirect('admin.billing')
            ->with('success', 'Scheduled plan change has been cancelled.');
    }

    public function checkout(Request $request, string $planSlug)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $plan = Plan::active()->where('slug', $planSlug)->first();

        if (!$plan) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'The selected plan is not available.');
        }

        $subscription = $tenant->subscription;

        if ($subscription && $subscription->plan && $subscription->plan->id === $plan->id) {
            return admin_redirect('admin.billing')
                ->with('info', 'You are already on this plan.');
        }

        $billingCycle = $request->validate(['billing_cycle' => ['nullable', 'in:monthly,yearly']])['billing_cycle'] ?? 'monthly';

        $intentData = null;
        $amount = (float) ($plan->getPriceForInterval($billingCycle) ?? 0);

        if ($amount > 0) {
            try {
                $currencyCode = CurrencyCode::tryFrom($tenant->websiteInfo?->currency_code ?? 'MMK') ?? CurrencyCode::MMK;

                $intent = app(CheckoutService::class)->initiateCheckout(
                    tenant: $tenant,
                    plan: $plan,
                    billingCycle: $billingCycle,
                    amount: $amount,
                    currency: Currency::fromEnum($currencyCode),
                    gateway: 'manual',
                    metadata: ['source' => 'merchant_checkout'],
                );

                $intentData = [
                    'id' => $intent->id,
                    'reference_number' => $intent->reference_number,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'status' => $intent->status,
                    'billing_cycle' => $intent->billing_cycle,
                    'expires_at' => $intent->expires_at?->toDateTimeString(),
                    'created_at' => $intent->created_at->toDateTimeString(),
                ];
            } catch (\InvalidArgumentException $e) {
                $intentData = null;
            }
        }

        try {
            $allFeatureDefs = FeatureGate::getAllFeatureDefinitions();
            $featureKeys = array_column($allFeatureDefs, 'key');

            $plans = Plan::active()->ordered()->get()->map(function ($p) use ($featureKeys) {
                $enabledFeatures = $p->getEnabledFeatures();
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'monthly_price' => $p->monthly_price,
                    'yearly_price' => $p->yearly_price,
                    'yearly_savings_percent' => $p->yearlySavingsPercent(),
                    'product_limit' => $p->productLimit(),
                    'staff_limit' => $p->staffLimit(),
                    'storage_limit' => $p->storageLimitMb(),
                    'limits' => [
                        'product_limit' => $p->productLimit(),
                        'staff_limit' => $p->staffLimit(),
                        'storage_limit' => $p->storageLimitMb(),
                        'orders_monthly_limit' => $p->limitValue('orders_monthly_limit'),
                        'coupon_limit' => $p->limitValue('coupon_limit'),
                        'promotion_limit' => $p->limitValue('promotion_limit'),
                        'flash_sale_limit' => $p->limitValue('flash_sale_limit'),
                    ],
                    'features' => array_map(fn($key) => [
                        'key' => $key,
                        'enabled' => in_array($key, $enabledFeatures),
                    ], $featureKeys),
                ];
            });

            $currentPlan = $subscription?->plan;
            $paymentMethods = BillingPaymentMethod::active()
                ->where('supports_manual_payment', true)
                ->orderBy('sort_order')
                ->orderBy('display_name')
                ->get()
                ->map(fn($pm) => [
                    'id' => $pm->id,
                    'name' => $pm->display_name,
                    'account_name' => $pm->account_name,
                    'account_number' => $pm->account_number,
                    'bank_name' => $pm->bank_name,
                    'instructions' => $pm->instructions,
                    'qr_image_url' => $pm->qr_image_url,
                ]);

            return Inertia::render('Admin/Billing/Checkout', [
                'intent' => $intentData,
                'billingCycle' => $billingCycle,
                'selectedPlan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'monthly_price' => $plan->monthly_price,
                    'yearly_price' => $plan->yearly_price,
                    'yearly_savings_percent' => $plan->yearlySavingsPercent(),
                    'product_limit' => $plan->productLimit(),
                    'staff_limit' => $plan->staffLimit(),
                    'storage_limit' => $plan->storageLimitMb(),
                    'limits' => [
                        'product_limit' => $plan->productLimit(),
                        'staff_limit' => $plan->staffLimit(),
                        'storage_limit' => $plan->storageLimitMb(),
                        'orders_monthly_limit' => $plan->limitValue('orders_monthly_limit'),
                        'coupon_limit' => $plan->limitValue('coupon_limit'),
                        'promotion_limit' => $plan->limitValue('promotion_limit'),
                        'flash_sale_limit' => $plan->limitValue('flash_sale_limit'),
                    ],
                    'features' => array_map(fn($key) => [
                        'key' => $key,
                        'enabled' => in_array($key, $plan->getEnabledFeatures()),
                    ], $featureKeys),
                ],
                'currentPlan' => $currentPlan ? [
                    'id' => $currentPlan->id,
                    'name' => $currentPlan->name,
                    'slug' => $currentPlan->slug,
                    'description' => $currentPlan->description,
                    'monthly_price' => $currentPlan->monthly_price,
                    'yearly_price' => $currentPlan->yearly_price,
                    'product_limit' => $currentPlan->productLimit(),
                    'staff_limit' => $currentPlan->staffLimit(),
                    'storage_limit' => $currentPlan->storageLimitMb(),
                    'limits' => [
                        'product_limit' => $currentPlan->productLimit(),
                        'staff_limit' => $currentPlan->staffLimit(),
                        'storage_limit' => $currentPlan->storageLimitMb(),
                        'orders_monthly_limit' => $currentPlan->limitValue('orders_monthly_limit'),
                        'coupon_limit' => $currentPlan->limitValue('coupon_limit'),
                        'promotion_limit' => $currentPlan->limitValue('promotion_limit'),
                        'flash_sale_limit' => $currentPlan->limitValue('flash_sale_limit'),
                    ],
                    'features' => array_map(fn($key) => [
                        'key' => $key,
                        'enabled' => in_array($key, $currentPlan->getEnabledFeatures()),
                    ], $featureKeys),
                ] : null,
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'billing_interval' => $subscription->billing_interval,
                    'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                    'trial_days_remaining' => $subscription->daysLeftInTrial(),
                    'on_trial' => $subscription->isTrialing(),
                    'expires_at' => $subscription->expires_at?->toDateString(),
                ] : null,
                'allFeatureDefs' => $allFeatureDefs,
                'plans' => $plans,
                'paymentMethods' => $paymentMethods,
            ]);
        } catch (\Exception $e) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'Unable to prepare checkout. Please try again or contact support.');
        }
    }

    public function payment(Request $request)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $reference = $request->query('intent');
        $intent = null;
        $intentData = null;
        $selectedPlan = null;

        if ($reference) {
            $intentService = app(PaymentIntentService::class);
            $intent = $intentService->findByReferenceForTenant($tenant, $reference);

            if ($intent && $intent->plan) {
                $plan = $intent->plan;
                $featureKeys = array_column(FeatureGate::getAllFeatureDefinitions(), 'key');
                $selectedPlan = [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'monthly_price' => $plan->monthly_price,
                    'yearly_price' => $plan->yearly_price,
                ];

                $intentData = [
                    'id' => $intent->id,
                    'reference_number' => $intent->reference_number,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'status' => $intent->status,
                    'billing_cycle' => $intent->billing_cycle,
                    'expires_at' => $intent->expires_at?->toDateTimeString(),
                    'created_at' => $intent->created_at->toDateTimeString(),
                ];
            }
        }

            $paymentMethods = BillingPaymentMethod::active()
            ->where('supports_manual_payment', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->map(fn($pm) => [
                'id' => $pm->id,
                'name' => $pm->display_name,
                'display_name' => $pm->display_name,
                'type' => $pm->type,
                'account_name' => $pm->account_name,
                'account_number' => $pm->account_number,
                'bank_name' => $pm->bank_name,
                'branch' => $pm->branch,
                'instructions' => $pm->instructions,
                'currency' => $pm->currency,
                'qr_image_url' => $pm->qr_image_url,
                'is_active' => $pm->is_active,
            ]);

        $subscription = $tenant->subscription;
        $currentPlan = $subscription?->plan;

        $upgradeDetails = null;
        $isUpgradeIntent = $intent && ($intent->metadata['type'] ?? null) === SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE;
        $isTrialConversionIntent = $intent && ($intent->metadata['trial_conversion'] ?? false) === true;

        if ($isUpgradeIntent || $isTrialConversionIntent) {
            $upgradeDetails = [
                'proration' => $intent->metadata['proration'] ?? [],
                'current_expires_at' => $subscription?->expires_at?->toDateString(),
                'effective_date' => $intent->metadata['proration']['effective_date'] ?? now()->toDateString(),
                'scheduled_change_action' => $intent->metadata['scheduled_change_action'] ?? 'keep',
                'is_trial_conversion' => $isTrialConversionIntent,
            ];
        }

        return Inertia::render('Admin/Billing/Payment', [
            'intent' => $intentData,
            'selectedPlan' => $selectedPlan,
            'currentPlan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
            ] : null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'billing_interval' => $subscription->billing_interval,
            ] : null,
            'paymentMethods' => $paymentMethods,
            'upgradeDetails' => $upgradeDetails,
        ] + $this->paymentFormContext($subscription, $intent?->plan, $intent));
    }

    public function paymentSubmit(Request $request)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            return response()->json(['error' => 'Store not found.'], 403);
        }

        $validated = $request->validate([
            'intent_reference' => ['nullable', 'string'],
            'plan_slug' => ['required_without:intent_reference', 'nullable', 'string'],
            'billing_cycle' => ['required_without:intent_reference', 'nullable', 'in:monthly,yearly'],
            'submission_key' => ['required', 'string', 'max:100'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_account' => ['required', 'string', 'max:255'],
            'transaction_reference' => ['required', 'string', 'max:255'],
            'transferred_amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'transfer_time' => ['nullable', 'date_format:H:i'],
            'evidence' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method_id' => ['nullable', 'exists:billing_payment_methods,id'],
            'payment_reason' => ['nullable', 'string', 'in:' . implode(',', array_keys(PaymentIntent::PAYMENT_REASONS))],
            'payment_reason_text' => ['nullable', 'string', 'max:255', 'required_if:payment_reason,other'],
        ]);

        $intentService = app(PaymentIntentService::class);
        $intent = null;

        if (!empty($validated['intent_reference'])) {
            $intent = $intentService->findByReferenceForTenant($tenant, $validated['intent_reference']);
        } else {
            $plan = Plan::active()->where('slug', $validated['plan_slug'])->first();
            if (!$plan) {
                return redirect()->back()->with('error', 'The selected plan is not available.');
            }

            $currencyCode = CurrencyCode::tryFrom($tenant->websiteInfo?->currency_code ?? 'MMK') ?? CurrencyCode::MMK;
            $currency = Currency::fromEnum($currencyCode);
            $billingCycle = $validated['billing_cycle'];
            $amount = (float) ($plan->getPriceForInterval($billingCycle) ?? 0);

            $intent = app(CheckoutService::class)->initiateCheckout(
                tenant: $tenant,
                plan: $plan,
                billingCycle: $billingCycle,
                amount: $amount,
                currency: $currency,
                gateway: 'manual',
                metadata: [
                    'source' => 'merchant_checkout',
                    'submission_key' => $validated['submission_key'],
                ],
            );
        }

        if (!$intent) {
            return redirect()->back()->with('error', 'Payment intent not found.');
        }

        if ($intent->status !== 'waiting_payment') {
            $sameSubmission = $intent->status === 'waiting_review'
                && ($intent->metadata['submission_key'] ?? null) === $validated['submission_key']
                && $intent->evidences()
                    ->where('transaction_reference', $validated['transaction_reference'])
                    ->exists();

            if ($sameSubmission) {
                try {
                    $this->ensureIntentInvoice($intent);
                } catch (\Throwable $e) {
                    Log::error('Payment submission recovery failed on retry path.', [
                        'intent_id' => $intent->id,
                        'reference_number' => $intent->reference_number,
                        'error' => $e->getMessage(),
                    ]);

                    return redirect()->back()->with('error', 'Failed to submit payment. Please try again.');
                }

                return admin_redirect('admin.billing.payment', [
                    'intent' => $intent->reference_number,
                    'submitted' => 'true',
                ])->with('success', 'Payment submitted successfully! Your payment is now awaiting review.');
            }

            return redirect()->back()->with('error', 'This payment has already been submitted or cannot be submitted.');
        }

        $metadata = $intent->metadata ?? [];
        $metadata['submission_key'] = $validated['submission_key'];

        if (!empty($validated['payment_reason'])) {
            $metadata['payment_reason'] = $validated['payment_reason'];
            $metadata['payment_reason_text'] = $validated['payment_reason'] === 'other'
                ? ($validated['payment_reason_text'] ?? null)
                : null;
        }

        if (empty($metadata['from_plan_id'])) {
            $currentPlan = $tenant->subscription?->plan;
            if ($currentPlan && !$currentPlan->isFree()) {
                $metadata['from_plan_id'] = $currentPlan->id;
                $metadata['from_plan_name'] = $currentPlan->name;
            }
        }

        $metadata['payment_type'] = PaymentIntent::classifyPaymentType($metadata);

        $intent->update(['metadata' => $metadata]);

        if ($intent->evidences()->where('transaction_reference', $validated['transaction_reference'])->exists()) {
            try {
                if ($intent->status === 'waiting_payment') {
                    app(ManualPaymentService::class)->confirmPayment($intent);
                    $intent->refresh();
                }

                $this->ensureIntentInvoice($intent);
            } catch (\Throwable $e) {
                Log::error('Payment submission failed on evidence-resubmit path.', [
                    'intent_id' => $intent->id,
                    'reference_number' => $intent->reference_number,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'Failed to submit payment. Please try again.');
            }

            return admin_redirect('admin.billing.payment', [
                'intent' => $intent->reference_number,
                'submitted' => 'true',
            ])->with('success', 'Payment submitted successfully! Your payment is now awaiting review.');
        }

        try {
            $filePath = $this->imageService->upload($request->file('evidence'), 'payment-evidence');

            $evidenceService = app(PaymentEvidenceService::class);
            $evidenceService->store(
                intent: $intent,
                type: 'bank_transfer',
                filePath: $filePath,
                note: $validated['note'] ?? null,
                metadata: [
                    'payment_method_id' => $validated['payment_method_id'] ?? null,
                    'uploaded_by' => 'merchant',
                    'submitted_at' => now()->toDateTimeString(),
                    'transfer_time' => $validated['transfer_time'] ?? null,
                ],
                senderName: $validated['sender_name'],
                senderAccount: $validated['sender_account'],
                transactionReference: $validated['transaction_reference'],
                transferredAmount: (float) $validated['transferred_amount'],
                transferDate: $validated['transfer_date'],
            );

            $manualPayment = app(ManualPaymentService::class);
            $manualPayment->confirmPayment($intent);
            app(\App\Services\InvoiceService::class)->generateFromPaymentIntent($intent->fresh());

            $intent->refresh();

            return admin_redirect('admin.billing.payment', [
                'intent' => $intent->reference_number,
                'submitted' => 'true',
            ])->with('success', 'Payment submitted successfully! Your payment is now awaiting review.');

        } catch (\Exception $e) {
            Log::error('Payment submission failed.', [
                'intent_id' => $intent?->id,
                'reference_number' => $intent?->reference_number,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to submit payment. Please try again.');
        }
    }

    private function ensureIntentInvoice(PaymentIntent $intent): void
    {
        try {
            app(\App\Services\InvoiceService::class)->generateFromPaymentIntent($intent->fresh());
        } catch (\Throwable $e) {
            Log::error('Payment submission recovery failed: invoice could not be generated.', [
                'intent_id' => $intent->id,
                'reference_number' => $intent->reference_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException('Failed to submit payment. Please try again.');
        }
    }

    public function renew(Request $request)
    {
        if (!auth()->user()->can('billing.renew')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect()->back()->with('error', 'No subscription found.');
        }

        if ($subscription->isSuspended()) {
            return redirect()->back()->with('error', 'Your subscription has been suspended. Please contact support.');
        }

        // This endpoint only handles trial extensions. Paid renewals use the manual-payment workflow.
        if ($subscription->isTrialing()) {
            if (!$subscription->canRenewTrial()) {
                $settings = PlatformSetting::current();
                $message = !$settings->allow_trial_renewal
                    ? 'Trial renewal is disabled. Please choose a paid plan to continue.'
                    : 'Your trial renewal limit has been reached. Please choose a paid plan to continue.';

                return admin_redirect('admin.billing.upgrade')->with('error', $message);
            }

            $subscription->renewTrial();
            $subscription->refresh();

            SubscriptionAuditService::log($subscription, 'trial_renewed', [
                'reason' => 'Trial extended via self-service.',
                'metadata' => [
                    'trial_renewals_count' => $subscription->trial_renewals_count,
                    'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
                ],
            ]);

            return admin_redirect('admin.billing')
                ->with('success', 'Your trial has been extended!');
        }

        if ($subscription->isInGoodStanding()) {
            return redirect()->back()->with('error', 'Your subscription is already active.');
        }

        if ($subscription->plan?->isFree()) {
            return admin_redirect('admin.billing')
                ->with('info', 'Your current plan is free and does not require renewal.');
        }

        // An ended trial must convert to a paid plan; a lapsed paid plan must be renewed by payment.
        $endedTrial = $subscription->trial_ends_at !== null && $subscription->trial_ends_at->isPast();

        if ($endedTrial) {
            return admin_redirect('admin.billing.upgrade')
                ->with('info', 'Your trial has ended. Please choose a paid plan to continue.');
        }

        return admin_redirect('admin.billing.upgrade')
            ->with('info', 'Please complete a paid renewal to reactivate your subscription.');
    }

    /**
     * Start a manual-payment renewal of the current plan.
     */
    public function renewPlan(Request $request)
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;
        if (!$subscription || !$subscription->plan) {
            return admin_redirect('admin.billing.upgrade')
                ->with('error', 'No active subscription found.');
        }

        if ($subscription->plan->isFree()) {
            return admin_redirect('admin.billing')
                ->with('info', 'Your current plan is free and does not require renewal.');
        }

        $plan = $subscription->plan;
        $billingCycle = $subscription->billing_interval ?? 'monthly';
        $amount = (float) ($plan->getPriceForInterval($billingCycle) ?? 0);

        if ($amount <= 0) {
            return admin_redirect('admin.billing')
                ->with('error', 'The renewal amount could not be determined. Please contact support.');
        }

        $pending = PaymentIntent::forTenant($tenant->id)
            ->whereIn('status', ['waiting_payment', 'waiting_review'])
            ->latest()
            ->first();

        if ($pending && ((int) $pending->plan_id !== (int) $plan->id || $pending->billing_cycle !== $billingCycle)) {
            return admin_redirect('admin.billing.payment', ['intent' => $pending->reference_number])
                ->with('info', 'You already have a payment awaiting completion or review.');
        }

        $intent = null;
        try {
            $currencyCode = CurrencyCode::tryFrom($tenant->websiteInfo?->currency_code ?? 'MMK') ?? CurrencyCode::MMK;

            $intent = app(CheckoutService::class)->initiateCheckout(
                tenant: $tenant,
                plan: $plan,
                billingCycle: $billingCycle,
                amount: $amount,
                currency: Currency::fromEnum($currencyCode),
                gateway: 'manual',
                metadata: [
                    'source' => 'merchant_renewal',
                    'payment_type' => PaymentIntent::TYPE_RENEWAL,
                    'payment_reason' => 'subscription_renewal',
                ],
            );
        } catch (\InvalidArgumentException $e) {
            return admin_redirect('admin.billing')
                ->with('error', 'Unable to start renewal payment. Please try again.');
        }

        $paymentMethods = BillingPaymentMethod::active()
            ->where('supports_manual_payment', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->map(fn($pm) => [
                'id' => $pm->id,
                'name' => $pm->display_name,
                'display_name' => $pm->display_name,
                'type' => $pm->type,
                'account_name' => $pm->account_name,
                'account_number' => $pm->account_number,
                'bank_name' => $pm->bank_name,
                'branch' => $pm->branch,
                'instructions' => $pm->instructions,
                'currency' => $pm->currency,
                'qr_image_url' => $pm->qr_image_url,
                'is_active' => $pm->is_active,
            ]);

        return Inertia::render('Admin/Billing/Payment', [
            'intent' => $intent ? [
                'id' => $intent->id,
                'reference_number' => $intent->reference_number,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'status' => $intent->status,
                'billing_cycle' => $intent->billing_cycle,
                'expires_at' => $intent->expires_at?->toDateTimeString(),
                'created_at' => $intent->created_at->toDateTimeString(),
            ] : null,
            'selectedPlan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
            ],
            'currentPlan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'subscription' => [
                'status' => $subscription->status,
                'billing_interval' => $subscription->billing_interval,
                'expires_at' => $subscription->expires_at?->toDateString(),
            ],
            'paymentMethods' => $paymentMethods,
            'renewalMode' => true,
            'scheduledChange' => $subscription->hasPendingDowngrade() && $subscription->pendingPlan ? [
                'name' => $subscription->pendingPlan->name,
                'effective_at' => $subscription->pending_plan_effective_at?->toDateString(),
            ] : null,
        ] + $this->paymentFormContext($subscription, $plan, $intent));
    }

    public function requestCancellation(Request $request)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(SubscriptionCancellationRequest::REASONS))],
            'comment' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'],
        ]);

        $subscription = $tenant->subscription;
        if (!$subscription) {
            return admin_redirect('admin.billing')->with('error', 'No subscription found.');
        }

        if ($subscription->activeCancellationRequest()->exists()) {
            return admin_redirect('admin.billing')
                ->with('error', 'You already have a pending cancellation request.');
        }

        SubscriptionCancellationRequest::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'reason' => $data['reason'],
            'comment' => $data['comment'] ?? null,
            'status' => SubscriptionCancellationRequest::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);

        return admin_redirect('admin.billing')
            ->with('success', 'Your cancellation request has been submitted and is pending review.');
    }

    public function withdrawCancellation(Request $request)
    {
        if (!auth()->user()->can('billing.manage')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;
        $active = $subscription?->activeCancellationRequest()->first();

        if (!$active) {
            return admin_redirect('admin.billing')
                ->with('info', 'No pending cancellation request found.');
        }

        $active->update(['status' => SubscriptionCancellationRequest::STATUS_WITHDRAWN]);

        return admin_redirect('admin.billing')
            ->with('success', 'Your cancellation request has been withdrawn.');
    }

    private function renewalPayload(?Subscription $subscription, ?PaymentIntent $renewalIntent): array
    {
        $thresholdDays = min(90, max(1, (int) (PlatformSetting::current()->billing_renewal_reminder_days ?? 7)));

        $activePaid = (bool) (
            $subscription
            && $subscription->isActive()
            && $subscription->plan
            && !$subscription->plan->isFree()
        );

        $expiresAt = $subscription?->expires_at;
        $daysUntilExpiry = $expiresAt
            ? (int) ceil(max(0, $expiresAt->getTimestamp() - now()->getTimestamp()) / 86400)
            : null;

        // Boundary-inclusive: due as soon as expiry is within the threshold window.
        $due = $activePaid
            && $expiresAt !== null
            && $expiresAt->getTimestamp() <= now()->addDays($thresholdDays)->getTimestamp();

        $isLapsed = (bool) ($subscription && in_array($subscription->status, ['expired', 'past_due', 'canceled'], true));

        $pending = $renewalIntent ? [
            'reference_number' => $renewalIntent->reference_number,
            'status' => $renewalIntent->status,
            'amount' => (float) $renewalIntent->amount,
            'billing_cycle' => $renewalIntent->billing_cycle,
        ] : null;

        $eligible = (bool) (
            $subscription
            && !$subscription->isSuspended()
            && $subscription->plan
            && !$subscription->plan->isFree()
            && ($due || $isLapsed)
        );

        return [
            'pending' => $pending,
            'price' => $subscription?->billedPrice(),
            'billing_interval' => $subscription?->billing_interval,
            'threshold_days' => $thresholdDays,
            'days_until_expiry' => $daysUntilExpiry,
            'due' => $due,
            'active_paid' => $activePaid,
            'is_lapsed' => $isLapsed,
            // Authoritative backend eligibility for showing/permitting the renewal action.
            'eligible' => $eligible,
            // A valid renewal request is already awaiting payment/review.
            'blocked_by_pending' => $pending !== null,
            // Final gate the UI must follow: eligible and no duplicate pending renewal.
            'can_renew' => $eligible && $pending === null,
        ];
    }

    private function cancellationPayload(SubscriptionCancellationRequest $request, ?Subscription $subscription = null): array
    {
        $effectiveAt = null;
        if ($request->status === SubscriptionCancellationRequest::STATUS_APPROVED && $subscription) {
            // Access continues until the paid period ends (cancellation is not immediate).
            $effectiveAt = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at->toDateString()
                : now()->toDateString();
        }

        return [
            'id' => $request->id,
            'reason' => $request->reason,
            'reason_label' => $request->reasonLabel(),
            'comment' => $request->comment,
            'status' => $request->status,
            'created_at' => $request->created_at?->toDateTimeString(),
            'review_note' => $request->review_note,
            'reviewed_at' => $request->reviewed_at?->toDateTimeString(),
            'effective_at' => $effectiveAt,
        ];
    }

    private function paymentFormContext(?Subscription $subscription, ?Plan $targetPlan, ?PaymentIntent $intent): array
    {
        $now = now();

        return [
            'timezone' => config('app.timezone'),
            'defaultTransferDate' => $now->format('Y-m-d'),
            'defaultTransferTime' => $now->format('H:i'),
            'paymentReasons' => collect(\App\Models\PaymentIntent::PAYMENT_REASONS)
                ->map(fn ($meta, $key) => ['value' => $key, 'label' => $meta['label']])
                ->values(),
            'suggestedReason' => $this->suggestedPaymentReason($subscription, $targetPlan, $intent),
        ];
    }

    private function suggestedPaymentReason(?Subscription $subscription, ?Plan $targetPlan, ?PaymentIntent $intent): string
    {
        if ($intent) {
            $meta = $intent->metadata ?? [];
            $type = $meta['type'] ?? null;
            $source = $meta['source'] ?? null;

            if ($type === SubscriptionPlanChangeService::TYPE_PLAN_UPGRADE || $source === 'merchant_upgrade') {
                return 'plan_upgrade';
            }
            if ($source === 'merchant_renewal') {
                return 'subscription_renewal';
            }
            if (!empty($meta['trial_conversion']) || $source === 'merchant_trial_conversion') {
                return 'new_subscription';
            }
        }

        $currentPlan = $subscription?->plan;

        if (!$currentPlan || $currentPlan->isFree()) {
            return 'new_subscription';
        }

        if ($targetPlan && $targetPlan->id !== $currentPlan->id) {
            $interval = $subscription?->billing_interval ?? 'monthly';
            $currentPrice = (float) ($currentPlan->getPriceForInterval($interval) ?? 0);
            $targetPrice = (float) ($targetPlan->getPriceForInterval($interval) ?? 0);

            return $targetPrice > $currentPrice ? 'plan_upgrade' : 'plan_change';
        }

        return 'subscription_renewal';
    }
}
