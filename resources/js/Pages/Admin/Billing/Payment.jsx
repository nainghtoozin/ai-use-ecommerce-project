import { useState, useRef, useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Check, Copy, CheckCheck, Upload, X, AlertCircle, ShieldCheck, Clock, Banknote, ArrowLeft, Building2, FileImage, Loader2 } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import { CURRENCY_SYMBOL } from '@/Utils/currency';

function CopyButton({ text }) {
    const [copied, setCopied] = useState(false);
    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        }
    };
    return (
        <button onClick={handleCopy}
            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
            aria-label={`Copy ${text}`}
        >
            {copied ? <CheckCheck className="w-3.5 h-3.5 text-green-600" /> : <Copy className="w-3.5 h-3.5" />}
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}

const statusConfig = {
    draft: { label: 'Draft', classes: 'bg-gray-100 text-gray-600' },
    pending: { label: 'Pending', classes: 'bg-blue-100 text-blue-700' },
    waiting_payment: { label: 'Waiting Payment', classes: 'bg-amber-100 text-amber-700' },
    waiting_review: { label: 'Waiting Review', classes: 'bg-purple-100 text-purple-700' },
    approved: { label: 'Approved', classes: 'bg-emerald-100 text-emerald-700' },
    paid: { label: 'Paid', classes: 'bg-emerald-100 text-emerald-700' },
    completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
    rejected: { label: 'Rejected', classes: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelled', classes: 'bg-gray-100 text-gray-600' },
    expired: { label: 'Expired', classes: 'bg-gray-100 text-gray-600' },
    failed: { label: 'Failed', classes: 'bg-red-100 text-red-700' },
};

function StatusBadge({ status }) {
    const cfg = statusConfig[status] || statusConfig.draft;
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${cfg.classes}`}>
            {cfg.label}
        </span>
    );
}

function PaymentMethodCard({ method, selectedId, onSelect, intentRef }) {
    const isSelected = selectedId === method.id;
    return (
        <div
            onClick={() => onSelect(method.id)}
            className={`relative rounded-xl border-2 p-4 cursor-pointer transition-all duration-200 ${
                isSelected ? 'border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'
            }`}
            role="radio"
            aria-checked={isSelected}
            tabIndex={0}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSelect(method.id); } }}
        >
            <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-2">
                    <div className="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center">
                        <Building2 className="w-4 h-4 text-blue-600" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{method.bank_name || method.name}</p>
                        <p className="text-xs text-gray-500">{method.name}</p>
                    </div>
                </div>
                <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${isSelected ? 'border-blue-500' : 'border-gray-300'}`}>
                    {isSelected && <div className="w-2.5 h-2.5 rounded-full bg-blue-500" />}
                </div>
            </div>

            <div className="space-y-2 text-sm">
                <div className="flex items-center justify-between">
                    <span className="text-gray-500">Account Name</span>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900">{method.account_name}</span>
                        {method.account_name && <CopyButton text={method.account_name} />}
                    </div>
                </div>
                <div className="flex items-center justify-between">
                    <span className="text-gray-500">Account Number</span>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900 font-mono">{method.account_number}</span>
                        {method.account_number && <CopyButton text={method.account_number} />}
                    </div>
                </div>
            </div>

            {method.qr_image_url && (
                <div className="mt-3 pt-3 border-t border-gray-100">
                    <img src={method.qr_image_url} alt={`${method.name} QR code`} className="w-24 h-24 mx-auto rounded-lg object-contain" />
                </div>
            )}

            <div className="mt-3 pt-3 border-t border-gray-100">
                <p className="text-xs text-gray-400 mb-1.5">Reference to include:</p>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                    <span className="text-xs font-mono font-semibold text-gray-900">{intentRef || '—'}</span>
                    {intentRef && <CopyButton text={intentRef} />}
                </div>
            </div>
        </div>
    );
}

