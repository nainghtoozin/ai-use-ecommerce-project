import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';
import { Clock, CalendarDays, ShieldAlert, X } from 'lucide-react';

const TONES = {
    info: {
        wrapper: 'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-900/40',
        icon: 'text-blue-600',
        text: 'text-blue-800 dark:text-blue-200',
        button: 'text-blue-700 border-blue-300 hover:bg-blue-100 dark:text-blue-200 dark:border-blue-800 dark:hover:bg-blue-900/40',
        Icon: Clock,
    },
    warning: {
        wrapper: 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-900/40',
        icon: 'text-amber-600',
        text: 'text-amber-800 dark:text-amber-200',
        button: 'text-amber-700 border-amber-300 hover:bg-amber-100 dark:text-amber-200 dark:border-amber-800 dark:hover:bg-amber-900/40',
        Icon: CalendarDays,
    },
    danger: {
        wrapper: 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-900/40',
        icon: 'text-red-600',
        text: 'text-red-800 dark:text-red-200',
        button: 'text-red-700 border-red-300 hover:bg-red-100 dark:text-red-200 dark:border-red-800 dark:hover:bg-red-900/40',
        Icon: ShieldAlert,
    },
};

function storageKeyFor(notice) {
    if (!notice?.dismissible || typeof window === 'undefined') return null;
    const today = new Date().toISOString().slice(0, 10);
    return `subscription_notice_${notice.key}_${today}`;
}

export default function SubscriptionNotice() {
    const { subscription_notice: notice } = usePage().props;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(false);
    }, [notice?.key, notice?.title, notice?.message]);

    if (!notice) return null;

    const storageKey = storageKeyFor(notice);
    const dismissedToday = storageKey && typeof window !== 'undefined'
        ? window.localStorage.getItem(storageKey) === '1'
        : false;

    if (dismissed || dismissedToday) return null;

    const tone = TONES[notice.severity] || TONES.info;
    const Icon = tone.Icon;

    const dismiss = () => {
        setDismissed(true);
        if (storageKey) window.localStorage.setItem(storageKey, '1');
    };

    const runAction = () => {
        if (!notice.action) return;
        router.get(adminUrl(notice.action.path));
    };

    return (
        <div className={`border-b ${tone.wrapper}`} role="status" aria-live="polite">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <div className={`flex items-start sm:items-center gap-2 text-sm flex-1 min-w-0 ${tone.text}`}>
                    <Icon className={`w-4 h-4 flex-shrink-0 mt-0.5 sm:mt-0 ${tone.icon}`} />
                    <span className="min-w-0">
                        <strong className="font-semibold">{notice.title}.</strong> {notice.message}
                    </span>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                    {notice.action && (
                        <button
                            type="button"
                            onClick={runAction}
                            className={`px-3 py-1 text-xs font-medium rounded-lg bg-white dark:bg-gray-900 border transition-colors whitespace-nowrap ${tone.button}`}
                        >
                            {notice.action.label}
                        </button>
                    )}
                    {notice.dismissible && (
                        <button
                            type="button"
                            onClick={dismiss}
                            aria-label="Dismiss notice"
                            className={`p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors ${tone.text}`}
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
