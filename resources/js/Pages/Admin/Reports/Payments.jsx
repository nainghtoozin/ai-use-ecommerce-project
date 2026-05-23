import { memo, useState, useCallback, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    DollarSign, CheckCircle, Clock, ArrowLeftRight, XCircle, Banknote,
    Search, Filter, FileText, Eye, ChevronDown, CreditCard, Landmark,
    Smartphone, Building2, Shield, ShieldCheck, ShieldX, Image, X,
    AlertTriangle,
} from 'lucide-react';

const PER_PAGE_OPTIONS = [25, 50, 100, 1000];

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString() + ' MMK';
}

const paymentStatusConfig = {
    paid:     { label: 'Paid',     className: 'bg-emerald-100 text-emerald-700' },
    pending:  { label: 'Pending',  className: 'bg-amber-100 text-amber-700' },
    failed:   { label: 'Failed',   className: 'bg-red-100 text-red-700' },
    refunded: { label: 'Refunded', className: 'bg-purple-100 text-purple-700' },
};

const verificationStatusConfig = {
    unchecked: { label: 'Unchecked', className: 'bg-slate-100 text-slate-600' },
    verified:  { label: 'Verified',  className: 'bg-emerald-100 text-emerald-700' },
    rejected:  { label: 'Rejected',  className: 'bg-red-100 text-red-700' },
};

const paymentMethodIcons = {
    kpay:      { icon: Smartphone,  color: 'text-blue-600', bg: 'bg-blue-50' },
    wavepay:   { icon: Smartphone,  color: 'text-cyan-600', bg: 'bg-cyan-50' },
    'cb pay':  { icon: Building2,   color: 'text-indigo-600', bg: 'bg-indigo-50' },
    'cb':      { icon: Building2,   color: 'text-indigo-600', bg: 'bg-indigo-50' },
    visa:      { icon: CreditCard,  color: 'text-blue-700', bg: 'bg-blue-50' },
    mastercard:{ icon: CreditCard,  color: 'text-orange-600', bg: 'bg-orange-50' },
    cod:       { icon: Banknote,    color: 'text-green-600', bg: 'bg-green-50' },
    default:   { icon: Landmark,    color: 'text-gray-500',  bg: 'bg-gray-50' },
};

function getPaymentMethodStyle(name) {
    if (!name) return paymentMethodIcons.default;
    const key = name.toLowerCase().replace(/\s+/g, '');
    for (const [k, v] of Object.entries(paymentMethodIcons)) {
        if (key.includes(k.replace(/\s+/g, '')) || name.toLowerCase().includes(k)) {
            return v;
        }
    }
    return paymentMethodIcons.default;
}

function derivePaymentStatus(order) {
    const ps = order.payment_status;
    const os = order.order_status;
    if (os === 'cancelled' && !['unpaid', 'rejected'].includes(ps)) return 'refunded';
    if (ps === 'rejected') return 'failed';
    if (['paid', 'verified'].includes(ps)) return 'paid';
    if (ps === 'pending') return 'pending';
    return 'pending';
}

function deriveVerificationStatus(order) {
    const ps = order.payment_status;
    if (ps === 'verified') return 'verified';
    if (ps === 'rejected') return 'rejected';
    if (['unpaid', 'paid'].includes(ps)) return 'unchecked';
    return 'unchecked';
}

const colorMap = {
    blue:    { bg: 'bg-blue-50',    text: 'text-blue-600' },
    emerald: { bg: 'bg-emerald-50',  text: 'text-emerald-600' },
    amber:   { bg: 'bg-amber-50',   text: 'text-amber-600' },
    purple:  { bg: 'bg-purple-50',  text: 'text-purple-600' },
    red:     { bg: 'bg-red-50',     text: 'text-red-600' },
    slate:   { bg: 'bg-slate-50',   text: 'text-slate-600' },
};