function UploadArea({ file, onFileSelect, onFileRemove, error }) {
    const dropRef = useRef(null);
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);

    const handleDrop = (e) => {
        e.preventDefault();
        setDragging(false);
        const f = e.dataTransfer.files[0];
        if (f) onFileSelect(f);
    };

    const handleChange = (e) => {
        const f = e.target.files[0];
        if (f) onFileSelect(f);
    };

    const previewUrl = file ? URL.createObjectURL(file) : null;

    return (
        <div
            ref={dropRef}
            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
            onDragLeave={() => setDragging(false)}
            onDrop={handleDrop}
            onClick={() => inputRef.current?.click()}
            className={`relative rounded-xl border-2 border-dashed p-6 text-center cursor-pointer transition-all duration-200 ${
                dragging ? 'border-blue-400 bg-blue-50' : error ? 'border-red-300 bg-red-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50/50'
            }`}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inputRef.current?.click(); } }}
            aria-label="Upload payment evidence"
        >
            <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/jpg,image/gif" onChange={handleChange} className="hidden" />

            {file && previewUrl ? (
                <div className="relative inline-block" onClick={(e) => e.stopPropagation()}>
                    <img src={previewUrl} alt="Payment evidence preview" className="max-h-48 rounded-lg mx-auto object-contain shadow-sm" />
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); onFileRemove(); }}
                        className="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-red-600 transition-colors"
                        aria-label="Remove image"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                    <p className="text-xs text-gray-500 mt-2">{file.name} ({(file.size / 1024).toFixed(1)} KB)</p>
                </div>
            ) : (
                <div>
                    <div className="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <FileImage className="w-6 h-6 text-gray-400" />
                    </div>
                    <p className="text-sm font-medium text-gray-700">Click to upload or drag and drop</p>
                    <p className="text-xs text-gray-400 mt-1">PNG, JPG or GIF — Max 5MB</p>
                </div>
            )}
        </div>
    );
}

