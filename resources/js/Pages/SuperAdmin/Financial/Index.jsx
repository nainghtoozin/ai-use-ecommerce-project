import { useState, useEffect, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PaymentIntentBadge from '@/Components/Billing/PaymentIntentBadge';
import {
    Search, X, Filter, Clock, Check, XCircle,
    CreditCard, DollarSign, TrendingUp, Eye,
    FileText, Image, MessageSquare,
    BookOpen, Download, BarChart3,
    ShieldCheck, Building2, AlertCircle,
} from 'lucide-react';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'completed', label: 'Completed' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
    { value: 'waiting_review', label: 'Waiting Review' },
    { value: 'waiting_payment', label: 'Waiting Payment' },
    { value: 'pending', label: 'Pending' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
    { value: 'failed', label: 'Failed' },
];

function formatCurrency(amount, currency) {
    if (amount === null || amount === undefined) return '—';
    const val = Number(amount).toLocaleString('en-US', { minimumFractionDigits: currency === 'MMK' ? 0 : 2, maximumFractionDigits: 2 });
    return currency === 'MMK' ? `${val} MMK` : `$${val}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function StatCard({ icon: Icon, label, value, color, prefix }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <div className="flex items-center gap-4">
                <div className={`w-12 h-12 rounded-xl ${color.bg} flex items-center justify-center flex-shrink-0`}>
                    <Icon className={`w-6 h-6 ${color.text}`} />
                </div>
                <div className="min-w-0">
                    <p className="text-xl font-bold text-gray-900 truncate">{prefix}{value}</p>
                    <p className="text-sm text-gray-500 truncate">{label}</p>
                </div>
            </div>
        </div>
    );
}

function TimelineIcon({ type }) {
    const icons = {
        created: Clock, paid: Check, reviewed: MessageSquare,
        approved: Check, rejected: XCircle, cancelled: XCircle,
        expired: Clock, completed: Check, evidence_uploaded: Image,
        comment_added: MessageSquare,
    };
    const colors = {
        created: 'text-blue-500 bg-blue-100', paid: 'text-emerald-500 bg-emerald-100',
        reviewed: 'text-purple-500 bg-purple-100', approved: 'text-emerald-500 bg-emerald-100',
        rejected: 'text-red-500 bg-red-100', cancelled: 'text-gray-500 bg-gray-100',
        expired: 'text-gray-500 bg-gray-100', completed: 'text-green-500 bg-green-100',
        evidence_uploaded: 'text-amber-500 bg-amber-100', comment_added: 'text-blue-500 bg-blue-100',
    };
    const Icon = icons[type] || Clock;
    const color = colors[type] || 'text-gray-500 bg-gray-100';
    return (
        <div className={`w-8 h-8 rounded-full ${color} flex items-center justify-center flex-shrink-0`}>
            <Icon className="w-4 h-4" />
        </div>
    );
}

function TransactionDetailDrawer({ txn, open, onClose }) {
    useEffect(() => {
        if (open) document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [open]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape') onClose();
    }, [onClose]);

    useEffect(() => {
        if (open) window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, handleKeyDown]);

    if (!open || !txn) return null;

    const intent = txn.intent;

    return (
        <div className="fixed inset-0 z-50 flex">
            <div className="fixed inset-0 bg-black/30" onClick={onClose} aria-hidden="true" />
            <div className="relative ml-auto w-full max-w-xl bg-white shadow-2xl overflow-y-auto" role="dialog" aria-modal="true" aria-label="Transaction details">
                <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                            <BookOpen className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Transaction Details</h2>
                            <p className="text-xs text-gray-500 font-mono">{txn.transaction_number}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Close">
                        <X className="w-5 h-5 text-gray-500" />
                    </button>
                </div>

                <div className="p-6 space-y-6">
                    <div className="flex items-center justify-between">
                        <PaymentIntentBadge status={txn.status} />
                        <span className="text-xs text-gray-400">{formatDateTime(txn.created_at)}</span>
                    </div>

                    {txn.tenant && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Building2 className="w-4 h-4 text-gray-400" /> Merchant
                                </h3>
                            </div>
                            <div className="p-5 space-y-2.5 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Store</span>
                                    <span className="font-semibold text-gray-900">{txn.tenant.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Email</span>
                                    <span className="text-gray-600">{txn.tenant.email || '—'}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="bg-gray-50 rounded-xl p-5 space-y-3">
                        <h3 className="text-sm font-semibold text-gray-900">Payment Details</h3>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Plan</span>
                            <span className="font-semibold text-gray-900">{txn.plan?.name || '—'}</span>
                        </div>
                        {intent?.billing_cycle && (
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Billing Cycle</span>
                                <span className="capitalize text-gray-900">{intent.billing_cycle}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Amount</span>
                            <span className="font-bold text-gray-900">{formatCurrency(txn.amount, txn.currency)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Currency</span>
                            <span className="text-gray-900">{txn.currency}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Gateway</span>
                            <span className="capitalize text-gray-900">{txn.gateway}</span>
                        </div>
                        {txn.gateway_reference && (
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Gateway Ref</span>
                                <span className="font-mono text-xs text-gray-600">{txn.gateway_reference}</span>
                            </div>
                        )}
                        {intent?.reference_number && (
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Intent Ref</span>
                                <span className="font-mono text-xs text-gray-600">{intent.reference_number}</span>
                            </div>
                        )}
                    </div>

                    {intent?.evidences && intent.evidences.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Image className="w-4 h-4 text-gray-400" /> Evidence
                                </h3>
                            </div>
                            <div className="p-5 space-y-3">
                                {intent.evidences.map((ev) => (
                                    <div key={ev.id}>
                                        <div className="rounded-lg overflow-hidden bg-gray-50 border border-gray-200">
                                            <img
                                                src={`/storage/${ev.file_path}`}
                                                alt="Payment evidence"
                                                className="w-full max-h-64 object-contain cursor-pointer"
                                                onClick={() => window.open(`/storage/${ev.file_path}`, '_blank')}
                                            />
                                        </div>
                                        {ev.note && <p className="text-xs text-gray-500 mt-2 italic">"{ev.note}"</p>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {intent?.comments && intent.comments.length > 0 && (
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
                                            <span className="text-xs font-semibold text-gray-600">{c.author_name?.charAt(0)?.toUpperCase() || '?'}</span>
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

                    {intent?.timeline && intent.timeline.length > 0 && (
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

                    {intent?.reviews && intent.reviews.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <ShieldCheck className="w-4 h-4 text-gray-400" /> Audit Trail
                                </h3>
                            </div>
                            <div className="p-5 space-y-3">
                                {intent.reviews.map((r) => (
                                    <div key={r.id} className="flex items-start gap-3 text-sm">
                                        <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${
                                            r.action === 'approved' ? 'bg-emerald-100' : 'bg-red-100'
                                        }`}>
                                            {r.action === 'approved'
                                                ? <Check className="w-4 h-4 text-emerald-600" />
                                                : <XCircle className="w-4 h-4 text-red-600" />}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-gray-900 capitalize">{r.action}</span>
                                                <span className="text-xs text-gray-400">{formatDateTime(r.created_at)}</span>
                                            </div>
                                            {r.reviewer_name && <p className="text-xs text-gray-500">by {r.reviewer_name}</p>}
                                            {r.reason && <p className="text-xs text-gray-600 mt-0.5">Reason: {r.reason}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {txn.ledger && txn.ledger.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <BookOpen className="w-4 h-4 text-gray-400" /> Ledger Entries
                                </h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                                            <th className="px-5 py-3 font-medium">Entry</th>
                                            <th className="px-5 py-3 font-medium">Amount</th>
                                            <th className="px-5 py-3 font-medium">Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {txn.ledger.map((le) => (
                                            <tr key={le.id}>
                                                <td className="px-5 py-3">
                                                    <span className="capitalize text-gray-900">{le.type}</span>
                                                    {le.description && <p className="text-xs text-gray-500">{le.description}</p>}
                                                </td>
                                                <td className="px-5 py-3 font-mono font-semibold text-gray-900">
                                                    {formatCurrency(le.amount, le.currency)}
                                                </td>
                                                <td className="px-5 py-3 text-gray-500">{formatDateTime(le.recorded_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-center pt-2">
                        <button onClick={onClose} className="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
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
            <p className="text-sm text-gray-500">Page {links.find(l => l.active)?.label || '—'}</p>
            <div className="flex gap-1">
                {links.map((link, i) => {
                    if (!link.url) return (
                        <span key={i} className="px-3 py-1.5 text-sm text-gray-400 rounded-md cursor-not-allowed">
                            {link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', '←').replace('Next', '→')}
                        </span>
                    );
                    return (
                        <button key={i} onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`px-3 py-1.5 text-sm rounded-md transition-colors ${link.active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function SuperAdminFinancialConsole({ transactions, filters, plans, stats }) {
    const [showFilters, setShowFilters] = useState(false);
    const [searchValue, setSearchValue] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [planFilter, setPlanFilter] = useState(filters?.plan_id || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');
    const [amountMin, setAmountMin] = useState(filters?.amount_min || '');
    const [amountMax, setAmountMax] = useState(filters?.amount_max || '');
    const [selectedTxn, setSelectedTxn] = useState(null);

    const hasActiveFilters = filters?.status || filters?.plan_id || filters?.date_from || filters?.date_to || filters?.search || filters?.amount_min || filters?.amount_max;

    const applyFilters = () => {
        router.get('/superadmin/financial', {
            status: statusFilter || undefined,
            plan_id: planFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            amount_min: amountMin || undefined,
            amount_max: amountMax || undefined,
            search: searchValue || undefined,
        }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setSearchValue(''); setStatusFilter(''); setPlanFilter('');
        setDateFrom(''); setDateTo(''); setAmountMin(''); setAmountMax('');
        router.get('/superadmin/financial', {}, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => { e.preventDefault(); applyFilters(); };

    const items = (transactions?.data || []).map(t => ({
        ...t,
        intent: t.intent || null,
        ledger: t.ledger || [],
    }));

    return (
        <AdminLayout>
            <Head title="Financial Console" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Financial Console</h1>
                        <p className="text-sm text-gray-500 mt-1">Platform transaction and financial overview</p>
                    </div>
                    <div className="flex gap-2">
                        <button className="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center gap-2">
                            <Download className="w-4 h-4" /> Export CSV
                        </button>
                    </div>
                </div>

                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard icon={TrendingUp} label="Total Revenue" value={formatCurrency(stats.total_revenue, 'MMK')} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} />
                        <StatCard icon={DollarSign} label="Monthly Revenue" value={formatCurrency(stats.monthly_revenue, 'MMK')} color={{ bg: 'bg-blue-100', text: 'text-blue-600' }} />
                        <StatCard icon={Clock} label="Today's Revenue" value={formatCurrency(stats.today_revenue, 'MMK')} color={{ bg: 'bg-purple-100', text: 'text-purple-600' }} />
                        <StatCard icon={AlertCircle} label="Pending Revenue" value={formatCurrency(stats.pending_revenue, 'MMK')} color={{ bg: 'bg-amber-100', text: 'text-amber-600' }} />
                        <StatCard icon={Check} label="Completed Transactions" value={stats.completed_transactions} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} />
                        <StatCard icon={Clock} label="Pending Review" value={stats.pending_review} color={{ bg: 'bg-purple-100', text: 'text-purple-600' }} />
                        <StatCard icon={XCircle} label="Rejected Payments" value={stats.rejected_payments} color={{ bg: 'bg-red-100', text: 'text-red-600' }} />
                        <StatCard icon={BarChart3} label="Avg Transaction" value={formatCurrency(stats.avg_transaction, 'MMK')} color={{ bg: 'bg-gray-100', text: 'text-gray-600' }} />
                    </div>
                )}

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                        <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input type="text" value={searchValue} onChange={(e) => setSearchValue(e.target.value)}
                                    placeholder="Search by reference, merchant, or plan..."
                                    className="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                    aria-label="Search transactions" />
                            </div>
                            <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Search</button>
                        </form>
                        <button onClick={() => setShowFilters(!showFilters)}
                            className={`px-4 py-2 text-sm font-medium rounded-lg border transition-colors flex items-center gap-2 ${
                                showFilters || hasActiveFilters ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}>
                            <Filter className="w-4 h-4" /> Filters
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
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                        aria-label="Filter by status">
                                        {statusOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Plan</label>
                                    <select value={planFilter} onChange={(e) => setPlanFilter(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                                        aria-label="Filter by plan">
                                        <option value="">All Plans</option>
                                        {plans?.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                                    <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date from" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                                    <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        aria-label="Date to" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Amount Min</label>
                                    <input type="number" step="0.01" min="0" value={amountMin} onChange={(e) => setAmountMin(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        placeholder="0.00" aria-label="Minimum amount" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Amount Max</label>
                                    <input type="number" step="0.01" min="0" value={amountMax} onChange={(e) => setAmountMax(e.target.value)}
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                        placeholder="999999" aria-label="Maximum amount" />
                                </div>
                                <div className="flex items-end sm:col-span-2">
                                    <button onClick={applyFilters} className="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
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
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Merchant</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((txn) => (
                                        <tr key={txn.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-mono font-semibold text-gray-900">{txn.transaction_number}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                        <Building2 className="w-3.5 h-3.5 text-gray-500" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{txn.tenant?.name || '—'}</p>
                                                        <p className="text-xs text-gray-400">{txn.tenant?.email || ''}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-sm text-gray-600">{txn.plan?.name || '—'}</td>
                                            <td className="px-5 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{formatCurrency(txn.amount, txn.currency)}</td>
                                            <td className="px-5 py-4 whitespace-nowrap text-sm text-gray-500">{formatDate(txn.created_at)}</td>
                                            <td className="px-5 py-4 whitespace-nowrap"><PaymentIntentBadge status={txn.status} size="sm" /></td>
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <button onClick={() => setSelectedTxn(txn)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                    aria-label={`View transaction ${txn.transaction_number}`}>
                                                    <Eye className="w-3.5 h-3.5" /> View
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-12 text-center">
                            <div className="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                <FileText className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-base font-semibold text-gray-900 mb-2">No Financial Records Yet</h3>
                            <p className="text-sm text-gray-500 max-w-md mx-auto">
                                {hasActiveFilters
                                    ? 'No transactions match your current filters. Try adjusting your search criteria.'
                                    : 'No transactions have been recorded yet. They will appear once merchants complete payments.'}
                            </p>
                            {hasActiveFilters && (
                                <button onClick={clearFilters} className="mt-6 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                    Clear Filters
                                </button>
                            )}
                        </div>
                    )}

                    {items.length > 0 && (
                        <div className="px-5 py-4 border-t border-gray-100">
                            <Pagination links={transactions?.links || []} />
                        </div>
                    )}
                </div>
            </div>

            <TransactionDetailDrawer txn={selectedTxn} open={!!selectedTxn} onClose={() => setSelectedTxn(null)} />
        </AdminLayout>
    );
}
