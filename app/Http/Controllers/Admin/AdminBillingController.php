<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionAuditLog;
use App\Services\FeatureGate;
use App\Services\SubscriptionAuditService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBillingController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = auth()->user()->tenant;

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

        $tenant = auth()->user()->tenant;

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

        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        $subscription = $tenant->subscription;
        $currentPlan = $subscription?->plan;

        $allPlans = Plan::active()->ordered()->get();
        $allFeatureDefs = FeatureGate::getAllFeatureDefinitions();

        $plans = $allPlans->map(function ($plan) use ($currentPlan) {
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
            ];
        });

        $usage = $subscription ? SubscriptionLimitService::for($tenant)->getAllLimits() : [];

        return Inertia::render('Admin/Billing/UpgradePlan', [
            'currentPlan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
            ] : null,
            'plans' => $plans,
            'usage' => $usage,
            'allFeatureDefs' => $allFeatureDefs,
        ]);
    }

    public function paymentHistory()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Billing/PaymentHistory');
    }

    public function settings()
    {
        if (!auth()->user()->can('billing.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Billing/Settings');
    }

    public function renew(Request $request)
    {
        if (!auth()->user()->can('billing.renew')) {
            abort(403, 'Unauthorized');
        }

        $tenant = auth()->user()->tenant;

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