export default function AdminBillingPayment({ intent, selectedPlan, currentPlan, subscription, paymentMethods }) {
    const { props } = usePage();
    const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const submitted = urlParams.get('submitted') === 'true';
    const flash = props?.flash || {};

    const [selectedMethod, setSelectedMethod] = useState(null);
    const [senderName, setSenderName] = useState('');
    const [senderAccount, setSenderAccount] = useState('');
    const [transactionReference, setTransactionReference] = useState('');
    const [transferredAmount, setTransferredAmount] = useState('');
    const [transferDate, setTransferDate] = useState('');
    const [evidenceFile, setEvidenceFile] = useState(null);
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [uploadError, setUploadError] = useState(null);

    const isWaitingReview = intent?.status === 'waiting_review' || submitted;
    const isTerminal = ['completed', 'approved', 'paid', 'cancelled', 'expired', 'failed'].includes(intent?.status);
    const isRejected = intent?.status === 'rejected';

    const errors = {};

    function validateForm() {
        const errs = [];
        if (!senderName.trim()) errs.push('Account holder name is required.');
        if (!senderAccount.trim()) errs.push('Sender account/phone is required.');
        if (!transactionReference.trim()) errs.push('Transaction reference is required.');
        if (!transferredAmount || parseFloat(transferredAmount) <= 0) errs.push('Enter a valid transferred amount greater than zero.');
        if (!transferDate) errs.push('Transfer date is required.');
        if (transferDate && new Date(transferDate) > new Date()) errs.push('Transfer date cannot be in the future.');
        if (!evidenceFile) errs.push('Receipt image is required.');
        if (!selectedMethod) errs.push('Please select a payment method.');
        return errs;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setUploadError(null);

        const errs = validateForm();
        if (errs.length > 0) {
            setUploadError(errs.join(' | '));
            return;
        }

        setSubmitting(true);

        const formData = new FormData();
        formData.append('intent_reference', intent?.reference_number || '');
        formData.append('sender_name', senderName.trim());
        formData.append('sender_account', senderAccount.trim());
        formData.append('transaction_reference', transactionReference.trim());
        formData.append('transferred_amount', transferredAmount);
        formData.append('transfer_date', transferDate);
        formData.append('evidence', evidenceFile);
        formData.append('note', note);
        if (selectedMethod) formData.append('payment_method_id', selectedMethod);

        try {
            router.post(adminUrl('/admin/billing/payment/submit'), formData, {
                preserveScroll: true,
                onError: (errs) => {
                    setUploadError(Object.values(errs).join(', '));
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            });
        } catch {
            setUploadError('Failed to submit payment. Please try again.');
            setSubmitting(false);
        }
    };

    if (!intent) {
        return (
            <AdminLayout>
                <Head title="Payment" />
                <div className="p-6 lg:p-8 space-y-6 max-w-2xl mx-auto">
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <AlertCircle className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h2 className="text-lg font-semibold text-gray-900 mb-1">Payment Intent Not Found</h2>
                        <p className="text-sm text-gray-500 mb-4">The payment you're looking for could not be found. Please start a new checkout.</p>
                        <button
                            onClick={() => router.get(adminUrl('/admin/billing/upgrade'))}
                            className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                        >
                            Back to Plans
                        </button>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    if (isTerminal && !isWaitingReview && !isRejected) {
        return (
            <AdminLayout>
                <Head title="Payment" />
                <div className="p-6 lg:p-8 space-y-6 max-w-2xl mx-auto">
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <div className="w-16 h-16 rounded-2xl bg-green-100 flex items-center justify-center mx-auto mb-4">
                            <Check className="w-8 h-8 text-green-600" />
                        </div>
                        <h2 className="text-xl font-bold text-gray-900 mb-2">Payment {intent.status === 'completed' ? 'Completed' : intent.status === 'approved' ? 'Approved' : intent.status === 'paid' ? 'Paid' : intent.status}</h2>
                        <p className="text-sm text-gray-500 mb-2">Reference: <span className="font-mono font-semibold">{intent.reference_number}</span></p>
                        <StatusBadge status={intent.status} />
                        <div className="mt-6">
                            <button
                                onClick={() => router.get(adminUrl('/admin/billing'))}
                                className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                            >
                                Back to Billing
                            </button>
                        </div>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    if (isRejected) {
        return (
            <AdminLayout>
                <Head title="Payment" />
                <div className="p-6 lg:p-8 space-y-6 max-w-2xl mx-auto">
                    <div className="bg-white rounded-xl border border-red-200 p-8 text-center">
                        <div className="w-16 h-16 rounded-2xl bg-red-100 flex items-center justify-center mx-auto mb-4">
                            <X className="w-8 h-8 text-red-600" />
                        </div>
                        <h2 className="text-xl font-bold text-gray-900 mb-2">Payment Rejected</h2>
                        <p className="text-sm text-gray-500 mb-4">Your payment has been reviewed and was not approved. Please contact support for more information.</p>
                        <StatusBadge status={intent.status} />
                        <div className="mt-6 flex gap-3 justify-center">
                            <button
                                onClick={() => router.get(adminUrl('/admin/billing'))}
                                className="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors"
                            >
                                Back to Billing
                            </button>
                            <button
                                onClick={() => router.get(adminUrl('/admin/settings'))}
                                className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                            >
                                Contact Support
                            </button>
                        </div>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    if (isWaitingReview) {
        return (
            <AdminLayout>
                <Head title="Payment Submitted" />
                <div className="p-6 lg:p-8 space-y-6 max-w-2xl mx-auto">
                    <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <div className="w-16 h-16 rounded-2xl bg-purple-100 flex items-center justify-center mx-auto mb-4">
                            <Clock className="w-8 h-8 text-purple-600" />
                        </div>
                        <h2 className="text-xl font-bold text-gray-900 mb-2">Payment Submitted Successfully</h2>
                        <p className="text-sm text-gray-500 mb-4">Your payment evidence has been received and is now awaiting review.</p>
                        <div className="flex items-center justify-center gap-2 mb-6">
                            <span className="text-sm text-gray-400">Reference:</span>
                            <span className="text-sm font-mono font-semibold text-gray-900">{intent.reference_number}</span>
                            <CopyButton text={intent.reference_number} />
                        </div>
                        <StatusBadge status="waiting_review" />
                    </div>

                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-base font-semibold text-gray-900">What Happens Next?</h3>
                        </div>
                        <div className="p-6">
                            <div className="space-y-0">
                                {[
                                    { icon: Upload, color: 'text-green-500', bg: 'bg-green-100', label: 'Payment Submitted', desc: 'Your evidence has been received.' },
                                    { icon: Clock, color: 'text-purple-500', bg: 'bg-purple-100', label: 'Awaiting Review', desc: 'Our team verifies your payment (typically within 24 hours).' },
                                    { icon: ShieldCheck, color: 'text-blue-500', bg: 'bg-blue-100', label: 'Admin Verification', desc: 'An admin reviews and approves your payment.' },
                                    { icon: Check, color: 'text-emerald-500', bg: 'bg-emerald-100', label: 'Subscription Activated', desc: 'Your subscription is activated with upgraded features.' },
                                ].map((step, i) => {
                                    const Icon = step.icon;
                                    return (
                                        <div key={i} className="relative flex items-start gap-4 pb-8 last:pb-0">
                                            {i < 3 && <div className="absolute left-5 top-10 bottom-0 w-px bg-gray-200" />}
                                            <div className={`relative z-10 w-10 h-10 rounded-full ${step.bg} flex items-center justify-center flex-shrink-0`}>
                                                <Icon className={`w-4 h-4 ${step.color}`} />
                                            </div>
                                            <div className="pt-1.5">
                                                <p className="text-sm font-semibold text-gray-900">{step.label}</p>
                                                <p className="text-xs text-gray-500 mt-0.5">{step.desc}</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    <div className="bg-amber-50 rounded-xl border border-amber-200 p-4">
                        <div className="flex items-start gap-2.5">
                            <Clock className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="text-sm font-semibold text-amber-800">Your store remains unchanged until approval.</p>
                                <p className="text-xs text-amber-600 mt-0.5">
                                    Your current subscription will continue to work as before. After approval, your plan will be upgraded automatically.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3 justify-center">
                        <button
                            onClick={() => router.get(adminUrl('/admin/billing'))}
                            className="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                        >
                            Back to Billing
                        </button>
                        <button
                            onClick={() => router.get(adminUrl('/admin/billing/payment-history'))}
                            className="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                        >
                            View Payment History
                        </button>
                    </div>
                </div>
            </AdminLayout>
        );
    }

    const canSubmit = intent?.status === 'waiting_payment';

    return (
        <AdminLayout>
            <Head title="Manual Payment" />

            <div className="p-6 lg:p-8 space-y-6 max-w-4xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900">Manual Payment</h1>
                            <StatusBadge status={intent?.status || 'draft'} />
                        </div>
                        <p className="text-sm text-gray-500 mt-1">Transfer the exact amount and upload your payment evidence</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Select Payment Method</h2>
                            </div>
                            <div className="p-6">
                                {paymentMethods && paymentMethods.length > 0 ? (
                                    <div className="space-y-4" role="radiogroup" aria-label="Payment methods">
                                        {paymentMethods.map(method => (
                                            <PaymentMethodCard
                                                key={method.id}
                                                method={method}
                                                selectedId={selectedMethod}
                                                onSelect={setSelectedMethod}
                                                intentRef={intent?.reference_number}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-6">
                                        <Banknote className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                                        <p className="text-sm text-gray-500">No payment methods are currently available.</p>
                                        <p className="text-xs text-gray-400 mt-1">Please contact support for assistance.</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Payment Instructions</h2>
                            </div>
                            <div className="p-6">
                                <ol className="space-y-3">
                                    {[
                                        { num: '1', label: 'Transfer the exact amount', desc: `Transfer ${CURRENCY_SYMBOL}${intent?.amount || selectedPlan?.monthly_price || '—'} to one of the accounts above.` },
                                        { num: '2', label: 'Use your reference number', desc: `Include your reference number ${intent?.reference_number || ''} in the transfer remarks.` },
                                        { num: '3', label: 'Upload payment evidence', desc: 'Take a screenshot or photo of your transfer confirmation and upload it below.' },
                                        { num: '4', label: 'Submit for review', desc: 'Click Submit Payment to send your evidence to our team.' },
                                        { num: '5', label: 'Wait for admin review', desc: 'Our team will verify your payment within 24 hours.' },
                                        { num: '6', label: 'Subscription activated', desc: 'Your subscription is upgraded immediately after approval.' },
                                    ].map((step) => (
                                        <li key={step.num} className="flex items-start gap-3">
                                            <span className="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5">{step.num}</span>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-900">{step.label}</p>
                                                <p className="text-xs text-gray-500 mt-0.5">{step.desc}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </div>

                        {canSubmit && (
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="bg-white rounded-xl border border-gray-200">
                                    <div className="px-6 py-4 border-b border-gray-100">
                                        <h2 className="text-base font-semibold text-gray-900">Payment Information</h2>
                                    </div>
                                    <div className="p-6 space-y-5">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label htmlFor="sender-name" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Account Holder Name <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    id="sender-name"
                                                    type="text"
                                                    value={senderName}
                                                    onChange={(e) => setSenderName(e.target.value)}
                                                    placeholder="e.g. John Doe"
                                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                    maxLength={255}
                                                />
                                            </div>
                                            <div>
                                                <label htmlFor="sender-account" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Sender Account / Phone <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    id="sender-account"
                                                    type="text"
                                                    value={senderAccount}
                                                    onChange={(e) => setSenderAccount(e.target.value)}
                                                    placeholder="e.g. 09123456789"
                                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                    maxLength={255}
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label htmlFor="transaction-ref" className="block text-sm font-medium text-gray-700 mb-1">
                                                Transaction Reference / Transaction ID <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                id="transaction-ref"
                                                type="text"
                                                value={transactionReference}
                                                onChange={(e) => setTransactionReference(e.target.value)}
                                                placeholder="e.g. TRX20260704123456"
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                maxLength={255}
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label htmlFor="transfer-amount" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Transferred Amount <span className="text-red-500">*</span>
                                                </label>
                                                <div className="relative">
                                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">
                                                        {CURRENCY_SYMBOL}
                                                    </span>
                                                    <input
                                                        id="transfer-amount"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={transferredAmount}
                                                        onChange={(e) => setTransferredAmount(e.target.value)}
                                                        placeholder="0.00"
                                                        className="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label htmlFor="transfer-date" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Transfer Date <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    id="transfer-date"
                                                    type="date"
                                                    value={transferDate}
                                                    onChange={(e) => setTransferDate(e.target.value)}
                                                    max={new Date().toISOString().split('T')[0]}
                                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                                />
                                            </div>
                                        </div>

                                        <UploadArea
                                            file={evidenceFile}
                                            onFileSelect={(f) => { setEvidenceFile(f); setUploadError(null); }}
                                            onFileRemove={() => { setEvidenceFile(null); setUploadError(null); }}
                                            error={uploadError}
                                        />
                                        {uploadError && (
                                            <p className="text-xs text-red-600 flex items-center gap-1">
                                                <AlertCircle className="w-3.5 h-3.5" /> {uploadError}
                                            </p>
                                        )}

                                        <div>
                                            <label htmlFor="payment-note" className="block text-sm font-medium text-gray-700 mb-1">
                                                Remark (optional)
                                            </label>
                                            <textarea
                                                id="payment-note"
                                                value={note}
                                                onChange={(e) => setNote(e.target.value.slice(0, 500))}
                                                rows={2}
                                                placeholder="Any additional information..."
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none transition-shadow"
                                            />
                                            <p className="text-xs text-gray-400 mt-1 text-right">{note.length}/500</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-blue-50 rounded-xl border border-blue-200 p-4">
                                    <div className="flex items-start gap-2.5">
                                        <ShieldCheck className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                                        <div>
                                            <p className="text-sm font-semibold text-blue-800">Why is manual payment safe?</p>
                                            <ul className="text-xs text-blue-600 mt-1 space-y-0.5">
                                                <li>✓ Your payment is reviewed by our team.</li>
                                                <li>✓ Your subscription is activated only after confirmation.</li>
                                                <li>✓ Your payment reference is unique.</li>
                                                <li>✓ Your payment history is permanently recorded.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-col sm:flex-row gap-3">
                                    <button
                                        type="submit"
                                        disabled={submitting || !evidenceFile || !selectedMethod}
                                        className="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                    >
                                        {submitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
                                        {submitting ? 'Submitting...' : 'Submit Payment'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => router.get(adminUrl('/admin/billing/checkout'), {}, { preserveState: false })}
                                        className="flex items-center justify-center gap-2 px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors"
                                    >
                                        <ArrowLeft className="w-4 h-4" />
                                        Back to Checkout
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h3 className="text-base font-semibold text-gray-900">Payment Summary</h3>
                            </div>
                            <div className="p-5 space-y-3">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Plan</span>
                                    <span className="font-semibold text-gray-900">{selectedPlan?.name || '—'}</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Billing</span>
                                    <span className="font-semibold text-gray-900 capitalize">{intent?.billing_cycle || 'monthly'}</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Currency</span>
                                    <span className="font-semibold text-gray-900">{intent?.currency || 'MMK'}</span>
                                </div>
                                <div className="border-t border-gray-100 pt-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-semibold text-gray-900">Total Amount</span>
                                        <span className="text-lg font-bold text-gray-900">
                                            {intent?.amount !== null && intent?.amount !== undefined
                                                ? `${CURRENCY_SYMBOL}${intent.amount}`
                                                : selectedPlan?.monthly_price !== null
                                                    ? `${CURRENCY_SYMBOL}${selectedPlan.monthly_price}`
                                                    : '—'}
                                        </span>
                                    </div>
                                </div>
                                <div className="border-t border-gray-100 pt-3">
                                    <div className="text-xs text-gray-400 mb-1">Reference Number</div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-mono font-semibold text-gray-900 break-all mr-2">{intent?.reference_number}</span>
                                        {intent?.reference_number && <CopyButton text={intent.reference_number} />}
                                    </div>
                                </div>
                                {currentPlan && selectedPlan && (
                                    <div className="border-t border-gray-100 pt-3">
                                        <div className="text-xs text-gray-400 mb-1">Plan Change</div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <span className="font-medium text-gray-700">{currentPlan.name}</span>
                                            <span className="text-gray-300">→</span>
                                            <span className="font-medium text-blue-600">{selectedPlan.name}</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-5">
                            <div className="flex items-start gap-2.5">
                                <ShieldCheck className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="text-sm font-semibold text-blue-800">Trust & Security</p>
                                    <ul className="text-xs text-blue-600 mt-2 space-y-1.5">
                                        <li className="flex items-start gap-1.5">
                                            <Check className="w-3 h-3 text-blue-500 mt-0.5 flex-shrink-0" />
                                            <span>Your payment is reviewed manually.</span>
                                        </li>
                                        <li className="flex items-start gap-1.5">
                                            <Check className="w-3 h-3 text-blue-500 mt-0.5 flex-shrink-0" />
                                            <span>Your reference number is unique.</span>
                                        </li>
                                        <li className="flex items-start gap-1.5">
                                            <Check className="w-3 h-3 text-blue-500 mt-0.5 flex-shrink-0" />
                                            <span>Your payment history is permanently recorded.</span>
                                        </li>
                                        <li className="flex items-start gap-1.5">
                                            <Check className="w-3 h-3 text-blue-500 mt-0.5 flex-shrink-0" />
                                            <span>Your subscription activates only after approval.</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {canSubmit && (
                            <div className="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center">
                                <p className="text-xs text-gray-500">Need help?</p>
                                <button
                                    onClick={() => window.location.href = '#'}
                                    className="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors mt-0.5"
                                >
                                    Contact Support
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
