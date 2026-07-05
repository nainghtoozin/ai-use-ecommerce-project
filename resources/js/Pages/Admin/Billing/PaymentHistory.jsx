import { useState, useEffect, useCallback } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PaymentIntentBadge from '@/Components/Billing/PaymentIntentBadge';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { Search, X, Filter, Calendar, ChevronDown, ExternalLink, Clock, MessageSquare, Image, Check, AlertCircle, Eye, FileText, CreditCard, XCircle, ShieldCheck } from 'lucide-react';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'waiting_payment', label: 'Waiting Payment' },
    { value: 'waiting_review', label: 'Waiting Review' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

function StatCard({ icon: Icon, label, value, color }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
            <div className={`w-12 h-12 rounded-xl ${color.bg} flex items-center justify-center flex-shrink-0`}>
                <Icon className={`w-6 h-6 ${color.text}`} />
            </div>
            <div>
                <p className="text-2xl font-bold text-gray-900">{value}</p>
                <p className="text-sm text-gray-500">{label}</p>
            </div>
        </div>
    );
}

function TimelineIcon({ type }) {
    const icons = {
        created: Clock,
        paid: Check,
        reviewed: MessageSquare,
        approved: Check,
        rejected: XCircle,
        cancelled: XCircle,
        expired: Clock,
        completed: Check,
        evidence_uploaded: Image,
        comment_added: MessageSquare,
    };
    const colors = {
        created: 'text-blue-500 bg-blue-100',
        paid: 'text-emerald-500 bg-emerald-100',
        reviewed: 'text-purple-500 bg-purple-100',
        approved: 'text-emerald-500 bg-emerald-100',
        rejected: 'text-red-500 bg-red-100',
        cancelled: 'text-gray-500 bg-gray-100',
        expired: 'text-gray-500 bg-gray-100',
        completed: 'text-green-500 bg-green-100',
        evidence_uploaded: 'text-amber-500 bg-amber-100',
        comment_added: 'text-blue-500 bg-blue-100',
    };
    const Icon = icons[type] || Clock;
    const color = colors[type] || 'text-gray-500 bg-gray-100';
    return (
        <div className={`w-8 h-8 rounded-full ${color} flex items-center justify-center flex-shrink-0`}>
            <Icon className="w-4 h-4" />
        </div>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}



function PaymentDetailDrawer({ intent, open, onClose }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    useEffect(() => {
        if (open) {
            document.body.style.overflow = 'hidden';
        }
        return () => { document.body.style.overflow = ''; };
    }, [open]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape') onClose();
    }, [onClose]);

    useEffect(() => {
        if (open) {
            window.addEventListener('keydown', handleKeyDown);
        }
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, handleKeyDown]);

    if (!open || !intent) return null;

    return (
        <div className="fixed inset-0 z-50 flex">
            <div className="fixed inset-0 bg-black/30" onClick={onClose} aria-hidden="true" />
            <div className="relative ml-auto w-full max-w-xl bg-white shadow-2xl overflow-y-auto" role="dialog" aria-modal="true" aria-label="Payment details">
                <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                            <CreditCard className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Payment Details</h2>
                            <p className="text-xs text-gray-500 font-mono">{intent.reference_number}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Close details">
                        <X className="w-5 h-5 text-gray-500" />
                    </button>
                </div>

                <div className="p-6 space-y-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <PaymentIntentBadge status={intent.status} />
                            {intent.subscription_event === 'subscription_activated' && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Activated
                                </span>
                            )}
                            {intent.subscription_event === 'subscription_renewed' && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    Renewed
                                </span>
                            )}
                        </div>
                        <span className="text-xs text-gray-400">{formatDateTime(intent.created_at)}</span>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-5 space-y-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Plan</span>
                            <span className="font-semibold text-gray-900">{intent.plan?.name || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Billing Cycle</span>
                            <span className="font-semibold text-gray-900 capitalize">{intent.billing_cycle || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Amount</span>
                            <span className="font-semibold text-gray-900">{formatCurrency(intent.amount, pc)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Currency</span>
                            <span className="font-semibold text-gray-900">{intent.currency || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Gateway</span>
                            <span className="font-semibold text-gray-900 capitalize">{intent.gateway || '—'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-500">Submitted</span>
                            <span className="font-semibold text-gray-900">{formatDate(intent.created_at)}</span>
                        </div>
                    </div>

                    {intent.evidences && intent.evidences.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Image className="w-4 h-4 text-gray-400" /> Payment Evidence
                                </h3>
                            </div>
                            <div className="p-5 space-y-3">
                                {intent.evidences.map((ev) => (
                                    <div key={ev.id}>
                                        <div className="relative rounded-lg overflow-hidden bg-gray-50 border border-gray-200">
                                            <img
                                                src={ev.file_path_url}
                                                alt="Payment evidence"
                                                className="w-full max-h-64 object-contain cursor-pointer"
                                                onClick={() => window.open(ev.file_path_url, '_blank')}
                                            />
                                        </div>
                                        {ev.note && <p className="text-xs text-gray-500 mt-2 italic">"{ev.note}"</p>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {intent.comments && intent.comments.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <MessageSquare className="w-4 h-4 text-gray-400" /> Review Comments
                                </h3>
                            </div>
                            <div className="p-5 space-y-4">
                                {intent.comments.map((c) => (
                                    <div key={c.id} className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                            <span className="text-xs font-semibold text-gray-600">
                                                {c.author_name?.charAt(0)?.toUpperCase() || '?'}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-semibold text-gray-900">{c.author_name}</span>
                                                <span className="text-xs text-gray-400">{formatDateTime(c.created_at)}</span>
                                            </div>
                                            <p className="text-sm text-gray-600 mt-0.5">{c.body}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {intent.comments && intent.comments.length === 0 && (
                        <div className="bg-gray-50 rounded-xl p-5 text-center">
                            <MessageSquare className="w-8 h-8 text-gray-300 mx-auto mb-2" />
                            <p className="text-sm text-gray-500">No review comments yet.</p>
                        </div>
                    )}

                    {intent.timeline && intent.timeline.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Clock className="w-4 h-4 text-gray-400" /> Timeline
                                </h3>
                            </div>
                            <div className="p-5">
                                <div className="space-y-0">
                                    {intent.timeline.map((event, i) => (
                                        <div key={event.id} className="relative flex items-start gap-4 pb-6 last:pb-0">
                                            {i < intent.timeline.length - 1 && (
                                                <div className="absolute left-4 top-8 bottom-0 w-px bg-gray-200" />
                                            )}
                                            <TimelineIcon type={event.type} />
                                            <div className="pt-1">
                                                <p className="text-sm font-medium text-gray-900">{event.description || event.type}</p>
                                                <p className="text-xs text-gray-400 mt-0.5">{formatDateTime(event.occurred_at)}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-center">
                        <button
                            onClick={onClose}
                            className="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="flex items-center justify-between pt-6">
            <p className="text-sm text-gray-500">
                Page {links.find(l => l.active)?.label || '—'}
            </p>
            <div className="flex gap-1">
                {links.map((link, i) => {
                    if (!link.url) {
                        return (
                            <span key={i} className="px-3 py-1.5 text-sm text-gray-400 rounded-md cursor-not-allowed">
                                {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                            </span>
                        );
                    }
                    return (
                        <button
                            key={i}
                            onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function AdminBillingPaymentHistory({ intents, filters, plans, subscription, stats }) {
    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const [showFilters, setShowFilters] = useState(false);
    const [searchValue, setSearchValue] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [planFilter, setPlanFilter] = useState(filters?.plan_id || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');
    const [selectedIntent, setSelectedIntent] = useState(null);

    const hasActiveFilters = filters?.status || filters?.plan_id || filters?.date_from || filters?.date_to || filters?.search;

    const applyFilters = () => {
        router.get(adminUrl('/admin/billing/payment-history'), {
            status: statusFilter || undefined,
            plan_id: planFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            search: searchValue || undefined,
        }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setSearchValue('');
        setStatusFilter('');
        setPlanFilter('');
        setDateFrom('');
        setDateTo('');
        router.get(adminUrl('/admin/billing/payment-history'), {}, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const viewPayment = (intent) => {
        setSelectedIntent(intent);
    };

    const data = intents?.data || [];
    const paginationLinks = intents?.links || [];

    const items = data.map((intent) => ({
        id: intent.id,
        reference_number: intent.reference_number,
        plan: intent.plan,
        billing_cycle: intent.billing_cycle,
        amount: intent.amount,
        currency: intent.currency,
        gateway: intent.gateway,
        status: intent.status,
        created_at: intent.created_at,
        evidences: intent.evidences || [],
        timeline: intent.timeline || [],
        comments: intent.comments || [],
        reviews: intent.reviews || [],
        subscription_event: intent.subscription_event,
    }));

    return (
        <AdminLayout>
            <Head title="Payment History" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Payment History</h1>
                        <p className="text-sm text-gray-500 mt-1">Review all your subscription payments, timelines, and admin comments</p>
                    </div>
                    <button
                        onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                        className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2"
                    >
                        Upgrade Plan
                    </button>
                </div>

                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <StatCard icon={CreditCard} label="Total Payments" value={stats.total || 0} color={{ bg: 'bg-blue-100', text: 'text-blue-600' }} />
                        <StatCard icon={Check} label="Completed" value={stats.completed || 0} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} />
                        <StatCard icon={Clock} label="Pending Review" value={stats.pending_review || 0} color={{ bg: 'bg-purple-100', text: 'text-purple-600' }} />
                        <StatCard icon={XCircle} label="Rejected" value={stats.rejected || 0} color={{ bg: 'bg-red-100', text: 'text-red-600' }} />
                        <StatCard icon={ShieldCheck} label="Current Plan" value={subscription?.plan?.name || '—'} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} />
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                        <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                    placeholder="Search by reference or plan..."
                                    className="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    aria-label="Search payments"
                                />
                            </div>
                            <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                Search
                            </button>
                        </form>
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className={`px-4 py-2 text-sm font-medium rounded-lg border transition-colors flex items-center gap-2 ${
                                showFilters || hasActiveFilters
                                    ? 'bg-blue-50 border-blue-200 text-blue-700'
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <Filter className="w-4 h-4" />
                            Filters
                            {hasActiveFilters && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                        </button>
                        {hasActiveFilters && (
                            <button onClick={clearFilters} className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                <X className="w-3.5 h-3.5" /> Clear
                            </button>
                        )}
                    </div>

                    {showFilters && (
                        <div className="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => { setStatusFilter(e.target.value); }}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                        aria-label="Filter by status"
                                    >
                                        {statusOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                                    <select
                                        value={planFilter}
                                        onChange={(e) => { setPlanFilter(e.target.value); }}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                        aria-label="Filter by plan"
                                    >
                                        <option value="">All Plans</option>
                                        {plans && plans.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date from"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                                    <input
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) => setDateTo(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date to"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <button
                                        onClick={applyFilters}
                                        className="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {items.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Billing</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((intent) => (
                                        <tr key={intent.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-mono font-semibold text-gray-900">{intent.reference_number}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-medium text-gray-900">{intent.plan?.name || '—'}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm text-gray-600 capitalize">{intent.billing_cycle || '—'}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-semibold text-gray-900">{formatCurrency(intent.amount, pc)}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm text-gray-500">{formatDate(intent.created_at)}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <PaymentIntentBadge status={intent.status} size="sm" />
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <button
                                                    onClick={() => viewPayment(intent)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                    aria-label={`View payment ${intent.reference_number}`}
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <div className="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <FileText className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-base font-semibold text-gray-900 mb-2">No Payment History Yet</h3>
                            <p className="text-sm text-gray-500 max-w-md mx-auto mb-6">
                                {hasActiveFilters
                                    ? 'No payments match your current filters. Try adjusting your search or filter criteria.'
                                    : 'You have no payment records yet. Upgrade your subscription to get started.'}
                            </p>
                            {!hasActiveFilters && (
                                <button
                                    onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                                    className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors"
                                >
                                    Upgrade Plan
                                </button>
                            )}
                            {hasActiveFilters && (
                                <button onClick={clearFilters} className="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                    Clear Filters
                                </button>
                            )}
                        </div>
                    )}

                    {items.length > 0 && <div className="px-5 py-4 border-t border-gray-100">
                        <Pagination links={paginationLinks} />
                    </div>}
                </div>
            </div>

            <PaymentDetailDrawer
                intent={selectedIntent}
                open={!!selectedIntent}
                onClose={() => setSelectedIntent(null)}
            />
        </AdminLayout>
    );
}
