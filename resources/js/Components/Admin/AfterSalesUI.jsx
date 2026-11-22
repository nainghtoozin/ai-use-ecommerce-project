import { Link } from '@inertiajs/react';

export const STATUS_STYLES = {
    requested: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-blue-50 text-blue-700 border-blue-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    awaiting_shipment: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    in_transit: 'bg-sky-50 text-sky-700 border-sky-200',
    received: 'bg-teal-50 text-teal-700 border-teal-200',
    inspection: 'bg-purple-50 text-purple-700 border-purple-200',
    cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
    expired: 'bg-gray-100 text-gray-600 border-gray-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    processing: 'bg-blue-50 text-blue-700 border-blue-200',
    failed: 'bg-rose-50 text-rose-700 border-rose-200',
};

export function StatusBadge({ status, className = '' }) {
    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${STATUS_STYLES[status] || 'bg-gray-100 text-gray-600 border-gray-200'} ${className}`}>
            {(status || 'unknown').replace(/_/g, ' ')}
        </span>
    );
}

export function PageShell({ title, subtitle, actions, children }) {
    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
                </div>
                {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
            </div>
            <div className="mt-5">{children}</div>
        </div>
    );
}

export function Card({ children, className = '' }) {
    return (
        <div className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 ${className}`}>
            {children}
        </div>
    );
}

export function EmptyState({ title, description, action = null }) {
    return (
        <div className="px-6 py-12 text-center">
            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</p>
            {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function StatCard({ label, value, tone = 'gray' }) {
    const tones = {
        gray: 'text-gray-900 dark:text-gray-100',
        amber: 'text-amber-600 dark:text-amber-400',
        blue: 'text-blue-600 dark:text-blue-400',
        emerald: 'text-emerald-600 dark:text-emerald-400',
        rose: 'text-rose-600 dark:text-rose-400',
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
            <p className={`mt-1 text-lg font-bold ${tones[tone] || tones.gray}`}>{value}</p>
        </div>
    );
}

export function FilterBar({ statuses, filters, onChange, searchPlaceholder = 'Search…' }) {
    return (
        <div className="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div className="-mx-1 flex gap-1 overflow-x-auto pb-1">
                {statuses.map((status) => {
                    const active = (filters.status || '') === status.value;
                    return (
                        <button
                            key={status.value || 'all'}
                            type="button"
                            onClick={() => onChange({ ...filters, status: status.value })}
                            className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                active
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                            }`}
                        >
                            {status.label}
                            {typeof status.count === 'number' && (
                                <span className={`ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] ${active ? 'bg-white/20' : 'bg-gray-100 dark:bg-gray-800'}`}>
                                    {status.count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
            <div className="relative w-full sm:w-64">
                <input
                    value={filters.search || ''}
                    onChange={(event) => onChange({ ...filters, search: event.target.value })}
                    placeholder={searchPlaceholder}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                />
            </div>
        </div>
    );
}

export function BackLink({ href, label }) {
    return (
        <Link href={href} className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            {label}
        </Link>
    );
}

export function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString();
}
