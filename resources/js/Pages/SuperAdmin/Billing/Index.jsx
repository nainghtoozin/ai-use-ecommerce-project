import { useState, useEffect, useCallback } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PaymentIntentBadge from '@/Components/Billing/PaymentIntentBadge';
import { formatCurrency as _formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import {
    Search, X, Filter, Clock, Check, XCircle,
    CreditCard, Eye, AlertCircle, Image,
    MessageSquare, ShieldCheck, Loader2,
    Building2, Store, FileText,
} from 'lucide-react';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'waiting_review', label: 'Waiting Review' },
    { value: 'waiting_payment', label: 'Waiting Payment' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    pending: 'bg-blue-100 text-blue-700',
    waiting_payment: 'bg-amber-100 text-amber-700',
    waiting_review: 'bg-purple-100 text-purple-700',
    approved: 'bg-emerald-100 text-emerald-700',
    paid: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
    expired: 'bg-gray-100 text-gray-600',
    failed: 'bg-red-100 text-red-700',
};

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

function ConfirmDialog({ open, title, message, confirmLabel, confirmClass, onConfirm, onCancel, children }) {
    useEffect(() => {
        if (open) document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [open]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-black/40" onClick={onCancel} />
            <div className="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6" role="dialog" aria-modal="true">
                <div className="text-center">
                    <div className={`w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center ${confirmClass === 'bg-red-100' ? 'bg-red-100' : 'bg-emerald-100'}`}>
                        {confirmClass === 'bg-red-100'
                            ? <XCircle className="w-7 h-7 text-red-600" />
                            : <Check className="w-7 h-7 text-emerald-600" />}
                    </div>
                    <h3 className="text-lg font-bold text-gray-900 mb-2">{title}</h3>
                    <p className="text-sm text-gray-500">{message}</p>
                    {children}
                </div>
                <div className="mt-6 flex gap-3 justify-center">
                    <button onClick={onCancel} className="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button onClick={onConfirm} className={`px-5 py-2.5 text-white rounded-xl text-sm font-semibold transition-colors ${
                        confirmClass === 'bg-red-100' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'
                    }`}>
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

function ReviewPanel({ intent, onApprove, onReject, processing }) {
    const [showConfirm, setShowConfirm] = useState(null);
    const [rejectReason, setRejectReason] = useState('');

    if (!intent || intent.status !== 'waiting_review') return null;

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-5 py-3 border-b border-gray-100">
                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                    <ShieldCheck className="w-4 h-4 text-gray-400" /> Review Payment
                </h3>
            </div>
            <div className="p-5 space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <button
                        onClick={() => setShowConfirm('approve')}
                        disabled={processing}
                        className="px-4 py-3 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
                        Approve Payment
                    </button>
                    <button
                        onClick={() => setShowConfirm('reject')}
                        disabled={processing}
                        className="px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <XCircle className="w-4 h-4" />}
                        Reject Payment
                    </button>
                </div>
            </div>

            <ConfirmDialog
                open={showConfirm === 'approve'}
                title="Approve Payment?"
                message={`This will approve payment ${intent.reference_number}. The subscription will be activated and the merchant will be notified.`}
                confirmLabel="Yes, Approve"
                confirmClass="bg-emerald-100"
                onConfirm={() => { setShowConfirm(null); onApprove(intent.id); }}
                onCancel={() => setShowConfirm(null)}
            />

            <ConfirmDialog
                open={showConfirm === 'reject'}
                title="Reject Payment?"
                message="This payment will be marked as rejected. The merchant may resubmit with corrected evidence."
                confirmLabel="Yes, Reject"
                confirmClass="bg-red-100"
                onConfirm={() => { if (rejectReason.trim()) { setShowConfirm(null); onReject(intent.id, rejectReason); } }}
                onCancel={() => { setShowConfirm(null); setRejectReason(''); }}
            >
                <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-700 text-left mb-1.5">Reason for rejection</label>
                    <textarea
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value.slice(0, 2000))}
                        rows={3}
                        placeholder="Explain why this payment is being rejected..."
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none resize-none"
                        aria-label="Rejection reason"
                    />
                    <p className="text-xs text-gray-400 mt-1 text-right">{rejectReason.length}/2000</p>
                </div>
            </ConfirmDialog>
        </div>
    );
}

function PaymentDetailDrawer({ intent, open, onClose, onApprove, onReject, processing }) {
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

    const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);
    const fmt = (a, c) => a == null ? '—' : _formatCurrency(a, { code: c || pc.code, symbol: pc.symbol, position: pc.position, decimals: pc.decimals });

    if (!open || !intent) return null;

    return (
        <div className="fixed inset-0 z-50 flex">
            <div className="fixed inset-0 bg-black/30" onClick={onClose} aria-hidden="true" />
            <div className="relative ml-auto w-full max-w-xl bg-white shadow-2xl overflow-y-auto" role="dialog" aria-modal="true" aria-label="Payment review details">
                <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                            <CreditCard className="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">Payment Review</h2>
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

                    {intent.tenant && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Store className="w-4 h-4 text-gray-400" /> Merchant Information
                                </h3>
                            </div>
                            <div className="p-5 space-y-2.5">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Store</span>
                                    <div className="flex items-center gap-1.5">
                                        <Building2 className="w-3.5 h-3.5 text-gray-400" />
                                        <span className="font-semibold text-gray-900">{intent.tenant.name}</span>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Slug</span>
                                    <span className="font-mono text-sm text-gray-600">{intent.tenant.slug}</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Email</span>
                                    <span className="text-sm text-gray-600">{intent.tenant.email || '—'}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="bg-gray-50 rounded-xl p-5 space-y-3">
                        <h3 className="text-sm font-semibold text-gray-900 mb-1">Payment Details</h3>
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
                            <span className="font-semibold text-gray-900">{fmt(intent.amount, intent.currency)}</span>
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
                            <span className="font-semibold text-gray-900">{formatDateTime(intent.created_at)}</span>
                        </div>
                    </div>

                    {intent.evidences && intent.evidences.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-3 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <Image className="w-4 h-4 text-gray-400" /> Payment Evidence
                                </h3>
                            </div>
                            <div className="p-5 space-y-4">
                                {intent.evidences.map((ev) => (
                                    <div key={ev.id} className="space-y-3">
                                        <div className="bg-gray-50 rounded-lg p-4 space-y-2.5">
                                            {ev.sender_name && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-500">Sender Name</span>
                                                    <span className="font-semibold text-gray-900">{ev.sender_name}</span>
                                                </div>
                                            )}
                                            {ev.sender_account && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-500">Account / Phone</span>
                                                    <span className="font-semibold text-gray-900">{ev.sender_account}</span>
                                                </div>
                                            )}
                                            {ev.transaction_reference && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-500">Transaction Ref</span>
                                                    <span className="font-mono font-semibold text-gray-900">{ev.transaction_reference}</span>
                                                </div>
                                            )}
                                            {ev.transferred_amount && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-500">Transferred Amount</span>
                                                    <span className="font-semibold text-gray-900">{fmt(ev.transferred_amount, intent.currency)}</span>
                                                </div>
                                            )}
                                            {ev.transfer_date && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-500">Transfer Date</span>
                                                    <span className="font-semibold text-gray-900">{formatDate(ev.transfer_date)}</span>
                                                </div>
                                            )}
                                        </div>
                                        <div className="relative rounded-lg overflow-hidden bg-gray-50 border border-gray-200">
                                            <img
                                                src={ev.file_path_url}
                                                alt="Payment evidence"
                                                className="w-full max-h-64 object-contain cursor-pointer"
                                                onClick={() => window.open(ev.file_path_url, '_blank')}
                                            />
                                        </div>
                                        {ev.note && <p className="text-xs text-gray-500 italic">"{ev.note}"</p>}
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

                    <ReviewPanel intent={intent} onApprove={onApprove} onReject={onReject} processing={processing} />

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
                                link.active ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function SuperAdminBillingConsole({ intents, filters, plans, stats }) {
    const { platform_setting } = usePage().props;
    const pc = getPlatformCurrencyConfig(platform_setting);
    const fmt = (a, c) => a == null ? '—' : _formatCurrency(a, { code: c || pc.code, symbol: pc.symbol, position: pc.position, decimals: pc.decimals });

    const [showFilters, setShowFilters] = useState(false);
    const [searchValue, setSearchValue] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [planFilter, setPlanFilter] = useState(filters?.plan_id || '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');
    const [selectedIntent, setSelectedIntent] = useState(null);
    const [processing, setProcessing] = useState(false);

    const hasActiveFilters = filters?.status || filters?.plan_id || filters?.date_from || filters?.date_to || filters?.search;

    const applyFilters = () => {
        router.get('/superadmin/billing', {
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
        router.get('/superadmin/billing', {}, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const handleApprove = (intentId) => {
        setProcessing(true);
        router.post(`/superadmin/billing/${intentId}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIntent(null); setProcessing(false); },
            onError: () => setProcessing(false),
            onFinish: () => setProcessing(false),
        });
    };

    const handleReject = (intentId, reason) => {
        setProcessing(true);
        router.post(`/superadmin/billing/${intentId}/reject`, { reason }, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIntent(null); setProcessing(false); },
            onError: () => setProcessing(false),
            onFinish: () => setProcessing(false),
        });
    };

    const items = (intents?.data || []).map((intent) => ({
        ...intent,
        evidences: intent.evidences || [],
        timeline: intent.timeline || [],
        comments: intent.comments || [],
        reviews: intent.reviews || [],
    }));

    const pendingPriority = items.filter(i => i.status === 'waiting_review').length;

    return (
        <AdminLayout>
            <Head title="Billing Console" />

            <div className="p-6 lg:p-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Billing Console</h1>
                        <p className="text-sm text-gray-500 mt-1">Review and manage all subscription payments across the platform</p>
                    </div>
                    {pendingPriority > 0 && (
                        <div className="flex items-center gap-2 px-4 py-2 bg-purple-50 rounded-xl border border-purple-200">
                            <Clock className="w-4 h-4 text-purple-600" />
                            <span className="text-sm font-semibold text-purple-700">{pendingPriority} pending review</span>
                        </div>
                    )}
                </div>

                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard icon={Clock} label="Pending Review" value={stats.pending_review || 0} color={{ bg: 'bg-purple-100', text: 'text-purple-600' }} />
                        <StatCard icon={Check} label="Approved Today" value={stats.approved_today || 0} color={{ bg: 'bg-emerald-100', text: 'text-emerald-600' }} />
                        <StatCard icon={XCircle} label="Rejected Today" value={stats.rejected_today || 0} color={{ bg: 'bg-red-100', text: 'text-red-600' }} />
                        <StatCard icon={CreditCard} label="Completed Payments" value={stats.completed_total || 0} color={{ bg: 'bg-blue-100', text: 'text-blue-600' }} />
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
                                    placeholder="Search by reference, merchant, plan, transaction ref, sender..."
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
                                        onChange={(e) => setStatusFilter(e.target.value)}
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
                                        onChange={(e) => setPlanFilter(e.target.value)}
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
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Submitted</th>
                                        <th className="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((intent) => (
                                        <tr key={intent.id} className={`hover:bg-gray-50/50 transition-colors ${intent.status === 'waiting_review' ? 'bg-purple-50/30' : ''}`}>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-mono font-semibold text-gray-900">{intent.reference_number}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                        <Building2 className="w-3.5 h-3.5 text-gray-500" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{intent.tenant?.name || '—'}</p>
                                                        <p className="text-xs text-gray-400">{intent.tenant?.email || ''}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm text-gray-600">{intent.plan?.name || '—'}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm font-semibold text-gray-900">{fmt(intent.amount, intent.currency)}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span className="text-sm text-gray-500">{formatDate(intent.created_at)}</span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <PaymentIntentBadge status={intent.status} size="sm" />
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <button
                                                    onClick={() => setSelectedIntent(intent)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                    aria-label={`Review payment ${intent.reference_number}`}
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    Review
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
                            <h3 className="text-base font-semibold text-gray-900 mb-2">
                                {hasActiveFilters ? 'No Matching Payments' : 'All Payments Reviewed'}
                            </h3>
                            <p className="text-sm text-gray-500 max-w-md mx-auto mb-6">
                                {hasActiveFilters
                                    ? 'No payments match your current filters. Try adjusting your search criteria.'
                                    : 'All payments have been reviewed. Great job!'}
                            </p>
                            {hasActiveFilters && (
                                <button onClick={clearFilters} className="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                    Clear Filters
                                </button>
                            )}
                        </div>
                    )}

                    {items.length > 0 && (
                        <div className="px-5 py-4 border-t border-gray-100">
                            <Pagination links={intents?.links || []} />
                        </div>
                    )}
                </div>
            </div>

            <PaymentDetailDrawer
                intent={selectedIntent}
                open={!!selectedIntent}
                onClose={() => setSelectedIntent(null)}
                onApprove={handleApprove}
                onReject={handleReject}
                processing={processing}
            />
        </AdminLayout>
    );
}
