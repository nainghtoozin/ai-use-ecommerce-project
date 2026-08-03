import { Head, usePage } from '@inertiajs/react';
import PlatformGuestLayout from '@/Layouts/PlatformGuestLayout';
import {
    CheckCircle2,
    Store,
    ExternalLink,
    ArrowRight,
    Calendar,
    CreditCard,
    Sparkles,
} from 'lucide-react';

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export default function StoreSuccess() {
    const { store, subscription } = usePage().props;

    const trialEndDate = formatDate(subscription?.trial_ends_at);
    const expiryDate = formatDate(subscription?.expires_at);

    return (
        <PlatformGuestLayout>
            <Head title="Welcome to Your Store!" />

            <div className="text-center">
                <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-5">
                    <CheckCircle2 className="w-8 h-8 text-green-600 dark:text-green-400" />
                </div>

                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                    Welcome, {store.name}!
                </h1>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Your store has been created and is ready to go.
                </p>

                <div className="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-left space-y-3 mb-6">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Store Name
                        </span>
                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {store.name}
                        </span>
                    </div>

                    <div className="border-t border-gray-200 dark:border-gray-700 pt-3 flex items-center justify-between">
                        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Store URL
                        </span>
                        <a
                            href={store.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-mono flex items-center gap-1"
                        >
                            {store.url} <ExternalLink className="w-3 h-3" />
                        </a>
                    </div>

                    {subscription && (
                        <>
                            <div className="border-t border-gray-200 dark:border-gray-700 pt-3 flex items-center justify-between">
                                <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1">
                                    <CreditCard className="w-3 h-3" /> Current Plan
                                </span>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {subscription.plan_name}
                                    </span>
                                    {subscription.is_free && (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            Free
                                        </span>
                                    )}
                                    {subscription.on_trial && (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                            <Sparkles className="w-3 h-3 mr-1" /> Trial
                                        </span>
                                    )}
                                </div>
                            </div>

                            {subscription.on_trial && trialEndDate && (
                                <div className="border-t border-gray-200 dark:border-gray-700 pt-3 flex items-center justify-between">
                                    <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1">
                                        <Calendar className="w-3 h-3" /> Trial Expires
                                    </span>
                                    <div className="text-right">
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {trialEndDate}
                                        </span>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            {subscription.days_left_in_trial} day{subscription.days_left_in_trial !== 1 ? 's' : ''} remaining
                                        </span>
                                    </div>
                                </div>
                            )}

                            {!subscription.on_trial && !subscription.is_free && expiryDate && (
                                <div className="border-t border-gray-200 dark:border-gray-700 pt-3 flex items-center justify-between">
                                    <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1">
                                        <Calendar className="w-3 h-3" /> Next Billing
                                    </span>
                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {expiryDate}
                                    </span>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <div className="space-y-3">
                    <a
                        href={store.admin_url}
                        className="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                    >
                        Go to Dashboard <ArrowRight className="w-4 h-4" />
                    </a>

                    <a
                        href={store.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Visit Store <ExternalLink className="w-4 h-4" />
                    </a>
                </div>
            </div>
        </PlatformGuestLayout>
    );
}
