<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\WebsiteInfo;

class OnboardingService
{
    private const SETTINGS_KEY = 'onboarding_dismissed';

    public function getOnboardingData(Tenant $tenant, Account $user): ?array
    {
        if ($this->isDismissed($tenant)) {
            return null;
        }

        $items = $this->checkItems($tenant);
        $completedCount = collect($items)->filter(fn($item) => $item['completed'])->count();
        $totalCount = count($items);
        $percentage = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

        if ($percentage >= 100) {
            return null;
        }

        $subscription = $tenant->subscription;
        $plan = $subscription?->plan;

        return [
            'items' => $items,
            'percentage' => $percentage,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'merchant_name' => $user->name ?? $user->display_name ?? 'Merchant',
            'store_name' => $tenant->name,
            'subscription' => $subscription && $plan ? [
                'plan_name' => $plan->name,
                'status' => $subscription->status,
                'on_trial' => $subscription->onTrial(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'days_left_in_trial' => $subscription->daysLeftInTrial(),
                'is_free' => $plan->isFree(),
            ] : null,
        ];
    }

    public function dismiss(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        $settings[self::SETTINGS_KEY] = true;
        $tenant->update(['settings' => $settings]);
    }

    public function resetOnboarding(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        unset($settings[self::SETTINGS_KEY]);
        $tenant->update(['settings' => $settings]);
    }

    private function isDismissed(Tenant $tenant): bool
    {
        return ($tenant->settings[self::SETTINGS_KEY] ?? false) === true;
    }

    private function checkItems(Tenant $tenant): array
    {
        $websiteInfo = WebsiteInfo::getSettings();
        $hasLogo = !empty($websiteInfo->logo);
        $hasPaymentMethod = PaymentMethod::where('is_active', true)->exists();
        $hasDeliverySettings = !empty($websiteInfo->default_shipping_fee) || !empty($websiteInfo->free_shipping_threshold);
        $hasCategory = Category::exists();
        $hasProduct = Product::exists();
        $hasWebsiteSettings = !empty($websiteInfo->site_name) && !empty($websiteInfo->site_description);
        $hasBusinessProfile = !empty($websiteInfo->contact_email) && !empty($websiteInfo->phone) && !empty($websiteInfo->address);

        return [
            'business_profile' => [
                'label' => 'Complete Business Profile',
                'completed' => $hasBusinessProfile,
                'link' => '/admin/website-info/edit',
            ],
            'store_logo' => [
                'label' => 'Upload Store Logo',
                'completed' => $hasLogo,
                'link' => '/admin/website-info/edit',
            ],
            'payment_method' => [
                'label' => 'Configure Payment Method',
                'completed' => $hasPaymentMethod,
                'link' => '/admin/payment-methods',
            ],
            'delivery_settings' => [
                'label' => 'Configure Delivery Settings',
                'completed' => $hasDeliverySettings,
                'link' => '/admin/website-info/edit',
            ],
            'first_category' => [
                'label' => 'Add First Category',
                'completed' => $hasCategory,
                'link' => '/admin/categories/create',
            ],
            'first_product' => [
                'label' => 'Add First Product',
                'completed' => $hasProduct,
                'link' => '/admin/products/create',
            ],
            'website_settings' => [
                'label' => 'Review Website Settings',
                'completed' => $hasWebsiteSettings,
                'link' => '/admin/website-info/edit',
            ],
            'invite_staff' => [
                'label' => 'Invite Staff',
                'completed' => false,
                'link' => null,
                'coming_soon' => true,
            ],
        ];
    }
}
