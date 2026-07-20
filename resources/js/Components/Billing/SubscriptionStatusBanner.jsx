import { usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import { AlertTriangle, Clock, Zap, Calendar, ShieldAlert, RefreshCw } from 'lucide-react';

export default function SubscriptionStatusBanner() {
    const { auth, tenant } = usePage().props;
    const user = auth?.user;
    if (!user || user.is_super_admin) return null;

    const sub = user.subscription;
    if (!sub) return null;

    const status = sub.status;
    const now = new Date();
    const expiresAt = sub.expires_at ? new Date(sub.expires_at) : null;
    const trialEndsAt = sub.trial_ends_at ? new Date(sub.trial_ends_at) : null;
    const locked = tenant?.locked_at || user.subscription_expired;

    const daysUntil = expiresAt
        ? Math.ceil((expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
        : null;

    const trialDaysRemaining = trialEndsAt
        ? Math.ceil((trialEndsAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
        : null;

    const graceDaysRemaining = status === 'past_due' && expiresAt
        ? 7 - Math.abs(Math.ceil((now.getTime() - expiresAt.getTime()) / (1000 * 60 * 60 * 24)))
        : 0;

    const handleRenew = () => {
        router.post(adminUrl('/admin/billing/renew'), {}, { preserveScroll: true });
    };

    const graceBanner = status === 'past_due' && graceDaysRemaining > 0 && (
        <div className="bg-amber-50 border-b border-amber-200 px-4 py-2.5">
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-sm text-amber-800">
                    <AlertTriangle className="w-4 h-4 text-amber-500 flex-shrink-0" />
                    <span>
                        <strong>Grace Period:</strong> Your subscription expired. Renew within <strong>{graceDaysRemaining} day{graceDaysRemaining !== 1 ? 's' : ''}</strong> to avoid service interruption.
                    </span>
                </div>
                <button onClick={handleRenew} className="px-3 py-1 text-xs font-medium text-amber-700 bg-white border border-amber-300 rounded-lg hover:bg-amber-100 transition-colors flex-shrink-0">
                    Renew Now
                </button>
            </div>
        </div>
    );

    const expiredBanner = status === 'expired' && (
        <div className="bg-red-50 border-b border-red-200 px-4 py-2.5">
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-sm text-red-800">
                    <ShieldAlert className="w-4 h-4 text-red-500 flex-shrink-0" />
                    <span>
                        <strong>Subscription Expired.</strong> Your store is now restricted. Renew to restore full access.
                    </span>
                </div>
                <button onClick={handleRenew} className="px-3 py-1 text-xs font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-100 transition-colors flex-shrink-0">
                    Renew Now
                </button>
            </div>
        </div>
    );

    const suspendedBanner = status === 'suspended' && (
        <div className="bg-red-100 border-b border-red-300 px-4 py-2.5">
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-sm text-red-900 font-medium">
                    <ShieldAlert className="w-4 h-4 text-red-600 flex-shrink-0" />
                    <span>Your store has been suspended. Please contact support to restore service.</span>
                </div>
            </div>
        </div>
    );

    const trialBanner = status === 'trialing' && trialDaysRemaining !== null && trialDaysRemaining <= 7 && (
        <div className={`border-b px-4 py-2.5 ${trialDaysRemaining <= 3 ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-sm">
                    <Clock className={`w-4 h-4 flex-shrink-0 ${trialDaysRemaining <= 3 ? 'text-amber-500' : 'text-blue-500'}`} />
                    <span className={trialDaysRemaining <= 3 ? 'text-amber-800' : 'text-blue-800'}>
                        <strong>Trial ends {trialDaysRemaining === 0 ? 'today' : `in ${trialDaysRemaining} day${trialDaysRemaining !== 1 ? 's' : ''}`}</strong>
                        {trialDaysRemaining <= 3 ? ' — Choose a plan to continue using all features.' : ''}
                    </span>
                </div>
                <button
                    onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                    className={`px-3 py-1 text-xs font-medium rounded-lg transition-colors flex-shrink-0 ${
                        trialDaysRemaining <= 3
                            ? 'text-amber-700 bg-white border border-amber-300 hover:bg-amber-100'
                            : 'text-blue-700 bg-white border border-blue-300 hover:bg-blue-100'
                    }`}
                >
                    Upgrade Now
                </button>
            </div>
        </div>
    );

    const expiringBanner = daysUntil !== null && daysUntil <= 14 && daysUntil > 0 && status === 'active' && (
        <div className={`border-b px-4 py-2.5 ${daysUntil <= 3 ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200'}`}>
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-sm">
                    <Calendar className={`w-4 h-4 flex-shrink-0 ${daysUntil <= 3 ? 'text-amber-500' : 'text-blue-500'}`} />
                    <span className={daysUntil <= 3 ? 'text-amber-800' : 'text-blue-800'}>
                        <strong>Subscription renews in {daysUntil} day{daysUntil !== 1 ? 's' : ''}</strong>
                        {daysUntil <= 3 ? ' — Renew now to avoid interruption.' : ''}
                    </span>
                </div>
                <button
                    onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                    className={`px-3 py-1 text-xs font-medium rounded-lg transition-colors flex-shrink-0 ${
                        daysUntil <= 3
                            ? 'text-amber-700 bg-white border border-amber-300 hover:bg-amber-100'
                            : 'text-blue-700 bg-white border border-blue-300 hover:bg-blue-100'
                    }`}
                >
                    Renew Now
                </button>
            </div>
        </div>
    );

    const lockedBanner = locked && !['expired', 'past_due', 'suspended'].includes(status) && (
        <div className="bg-amber-50 border-b border-amber-200 px-4 py-2.5">
            <div className="max-w-7xl mx-auto flex items-center gap-2 text-sm text-amber-800">
                <RefreshCw className="w-4 h-4 text-amber-500 flex-shrink-0" />
                <span>Your store is currently locked. Renew your subscription or contact support to restore access.</span>
            </div>
        </div>
    );

    return (
        <>
            {suspendedBanner}
            {expiredBanner}
            {graceBanner}
            {trialBanner}
            {expiringBanner}
            {lockedBanner}
        </>
    );
}
