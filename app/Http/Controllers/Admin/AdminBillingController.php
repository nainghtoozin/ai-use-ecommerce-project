<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Data\Currency;
use App\Enums\CurrencyCode;
use App\Models\BillingPaymentMethod;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionAuditLog;
use App\Models\Tenant;
use App\Services\FeatureGate;
use App\Services\ImageService;
use App\Services\Payment\Platform\CheckoutService;
use App\Services\Payment\Platform\ManualPaymentService;
use App\Services\Payment\Platform\PaymentEvidenceService;
use App\Services\Payment\Platform\PaymentIntentService;
use App\Services\SubscriptionAuditService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
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

        $subscription = $tenant->subscription;
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
                'next_billing_date' => $subscription->expires_at?->isFuture()
                    ? $subscription->expires_at->toDateString()
                    : ($subscription->plan && !$subscription->plan->isFree()
                        ? $subscription->plan->calculateExpiryDate(now(), $subscription->billing_interval ?? 'monthly')?->toDateString()
                        : null),
            ] : null,
            'usage' => $usage,
            'plans' => $plans,
            'featureCategories' => $featureCategories,
            'allFeatureDefs' => $allFeatureDefs,
            'auditLogs' => $auditLogs,
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

    public function checkout(string $planSlug)
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
            return redirect()->route('storefront.admin.billing.upgrade', ['store_slug' => $tenant->slug])
                ->with('error', 'The selected plan is not available.');
        }

        $subscription = $tenant->subscription;

        if ($subscription && $subscription->plan && $subscription->plan->id === $plan->id) {
            return redirect()->route('storefront.admin.billing', ['store_slug' => $tenant->slug])
                ->with('info', 'You are already on this plan.');
        }

        $currencyCode = CurrencyCode::tryFrom($tenant->websiteInfo?->currency_code ?? 'MMK') ?? CurrencyCode::MMK;
        $currency = Currency::fromEnum($currencyCode);

        $billingCycle = 'monthly';
        $amount = (float) ($plan->monthly_price ?? 0);
        $gateway = 'manual';

        try {
            $checkout = app(CheckoutService::class);
            $intent = $checkout->initiateCheckout(
                tenant: $tenant,
                plan: $plan,
                billingCycle: $billingCycle,
                amount: $amount,
                currency: $currency,
                gateway: $gateway,
                metadata: ['source' => 'merchant_upgrade']
            );

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

            return Inertia::render('Admin/Billing/Checkout', [
                'intent' => [
                    'id' => $intent->id,
                    'reference_number' => $intent->reference_number,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'status' => $intent->status,
                    'billing_cycle' => $intent->billing_cycle,
                    'expires_at' => $intent->expires_at?->toDateTimeString(),
                    'created_at' => $intent->created_at->toDateTimeString(),
                ],
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
            ]);
        } catch (\Exception $e) {
            return redirect()->route('storefront.admin.billing.upgrade', ['store_slug' => $tenant->slug])
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
        ]);
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
            'intent_reference' => ['required', 'string'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_account' => ['required', 'string', 'max:255'],
            'transaction_reference' => ['required', 'string', 'max:255'],
            'transferred_amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'evidence' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method_id' => ['nullable', 'exists:billing_payment_methods,id'],
        ]);

        $intentService = app(PaymentIntentService::class);
        $intent = $intentService->findByReferenceForTenant($tenant, $validated['intent_reference']);

        if (!$intent) {
            return redirect()->back()->with('error', 'Payment intent not found.');
        }

        if ($intent->status !== 'waiting_payment') {
            return redirect()->back()->with('error', 'This payment cannot be submitted in its current state.');
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
                    'payment_method_id' => $validated['payment_method_id'],
                    'uploaded_by' => 'merchant',
                ],
                senderName: $validated['sender_name'],
                senderAccount: $validated['sender_account'],
                transactionReference: $validated['transaction_reference'],
                transferredAmount: (float) $validated['transferred_amount'],
                transferDate: $validated['transfer_date'],
            );

            $manualPayment = app(ManualPaymentService::class);
            $manualPayment->confirmPayment($intent);

            $intent->refresh();

            return redirect()->route('storefront.admin.billing.payment', [
                'store_slug' => $tenant->slug,
                'intent' => $intent->reference_number,
                'submitted' => 'true',
            ])->with('success', 'Payment submitted successfully! Your payment is now awaiting review.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to submit payment. Please try again.');
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

        if ($subscription->isInGoodStanding()) {
            return redirect()->back()->with('error', 'Your subscription is already active.');
        }

        if ($subscription->isSuspended()) {
            return redirect()->back()->with('error', 'Your subscription has been suspended. Please contact support.');
        }

        if ($subscription->trial_ends_at && $subscription->onTrial()) {
            $settings = PlatformSetting::current();
            $trialRenewalBlocked = !$settings->allow_trial_renewal
                || $settings->max_trial_renewals <= 0
                || $subscription->trial_renewals_count >= $settings->max_trial_renewals;
            if ($trialRenewalBlocked) {
                return redirect()->back()->with(
                    'error',
                    'Trial renewal limit reached. Please upgrade to a paid plan to continue.'
                );
            }
        }

        $subscription->renewFromInterval('Self-service renewal by merchant.');

        // Only count as trial renewal if the subscription is still on trial
        if ($subscription->trial_ends_at && $subscription->onTrial()) {
            $subscription->increment('trial_renewals_count');
            SubscriptionAuditService::log($subscription, 'trial_renewed', [
                'reason' => 'Trial renewal via self-service.',
                'trial_renewals_count' => $subscription->trial_renewals_count,
            ]);
        }

        SubscriptionAuditService::log($subscription, 'renewed', [
            'reason' => 'Self-service renewal by merchant.',
        ]);

        return admin_redirect('admin.billing')
            ->with('success', 'Your subscription has been renewed!');
    }
}
