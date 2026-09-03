<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\WebsiteInfo;
use App\Models\User;

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

    public function getSetupGuideData(Tenant $tenant, Account $user): array
    {
        $subscription = $tenant->subscription;
        $plan = $subscription?->plan;
        $items = $this->checkItems($tenant);
        $completedCount = collect($items)->filter(fn($item) => $item['completed'])->count();
        $totalCount = count($items);
        $percentage = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

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
            'help_sections' => $this->getHelpSections(),
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
        $staffCount = User::where('tenant_id', $tenant->id)->count();

        return [
            'business_profile' => [
                'label' => 'Complete Business Profile',
                'description' => 'Add your store contact details, address, and business information so customers can reach you.',
                'completed' => $hasBusinessProfile,
                'link' => '/admin/settings',
                'category' => 'store',
            ],
            'store_logo' => [
                'label' => 'Upload Store Logo',
                'description' => 'Add your brand logo to appear on your storefront and in communications.',
                'completed' => $hasLogo,
                'link' => '/admin/settings',
                'category' => 'store',
            ],
            'payment_method' => [
                'label' => 'Configure Payment Method',
                'description' => 'Set up how customers will pay for their orders (e.g., bank transfer, COD).',
                'completed' => $hasPaymentMethod,
                'link' => '/admin/payment-methods',
                'category' => 'payments',
            ],
            'delivery_settings' => [
                'label' => 'Configure Delivery Settings',
                'description' => 'Set your shipping fees and free shipping threshold for orders.',
                'completed' => $hasDeliverySettings,
                'link' => '/admin/settings',
                'category' => 'delivery',
            ],
            'first_category' => [
                'label' => 'Add First Category',
                'description' => 'Create categories to organize your products (e.g., Electronics, Clothing).',
                'completed' => $hasCategory,
                'link' => '/admin/categories/create',
                'category' => 'products',
            ],
            'first_product' => [
                'label' => 'Add First Product',
                'description' => 'Add products to your catalog with details, price, and images.',
                'completed' => $hasProduct,
                'link' => '/admin/products/create',
                'category' => 'products',
            ],
            'website_settings' => [
                'label' => 'Review Website Settings',
                'description' => 'Configure your site title, description, and SEO settings.',
                'completed' => $hasWebsiteSettings,
                'link' => '/admin/settings',
                'category' => 'store',
            ],
            'invite_staff' => [
                'label' => 'Invite Team Members',
                'description' => "You have {$staffCount} team member(s). Invite more staff to help manage your store.",
                'completed' => $staffCount > 1,
                'link' => '/admin/team',
                'category' => 'team',
            ],
        ];
    }

    private function getHelpSections(): array
    {
        return [
            [
                'title' => 'Store Setup',
                'icon' => 'Store',
                'description' => 'Configure your store name, logo, contact info, and branding.',
                'links' => [
                    ['label' => 'General Settings', 'href' => '/admin/settings'],
                    ['label' => 'Store Logo & Branding', 'href' => '/admin/settings'],
                    ['label' => 'Contact Information', 'href' => '/admin/settings'],
                    ['label' => 'Social Media Links', 'href' => '/admin/settings'],
                ],
            ],
            [
                'title' => 'Products',
                'icon' => 'Package',
                'description' => 'Manage your product catalog, categories, brands, and inventory.',
                'links' => [
                    ['label' => 'Add Products', 'href' => '/admin/products/create'],
                    ['label' => 'Manage Products', 'href' => '/admin/products'],
                    ['label' => 'Categories', 'href' => '/admin/categories'],
                    ['label' => 'Brands', 'href' => '/admin/brands'],
                    ['label' => 'Inventory', 'href' => '/admin/inventory/dashboard'],
                    ['label' => 'Import Products', 'href' => '/admin/products/import/history'],
                ],
            ],
            [
                'title' => 'Orders',
                'icon' => 'ShoppingCart',
                'description' => 'View, process, and manage customer orders.',
                'links' => [
                    ['label' => 'All Orders', 'href' => '/admin/orders'],
                    ['label' => 'Sales Report', 'href' => '/admin/reports/sales'],
                    ['label' => 'Payment Report', 'href' => '/admin/reports/payments'],
                ],
            ],
            [
                'title' => 'Customers',
                'icon' => 'Users',
                'description' => 'Manage your team members and view customer activity.',
                'links' => [
                    ['label' => 'Team Members', 'href' => '/admin/team'],
                    ['label' => 'All Users', 'href' => '/admin/users'],
                    ['label' => 'Activity Log', 'href' => '/admin/activity-logs'],
                ],
            ],
            [
                'title' => 'Payments',
                'icon' => 'CreditCard',
                'description' => 'Configure payment methods and view payment history.',
                'links' => [
                    ['label' => 'Payment Methods', 'href' => '/admin/payment-methods'],
                    ['label' => 'Invoices', 'href' => '/admin/billing/invoices'],
                    ['label' => 'Payment History', 'href' => '/admin/billing/payment-history'],
                ],
            ],
            [
                'title' => 'Delivery',
                'icon' => 'Truck',
                'description' => 'Set up shipping fees, free shipping thresholds, and delivery options.',
                'links' => [
                    ['label' => 'Shipping Settings', 'href' => '/admin/website-info/edit'],
                    ['label' => 'Locations', 'href' => '/admin/cities'],
                ],
            ],
            [
                'title' => 'Storefront',
                'icon' => 'LayoutTemplate',
                'description' => 'Customize your store\'s look and feel.',
                'links' => [
                    ['label' => 'Storefront Settings', 'href' => '/admin/storefront'],
                    ['label' => 'Homepage', 'href' => '/admin/storefront/homepage'],
                    ['label' => 'Navigation', 'href' => '/admin/storefront/navigation'],
                    ['label' => 'Media Gallery', 'href' => '/admin/storefront/media'],
                    ['label' => 'Promotions', 'href' => '/admin/storefront/promotions'],
                    ['label' => 'Banners', 'href' => '/admin/banners'],
                ],
            ],
            [
                'title' => 'Promotions',
                'icon' => 'Tag',
                'description' => 'Run campaigns, discounts, and special offers.',
                'links' => [
                    ['label' => 'Coupons', 'href' => '/admin/coupons'],
                    ['label' => 'Promotions', 'href' => '/admin/promotions'],
                    ['label' => 'Banners', 'href' => '/admin/banners'],
                    ['label' => 'Promotion Reports', 'href' => '/admin/promotions/reports'],
                ],
            ],
            [
                'title' => 'Notifications',
                'icon' => 'Bell',
                'description' => 'Set up email and Telegram notifications.',
                'links' => [
                    ['label' => 'Notification Settings', 'href' => '/admin/settings/notifications'],
                    ['label' => 'Telegram Integration', 'href' => '/admin/settings/telegram-integration'],
                ],
            ],
            [
                'title' => 'Billing',
                'icon' => 'Receipt',
                'description' => 'Manage your subscription and billing.',
                'links' => [
                    ['label' => 'Subscription', 'href' => '/admin/billing/subscription'],
                    ['label' => 'Upgrade Plan', 'href' => '/admin/billing/upgrade'],
                    ['label' => 'Invoices', 'href' => '/admin/billing/invoices'],
                ],
            ],
        ];
    }
}
