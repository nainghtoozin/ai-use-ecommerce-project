import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import {
    CheckCircle2,
    Circle,
    ChevronDown,
    ChevronUp,
    X,
    Sparkles,
    ArrowRight,
    Plus,
    Package,
    Tag,
    CreditCard,
    Truck,
    Settings,
    Users,
    ExternalLink,
    Calendar,
    Rocket,
} from 'lucide-react';

const quickActions = [
    { label: 'Add Product', icon: Plus, link: '/admin/products/create', color: 'bg-blue-500' },
    { label: 'Add Category', icon: Tag, link: '/admin/categories/create', color: 'bg-violet-500' },
    { label: 'Configure Payment', icon: CreditCard, link: '/admin/payment-methods', color: 'bg-emerald-500' },
    { label: 'Manage Inventory', icon: Package, link: '/admin/inventory', color: 'bg-amber-500' },
    { label: 'View Orders', icon: Package, link: '/admin/orders', color: 'bg-indigo-500' },
];

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function ProgressBar({ percentage }) {
    const getColor = () => {
        if (percentage < 25) return 'bg-red-500';
        if (percentage < 50) return 'bg-amber-500';
        if (percentage < 75) return 'bg-blue-500';
        return 'bg-emerald-500';
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">Setup Progress</span>
                <span className="text-xs font-bold text-gray-700 dark:text-gray-300">{percentage}%</span>
            </div>
            <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${getColor()}`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

function ChecklistItem({ item, index }) {
    const Icon = item.completed ? CheckCircle2 : Circle;
    const iconClass = item.completed
        ? 'text-emerald-500'
        : item.coming_soon
            ? 'text-gray-300 dark:text-gray-600'
            : 'text-gray-400 dark:text-gray-500';

    const content = (
        <div className={`flex items-center gap-3 py-2.5 px-3 rounded-lg transition-colors ${
            item.completed
                ? 'bg-emerald-50/50 dark:bg-emerald-900/10'
                : item.coming_soon
                    ? 'opacity-60'
                    : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer'
        }`}>
            <Icon className={`w-5 h-5 flex-shrink-0 ${iconClass}`} />
            <span className={`text-sm flex-1 ${
                item.completed
                    ? 'text-gray-500 dark:text-gray-400 line-through'
                    : 'text-gray-700 dark:text-gray-300'
            }`}>
                {item.label}
            </span>
            {item.coming_soon && (
                <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                    Coming Soon
                </span>
            )}
            {item.completed && (
                <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                    Done
                </span>
            )}
        </div>
    );

    if (item.link && !item.completed && !item.coming_soon) {
        return <Link href={adminUrl(item.link)}>{content}</Link>;
    }

    return content;
}

export default function OnboardingChecklist({ onboarding }) {
    const [expanded, setExpanded] = useState(false);
    const [dismissing, setDismissing] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    if (!onboarding || dismissed) return null;

    const { items, percentage, merchant_name, store_name, subscription } = onboarding;
    const trialEndDate = formatDate(subscription?.trial_ends_at);

    const handleDismiss = () => {
        setDismissing(true);
        setDismissed(true);
        router.post(adminUrl('/admin/onboarding/dismiss'), {}, {
            preserveState: true,
            onFinish: () => setDismissing(false),
        });
    };

    return (
        <div className="rounded-xl border border-indigo-100 dark:border-indigo-900/30 bg-gradient-to-br from-indigo-50 via-white to-violet-50 dark:from-indigo-950/30 dark:via-gray-900 dark:to-violet-950/20 overflow-hidden">
            {/* Header */}
            <div className="p-5 pb-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                            <Rocket className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Welcome, {merchant_name}!
                            </h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Let's get your store ready to sell.
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={handleDismiss}
                        disabled={dismissing}
                        className="p-1 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        title="Dismiss onboarding"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Store Info */}
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <span className="w-2 h-2 rounded-full bg-indigo-500" />
                        {store_name}
                    </div>
                    {subscription && (
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300">
                            {subscription.on_trial ? (
                                <Sparkles className="w-3 h-3 text-amber-500" />
                            ) : (
                                <CreditCard className="w-3 h-3 text-gray-400" />
                            )}
                            {subscription.plan_name}
                            {subscription.on_trial && (
                                <span className="text-amber-600 dark:text-amber-400 ml-1">
                                    ({subscription.days_left_in_trial}d left)
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Progress */}
            <div className="px-5 pb-3">
                <ProgressBar percentage={percentage} />
            </div>

            {/* Collapsible Checklist */}
            <div className="px-5 pb-2">
                <button
                    onClick={() => setExpanded(!expanded)}
                    className="flex items-center justify-between w-full py-2 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                >
                    <span>Setup Checklist</span>
                    {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </button>
            </div>

            {expanded && (
                <div className="px-5 pb-4 space-y-0.5">
                    {Object.entries(items).map(([key, item], index) => (
                        <ChecklistItem key={key} item={item} index={index} />
                    ))}
                </div>
            )}

            {/* Quick Actions */}
            <div className="px-5 pb-5 pt-2 border-t border-indigo-100 dark:border-indigo-900/30">
                <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Quick Actions</p>
                <div className="flex flex-wrap gap-2">
                    {quickActions.map((action) => {
                        const Icon = action.icon;
                        return (
                            <Link
                                key={action.label}
                                href={adminUrl(action.link)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300 hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-sm transition-all"
                            >
                                <Icon className="w-3.5 h-3.5" />
                                {action.label}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