const Card = memo(function Card({ icon: Icon, label, value, color }) {
    const c = colorMap[color] || colorMap.slate;
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
            <div className="flex flex-col gap-1.5 lg:gap-2">
                <div className={`p-2 rounded-lg w-fit ${c.bg}`}>
                    <Icon className={`w-4 h-4 lg:w-5 lg:h-5 ${c.text}`} />
                </div>
                <div className="min-w-0">
                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">{label}</p>
                    <p className="text-sm sm:text-base lg:text-lg font-bold text-gray-900 break-words mt-0.5 tabular-nums">{value}</p>
                </div>
            </div>
        </div>
    );
});

const Badge = memo(function Badge({ config, value }) {
    if (!value && value !== 0) return null;
    const c = config[value] || config.default;
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${c.className}`}>
            {c.label}
        </span>
    );
});

function MethodIcon({ name }) {
    const style = getPaymentMethodStyle(name);
    const Icon = style.icon;
    return (
        <div className={`p-1.5 rounded-lg shrink-0 ${style.bg}`}>
            <Icon className={`w-4 h-4 ${style.color}`} />
        </div>
    );
}

function FlashToast() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState('');
    const [type, setType] = useState('success');

    useEffect(() => {
        const msg = flash?.success || flash?.error || flash?.warning;
        if (msg) {
            setMessage(msg);
            setType(flash.success ? 'success' : flash.error ? 'error' : 'warning');
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    if (!visible) return null;

    const bgMap = {
        success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-amber-50 border-amber-200 text-amber-800',
    };
    const iconMap = {
        success: CheckCircle,
        error: XCircle,
        warning: AlertTriangle,
    };
    const Icon = iconMap[type];

    return (
        <div className={`fixed top-4 right-4 z-50 max-w-sm w-full border rounded-xl p-4 shadow-lg ${bgMap[type]}`}>
            <div className="flex items-start gap-3">
                <Icon className="w-5 h-5 shrink-0 mt-0.5" />
                <p className="text-sm font-medium flex-1">{message}</p>
                <button onClick={() => setVisible(false)} className="shrink-0 p-0.5 hover:opacity-70 transition-opacity">
                    <X className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
}

function VerifyModal({ order, onClose, onVerify, onReject, processing }) {
    if (!order) return null;
    const methodName = order.payment_method?.name;
    const screenshotUrl = order.payment_screenshot_url;
    const hasScreenshot = order.payment_screenshot || order.payment_proof;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div className="flex items-center gap-2">
                        <Shield className="w-5 h-5 text-blue-600" />
                        <h2 className="text-lg font-semibold text-gray-900">Payment Verification</h2>
                    </div>
                    <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded-lg transition-colors">
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>

                <div className="p-6 space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Order</p>
                            <p className="text-sm font-semibold text-gray-900 mt-0.5">#{order.id}</p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</p>
                            <p className="text-sm font-mono text-gray-900 mt-0.5 break-all">
                                {order.transaction_id || <span className="text-gray-300">&mdash;</span>}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Payer Name</p>
                            <p className="text-sm font-medium text-gray-900 mt-0.5">
                                {order.payer_name || <span className="text-gray-300">&mdash;</span>}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</p>
                            <p className="text-sm font-medium text-gray-900 mt-0.5">
                                {order.first_name ? `${order.first_name} ${order.last_name}` : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</p>
                            <div className="flex items-center gap-2 mt-0.5">
                                <MethodIcon name={methodName} />
                                <span className="text-sm text-gray-700">{methodName || '—'}</span>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</p>
                            <p className="text-sm font-bold text-gray-900 mt-0.5">{formatCurrency(order.paid_amount || order.total_amount)}</p>
                            <p className="text-xs text-gray-400">
                                Expected: {formatCurrency(order.total_amount)}
                            </p>
                        </div>
                    </div>

                    {(hasScreenshot) && (
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Payment Screenshot</p>
                            <ScreenshotPreview
                                url={screenshotUrl}
                                fallbackText="Screenshot available"
                            />
                        </div>
                    )}

                    <div className="flex flex-col sm:flex-row gap-3 pt-2">
                        <button
                            onClick={() => onVerify(order)}
                            disabled={processing}
                            className="flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 disabled:opacity-50 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                        >
                            {processing ? (
                                <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                            ) : <ShieldCheck className="w-4 h-4" />}
                            {processing ? 'Verifying...' : 'Verify Payment'}
                        </button>
                        <button
                            onClick={() => onReject(order)}
                            disabled={processing}
                            className="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                        >
                            <ShieldX className="w-4 h-4" />
                            Reject Payment
                        </button>
                        <button
                            onClick={onClose}
                            disabled={processing}
                            className="px-4 py-2.5 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function RejectModal({ order, onClose, onConfirm, reason, setReason, processing }) {
    if (!order) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div className="flex items-center gap-2">
                        <ShieldX className="w-5 h-5 text-red-600" />
                        <h2 className="text-lg font-semibold text-gray-900">Reject Payment</h2>
                    </div>
                    <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded-lg transition-colors">
                        <X className="w-5 h-5 text-gray-400" />
                    </button>
                </div>

                <div className="p-6 space-y-4">
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />
                        <span>
                            You are about to reject the payment for <strong>Order #{order.id}</strong>.
                            This will cancel the order and notify the customer.
                        </span>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                            Rejection Reason <span className="text-gray-400">(optional)</span>
                        </label>
                        <textarea
                            value={reason}
                            onChange={e => setReason(e.target.value)}
                            placeholder="e.g. Transaction ID mismatch, unclear screenshot, insufficient amount..."
                            rows={4}
                            maxLength={1000}
                            className="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"
                        />
                        <p className="text-xs text-gray-400 mt-1 text-right">{reason.length}/1000</p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3 pt-2">
                        <button
                            onClick={() => onConfirm(order)}
                            disabled={processing}
                            className="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                        >
                            {processing ? (
                                <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                            ) : <ShieldX className="w-4 h-4" />}
                            {processing ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                        <button
                            onClick={onClose}
                            disabled={processing}
                            className="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ScreenshotPreview({ url, fallbackText }) {
    const [lightboxOpen, setLightboxOpen] = useState(false);

    if (!url || url.includes('placeholder')) {
        return (
            <div className="border border-dashed border-gray-300 rounded-xl p-6 text-center text-gray-400 text-sm">
                <Image className="w-8 h-8 mx-auto mb-1 opacity-50" />
                {fallbackText || 'No screenshot uploaded'}
            </div>
        );
    }

    return (
        <>
            <div
                className="relative w-40 h-40 rounded-xl overflow-hidden border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity group"
                onClick={() => setLightboxOpen(true)}
            >
                <img src={url} alt="Payment screenshot" className="w-full h-full object-cover" />
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                    <Search className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                </div>
            </div>

            {lightboxOpen && (
                <div
                    className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setLightboxOpen(false)}
                >
                    <button
                        className="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
                        onClick={() => setLightboxOpen(false)}
                    >
                        <X className="w-6 h-6 text-white" />
                    </button>
                    <img
                        src={url}
                        alt="Payment screenshot full"
                        className="max-w-full max-h-full rounded-xl object-contain"
                        onClick={e => e.stopPropagation()}
                    />
                </div>
            )}
        </>
    );
}

export default function PaymentReport({ orders, summary, paymentMethods, codMethodId, filters }) {
    const baseUrl = '/admin/reports/payments';
    const today = new Date().toISOString().split('T')[0];

    const [form, setForm] = useState({
        date_from:          filters.date_from || today,
        date_to:            filters.date_to || today,
        payment_method_id:  filters.payment_method_id || '',
        payment_status:     filters.payment_status || '',
        verification_status: filters.verification_status || '',
        search:             filters.search || '',
        search_by:          filters.search_by || '',
    });

    const [verifyTarget, setVerifyTarget] = useState(null);
    const [rejectTarget, setRejectTarget] = useState(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = useCallback((params) => {
        router.get(baseUrl + '?' + params.toString(), {}, { preserveState: true, preserveScroll: true });
    }, [baseUrl]);

    const handleSubmit = useCallback((e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => { if (v) params.set(k, v); });
        submit(params);
    }, [form, submit]);

    const handleReset = useCallback(() => {
        setForm({
            date_from: today, date_to: today,
            payment_method_id: '', payment_status: '', verification_status: '',
            search: '', search_by: '',
        });
        const params = new URLSearchParams();
        params.set('date_from', today);
        params.set('date_to', today);
        submit(params);
    }, [today, submit]);

    const handlePerPage = useCallback((val) => {
        const params = new URLSearchParams(window.location.search);
        params.set('per_page', val);
        params.delete('page');
        window.location.href = baseUrl + '?' + params.toString();
    }, [baseUrl]);

    const handleVerify = useCallback((order) => {
        setProcessing(true);
        router.post(`/admin/reports/payments/${order.id}/verify`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setVerifyTarget(null);
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    }, []);

    const handleRejectStart = useCallback((order) => {
        setRejectTarget(order);
        setRejectionReason('');
    }, []);

    const handleRejectConfirm = useCallback((order) => {
        setProcessing(true);
        router.post(`/admin/reports/payments/${order.id}/reject`, {
            rejection_reason: rejectionReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectTarget(null);
                setRejectionReason('');
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    }, [rejectionReason]);

    const params = new URLSearchParams(window.location.search);
    const perPage = params.get('per_page') || '25';

    return (
        <AdminLayout>
            <Head title="Payment Report" />
            <FlashToast />

            <div className="max-w-[1600px] mx-auto px-4 sm:px-5 lg:px-6 py-6 lg:py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payment Report</h1>
                    <p className="text-sm text-gray-500 mt-1">Transaction tracking and payment verification overview</p>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 space-y-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <Filter className="w-4 h-4" />
                        Filters
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
                            <input type="date" value={form.date_from}
                                onChange={e => setForm(p => ({ ...p, date_from: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
                            <input type="date" value={form.date_to}
                                onChange={e => setForm(p => ({ ...p, date_to: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Payment Method</label>
                            <select value={form.payment_method_id}
                                onChange={e => setForm(p => ({ ...p, payment_method_id: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Methods</option>
                                {(paymentMethods || []).map(pm => (
                                    <option key={pm.id} value={pm.id}>
                                        {pm.name}{pm.bank_name ? ` (${pm.bank_name})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Payment Status</label>
                            <select value={form.payment_status}
                                onChange={e => setForm(p => ({ ...p, payment_status: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Verification Status</label>
                            <select value={form.verification_status}
                                onChange={e => setForm(p => ({ ...p, verification_status: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                <option value="unchecked">Unchecked</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Search By</label>
                            <select value={form.search_by}
                                onChange={e => setForm(p => ({ ...p, search_by: e.target.value }))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Fields</option>
                                <option value="order_id">Order ID</option>
                                <option value="transaction_id">Transaction ID</option>
                            </select>
                        </div>
                        <div className="lg:col-span-2">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
                            <div className="flex gap-2">
                                <input type="text" value={form.search}
                                    onChange={e => setForm(p => ({ ...p, search: e.target.value }))}
                                    placeholder="Order ID or Transaction ID..."
                                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                <button type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors flex items-center gap-1.5 shrink-0">
                                    <Search className="w-3.5 h-3.5" />
                                    <span className="hidden sm:inline">Search</span>
                                </button>
                                <button type="button" onClick={handleReset}
                                    className="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-3 lg:gap-4">
                    <Card icon={DollarSign}     label="Total Payment Volume" value={formatCurrency(summary?.total_payment_amount)} color="blue" />
                    <Card icon={CheckCircle}    label="Verified Amount"     value={formatCurrency(summary?.verified_amount)}       color="emerald" />
                    <Card icon={Clock}          label="Pending Review"      value={Number(summary?.pending_verification_count || 0).toLocaleString() + ' orders'} color="amber" />
                    <Card icon={Banknote}       label="Net Received"        value={formatCurrency(summary?.net_received)}          color="blue" />
                    <Card icon={ArrowLeftRight} label="Refunded Amount"     value={formatCurrency(summary?.refunded_amount)}       color="purple" />
                    <Card icon={Banknote}       label="COD"                 value={formatCurrency(summary?.cod_amount) + ' / ' + Number(summary?.cod_count || 0).toLocaleString() + ' orders'} color="slate" />
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-gray-500" />
                            <h2 className="text-sm font-semibold text-gray-700">Payment Transactions</h2>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500">Rows:</span>
                            <div className="relative">
                                <select value={perPage} onChange={e => handlePerPage(e.target.value)}
                                    className="border border-gray-300 rounded-lg py-1.5 pl-3 pr-8 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer appearance-none">
                                    {PER_PAGE_OPTIONS.map(n => (
                                        <option key={n} value={n}>{n}</option>
                                    ))}
                                </select>
                                <ChevronDown className="absolute inset-y-0 right-0 top-1/2 -translate-y-1/2 mr-2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Transaction ID</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Order</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Customer</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Method</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Amount Paid</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3 text-right">Net Received</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Payment</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Verification</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Date</th>
                                    <th className="px-3 sm:px-5 py-2.5 sm:py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {!orders?.data?.length ? (
                                    <tr>
                                        <td colSpan="10" className="px-5 py-16 text-center text-gray-400">
                                            <FileText className="w-10 h-10 mx-auto mb-2 text-gray-300" />
                                            No payment transactions found matching your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => {
                                        const payStatus = derivePaymentStatus(order);
                                        const verStatus = deriveVerificationStatus(order);
                                        const methodName = order.payment_method?.name;
                                        const canVerify = order.can_verify_payment || order.can_approve_payment;
                                        const isVerified = order.payment_status === 'verified';
                                        const isRejected = order.payment_status === 'rejected';
                                        const hasScreenshot = order.payment_screenshot || order.payment_proof;
                                        return (
                                            <tr key={order.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <span className="text-xs sm:text-sm font-mono text-gray-500">
                                                        {order.transaction_id
                                                            ? (order.transaction_id.length > 16
                                                                ? order.transaction_id.substring(0, 16) + '...'
                                                                : order.transaction_id)
                                                            : <span className="text-gray-300">&mdash;</span>
                                                        }
                                                    </span>
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <span className="text-xs sm:text-sm font-mono font-medium text-gray-900">
                                                        #{order.id}
                                                    </span>
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <p className="text-xs sm:text-sm font-medium text-gray-900 truncate max-w-[140px]">
                                                        {order.first_name
                                                            ? `${order.first_name} ${order.last_name}`
                                                            : '—'}
                                                    </p>
                                                    {order.payer_name && (
                                                        <p className="text-xs text-gray-400 truncate max-w-[140px]">{order.payer_name}</p>
                                                    )}
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <div className="flex items-center gap-2">
                                                        <MethodIcon name={methodName} />
                                                        <span className="text-xs sm:text-sm text-gray-700 truncate max-w-[80px]">
                                                            {methodName || '—'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-900 text-right tabular-nums font-medium">
                                                    {order.paid_amount ? formatCurrency(order.paid_amount) : formatCurrency(order.total_amount)}
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-600 text-right tabular-nums">
                                                    {formatCurrency(order.total_amount)}
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <Badge config={paymentStatusConfig} value={payStatus} />
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <Badge config={verificationStatusConfig} value={verStatus} />
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5 text-xs sm:text-sm text-gray-500 tabular-nums whitespace-nowrap">
                                                    {(order.payment_verified_at || order.created_at)?.substring(0, 10) || '—'}
                                                </td>
                                                <td className="px-3 sm:px-5 py-3 sm:py-3.5">
                                                    <div className="flex items-center gap-1.5">
                                                        {canVerify && !isVerified && !isRejected && (
                                                            <>
                                                                <button
                                                                    onClick={() => setVerifyTarget(order)}
                                                                    className="flex items-center gap-1 px-2 sm:px-3 py-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded-lg transition-colors whitespace-nowrap"
                                                                    title={`Verify payment for Order #${order.id}`}
                                                                >
                                                                    <ShieldCheck className="w-3.5 h-3.5" />
                                                                    <span className="hidden sm:inline">Verify</span>
                                                                </button>
                                                                <button
                                                                    onClick={() => handleRejectStart(order)}
                                                                    className="flex items-center gap-1 px-2 sm:px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-600 hover:text-white rounded-lg transition-colors whitespace-nowrap"
                                                                    title={`Reject payment for Order #${order.id}`}
                                                                >
                                                                    <ShieldX className="w-3.5 h-3.5" />
                                                                    <span className="hidden sm:inline">Reject</span>
                                                                </button>
                                                            </>
                                                        )}
                                                        {isVerified && (
                                                            <span className="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 rounded-lg">
                                                                <CheckCircle className="w-3.5 h-3.5" />
                                                                <span>Done</span>
                                                            </span>
                                                        )}
                                                        <Link href={`/admin/orders/${order.id}`}
                                                            className="flex items-center gap-1 px-2 sm:px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-white bg-blue-50 hover:bg-blue-600 rounded-lg transition-colors whitespace-nowrap">
                                                            <Eye className="w-3.5 h-3.5" />
                                                            <span className="hidden sm:inline">View</span>
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {orders?.last_page > 1 && (
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-200 px-4 sm:px-5 py-4">
                            <p className="text-xs sm:text-sm text-gray-500">
                                Showing {orders.from} to {orders.to} of {orders.total.toLocaleString()} results
                            </p>
                            <div className="flex items-center gap-1">
                                {(() => {
                                    function pageHref(page) {
                                        const p = new URLSearchParams(window.location.search);
                                        p.set('page', String(page));
                                        return baseUrl + '?' + p.toString();
                                    }
                                    const last = orders.last_page;
                                    const current = orders.current_page;
                                    const pages = [];
                                    const rangeLimit = 2;
                                    const start = Math.max(1, current - rangeLimit);
                                    const end = Math.min(last, current + rangeLimit);
                                    if (start > 1) { pages.push(1); if (start > 2) pages.push('...'); }
                                    for (let i = start; i <= end; i++) pages.push(i);
                                    if (end < last) { if (end < last - 1) pages.push('...'); pages.push(last); }
                                    return (
                                        <>
                                            {current > 1 && (
                                                <Link preserveScroll href={pageHref(current - 1)}
                                                    className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">&laquo;</Link>
                                            )}
                                            {pages.map((p, i) =>
                                                p === '...' ? (
                                                    <span key={`e${i}`} className="px-1.5 py-1.5 text-xs text-gray-400">...</span>
                                                ) : (
                                                    <Link key={p} preserveScroll href={pageHref(p)}
                                                        className={`px-2 sm:px-3 py-1.5 text-xs sm:text-sm rounded-lg transition-colors ${p === current
                                                            ? 'bg-blue-600 text-white'
                                                            : 'text-gray-600 hover:bg-gray-100'
                                                        }`}>{p}</Link>
                                                )
                                            )}
                                            {current < last && (
                                                <Link preserveScroll href={pageHref(current + 1)}
                                                    className="px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">&raquo;</Link>
                                            )}
                                        </>
                                    );
                                })()}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <VerifyModal
                order={verifyTarget}
                onClose={() => { if (!processing) setVerifyTarget(null); }}
                onVerify={handleVerify}
                onReject={(order) => { setVerifyTarget(null); handleRejectStart(order); }}
                processing={processing}
            />

            <RejectModal
                order={rejectTarget}
                onClose={() => { if (!processing) setRejectTarget(null); }}
                onConfirm={handleRejectConfirm}
                reason={rejectionReason}
                setReason={setRejectionReason}
                processing={processing}
            />
        </AdminLayout>
    );
}
