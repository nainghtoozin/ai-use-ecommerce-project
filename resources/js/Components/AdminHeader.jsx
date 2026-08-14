import { Link, usePage, router } from '@inertiajs/react';
import NotificationBell from '@/Components/NotificationBell';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { adminUrl } from '@/Utils/adminUrl';
import { useTranslation } from '@/Utils/useTranslation';

export default function AdminHeader() {
    const { props, url } = usePage();
    const { auth, platform_setting } = props;
    const { t } = useTranslation();
    const isImpersonating = auth?.user?.is_impersonating;
    const impersonatorName = auth?.user?.impersonator_name;

    const storeSlug = url?.match(/^\/store\/([^/]+)\//)?.[1] ?? null;

    const stopImpersonating = () => {
        router.post('/superadmin/impersonate/leave', {}, {
            preserveScroll: true,
        });
    };

    const getPageTitle = () => {
        const path = url || '/';
        if (path.includes('dashboard')) return 'Dashboard';
        if (path.includes('products')) return 'Products';
        if (path.includes('categories')) return 'Categories';
        if (path.includes('orders')) return 'Orders';
        if (path.includes('promotions')) return 'Promotions';
        if (path.includes('payment-methods')) return 'Payment Methods';
        if (path.includes('units')) return 'Units';
        if (path.includes('cities')) return 'Cities';
        if (path.includes('townships')) return 'Townships';
        if (path.includes('website-info')) return 'Website Info';
        if (path.includes('telegram-integration')) return 'Telegram Integration';
        if (path.includes('settings')) return 'Settings';
        return 'Admin Panel';
    };

    const isSuperAdmin = auth?.user?.is_superadmin;
    const subscription = auth?.user?.subscription;
    const showSubscriptionStatus = !isSuperAdmin && auth?.user?.is_owner && url?.includes('/dashboard') && subscription;
    const subscriptionDays = Number(subscription?.days_until_expiry);
    const subscriptionState = subscription?.status === 'expired'
        ? 'expired'
        : subscription?.expires_at === null || subscription?.expires_at === undefined
            ? 'unlimited'
            : !Number.isFinite(subscriptionDays) || subscriptionDays <= 0
            ? 'expired'
            : subscriptionDays <= 7 ? 'expiring' : 'healthy';
    const subscriptionStatusClasses = {
        healthy: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        expiring: 'bg-amber-50 text-amber-700 border-amber-200',
        expired: 'bg-red-50 text-red-700 border-red-200',
        unlimited: 'bg-gray-50 text-gray-600 border-gray-200',
    };
    const subscriptionDetail = subscriptionState === 'unlimited'
        ? t('dashboard.subscription_no_expiry')
        : subscriptionState === 'expired'
            ? t('dashboard.subscription_expired_label')
            : t('dashboard.subscription_days_left', { days: subscriptionDays });
    const subtitle = isSuperAdmin
        ? (platform_setting?.site_name ? `${platform_setting.site_name} — SuperAdmin` : 'SuperAdmin Panel')
        : 'Manage your store';

    return (
        <>
            {isImpersonating && (
                <div className="bg-amber-500 text-white px-4 py-2 text-sm flex items-center justify-between sticky top-0 z-30">
                    <div className="flex items-center gap-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>You are impersonating <strong>{auth?.user?.name}</strong> ({auth?.user?.email})</span>
                        {impersonatorName && (
                            <span className="text-amber-100 text-xs ml-1">— by {impersonatorName}</span>
                        )}
                    </div>
                    <button
                        onClick={stopImpersonating}
                        className="px-3 py-1 bg-white dark:bg-gray-900 text-amber-700 rounded text-xs font-medium hover:bg-amber-50 transition-colors"
                    >
                        Back To SuperAdmin
                    </button>
                </div>
            )}
            <header className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 h-14 lg:h-16 flex items-center px-4 lg:px-6 sticky top-0 z-20 shadow-sm">
            <div className="flex items-center justify-between w-full">
                <div className="flex items-center gap-3">
                    <div className="lg:hidden w-10"></div>
                    <div>
                        <h1 className="text-base lg:text-lg font-semibold text-gray-900 dark:text-gray-100">{getPageTitle()}</h1>
                        <p className="text-xs text-gray-500 dark:text-gray-400 hidden lg:block">{subtitle}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 lg:gap-3">
                    {storeSlug && (
                        <Link
                            href={`/store/${storeSlug}`}
                            className="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                        >
                            <i className="bi bi-eye"></i>
                            View Store
                        </Link>
                    )}
                    {showSubscriptionStatus && (
                        <Link
                            href={adminUrl('/admin/billing')}
                            className={`hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors ${subscriptionStatusClasses[subscriptionState]}`}
                            title={subscriptionState === 'expiring' ? t('dashboard.subscription_expiring_soon') : t('dashboard.subscription')}
                        >
                            <span className="hidden lg:inline">{t('dashboard.subscription')}:</span>
                            <span>{subscription.plan_name || t('dashboard.subscription')}</span>
                            <span>·</span>
                            <span>{subscriptionDetail}</span>
                        </Link>
                    )}
                    <div className="hidden md:flex items-center gap-2 px-3 py-1.5 bg-gray-50 dark:bg-gray-950 rounded-lg">
                        <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span className="text-xs text-gray-600 dark:text-gray-400">Online</span>
                    </div>
                    <NotificationBell isAdmin={true} />
                    <LanguageSwitcher />
                    <div className="flex items-center gap-2 border-l border-gray-200 dark:border-gray-800 pl-2 lg:pl-3">
                        <div className="w-7 lg:w-8 h-7 lg:h-8 text-white rounded-lg flex items-center justify-center text-xs font-bold shadow-sm" style={{ background: 'linear-gradient(135deg, var(--theme-color, #3B82F6), color-mix(in srgb, var(--theme-color, #3B82F6) 80%, black))' }}>
                            {auth?.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        <div className="hidden lg:block">
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-300 block">{auth?.user?.name}</span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">{auth?.user?.role_label}</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>
            </>
    );
}
