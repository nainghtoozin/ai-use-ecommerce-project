import { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Upload, X, FileText } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { useTranslation } from '@/Utils/useTranslation';

function today() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function nowTime() {
    const date = new Date();
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

export default function AdminBillingCheckout({ intent, billingCycle, selectedPlan, currentPlan, subscription, allFeatureDefs, paymentMethods = [] }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const pc = getPlatformCurrencyConfig(props.platform_setting);
    const [selectedMethod, setSelectedMethod] = useState(paymentMethods.length === 1 ? paymentMethods[0].id : null);
    const [senderName, setSenderName] = useState('');
    const [senderAccount, setSenderAccount] = useState('');
    const [transactionReference, setTransactionReference] = useState('');
    const [evidence, setEvidence] = useState(null);
    const [note, setNote] = useState('');
    const [termsAccepted, setTermsAccepted] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submissionKey] = useState(() => (
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`
    ));
    const [transferDate, setTransferDate] = useState(today());
    const [transferTime, setTransferTime] = useState(nowTime());
    const [previewUrl, setPreviewUrl] = useState(null);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [fileInputKey, setFileInputKey] = useState(0);
    const previewUrlRef = useRef(null);
    const interval = billingCycle || intent?.billing_cycle || 'monthly';
    const amount = Number(intent?.amount ?? (interval === 'yearly' ? selectedPlan?.yearly_price : selectedPlan?.monthly_price) ?? 0);
    const isImage = !!evidence && (evidence.type || '').startsWith('image/');

    const revokePreview = () => {
        if (previewUrlRef.current) {
            URL.revokeObjectURL(previewUrlRef.current);
            previewUrlRef.current = null;
        }
        setPreviewUrl(null);
    };

    const handleEvidenceChange = (file) => {
        revokePreview();
        setEvidence(file || null);
        if (file && (file.type || '').startsWith('image/')) {
            const url = URL.createObjectURL(file);
            previewUrlRef.current = url;
            setPreviewUrl(url);
        }
    };

    const handleEvidenceRemove = () => {
        revokePreview();
        setEvidence(null);
        setFileInputKey((key) => key + 1);
    };

    useEffect(() => () => {
        if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
    }, []);

    useEffect(() => {
        if (!lightboxOpen) return;
        const onKey = (event) => { if (event.key === 'Escape') setLightboxOpen(false); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [lightboxOpen]);

    const submit = (event) => {
        event.preventDefault();
        if (submitting) return;
        if (!termsAccepted || !selectedMethod || !senderName.trim() || !senderAccount.trim() || !transactionReference.trim() || !transferDate || !evidence) {
            setError(t('billing.checkout_complete_required'));
            return;
        }

        const form = new FormData();
        form.append('intent_reference', intent?.reference_number || '');
        form.append('plan_slug', selectedPlan?.slug || '');
        form.append('billing_cycle', interval);
        form.append('submission_key', submissionKey);
        form.append('sender_name', senderName.trim());
        form.append('sender_account', senderAccount.trim());
        form.append('transaction_reference', transactionReference.trim());
        form.append('transferred_amount', String(amount));
        form.append('transfer_date', transferDate);
        if (transferTime) form.append('transfer_time', transferTime);
        form.append('evidence', evidence);
        form.append('note', note);
        form.append('payment_method_id', String(selectedMethod));

        setSubmitting(true);
        router.post(adminUrl('/admin/billing/payment/submit'), form, {
            preserveScroll: true,
            onError: (errors) => setError(Object.values(errors).join(', ')),
            onFinish: () => setSubmitting(false),
        });
    };

    const inputCls = 'mt-1 w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
    const labelCls = 'block text-sm font-medium text-gray-700 dark:text-gray-300';

    return (
        <AdminLayout>
            <Head title={t('billing.checkout')} />
            <div className="p-4 sm:p-6 lg:p-8 space-y-5 max-w-5xl mx-auto">
                <div>
                    <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('billing.checkout')}</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        {selectedPlan?.name} · <span className="capitalize">{interval}</span>
                        {intent?.reference_number ? ` · Ref ${intent.reference_number}` : ''}
                    </p>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div className="lg:col-span-2 space-y-5">
                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{t('billing.payment_method')}</h2>
                            <div className="mt-3 space-y-2.5">
                                {paymentMethods.map((method) => (
                                    <button type="button" key={method.id} onClick={() => setSelectedMethod(method.id)} aria-pressed={selectedMethod === method.id} className={`w-full text-left rounded-lg border-2 p-3.5 transition-colors ${selectedMethod === method.id ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                        <div className="flex items-center gap-3">
                                            {method.qr_image_url && (
                                                <img src={method.qr_image_url} alt="" className="w-12 h-12 rounded-lg object-cover border border-gray-200 dark:border-gray-700 shrink-0" />
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{method.bank_name || method.name}</span>
                                                    <span className={`w-4 h-4 rounded-full border-2 shrink-0 ${selectedMethod === method.id ? 'border-blue-500 bg-blue-500' : 'border-gray-300 dark:border-gray-600'}`} />
                                                </div>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{method.account_name} · {method.account_number}</p>
                                                {method.instructions && <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{method.instructions}</p>}
                                            </div>
                                        </div>
                                    </button>
                                ))}
                                {paymentMethods.length === 0 && (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">No manual payment methods are available right now. Please contact support.</p>
                                )}
                            </div>
                        </section>

                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{t('billing.payment_information')}</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                <label className={labelCls}>{t('billing.account_holder_name')}
                                    <input value={senderName} onChange={(event) => setSenderName(event.target.value)} placeholder={t('billing.account_holder_name')} autoComplete="name" className={inputCls} />
                                </label>
                                <label className={labelCls}>{t('billing.sender_account')}
                                    <input value={senderAccount} onChange={(event) => setSenderAccount(event.target.value)} placeholder={t('billing.sender_account')} autoComplete="tel" className={inputCls} />
                                </label>
                                <label className={`${labelCls} sm:col-span-2`}>{t('billing.transaction_reference')}
                                    <input value={transactionReference} onChange={(event) => setTransactionReference(event.target.value)} placeholder={t('billing.transaction_reference')} className={`${inputCls} font-mono`} />
                                </label>
                                <label className={labelCls}>{t('billing.payment_date')}
                                    <input type="date" value={transferDate} max={today()} onChange={(event) => setTransferDate(event.target.value)} className={inputCls} />
                                </label>
                                <label className={labelCls}>{t('billing.payment_time')}
                                    <input type="time" value={transferTime} onChange={(event) => setTransferTime(event.target.value)} className={inputCls} />
                                </label>
                                <label className={`${labelCls} sm:col-span-2`}>{t('billing.payment_evidence')}
                                    <input key={fileInputKey} type="file" accept="image/*" onChange={(event) => handleEvidenceChange(event.target.files?.[0] || null)} className="mt-1 w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border file:border-gray-300 dark:file:border-gray-700 file:text-sm file:font-medium file:bg-gray-50 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-100" />
                                    {evidence && (
                                        <div className="mt-2 flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-2.5">
                                            {previewUrl ? (
                                                <button type="button" onClick={() => setLightboxOpen(true)} title="View larger preview" className="shrink-0 cursor-zoom-in">
                                                    <img src={previewUrl} alt="Payment evidence preview" className="w-16 h-16 rounded-lg object-cover border border-gray-200 dark:border-gray-700" />
                                                </button>
                                            ) : (
                                                <span className="w-16 h-16 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0">
                                                    <FileText className="w-6 h-6 text-gray-400" />
                                                </span>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-medium text-gray-700 dark:text-gray-200 truncate" title={evidence.name}>{evidence.name}</p>
                                                <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{previewUrl ? 'Click thumbnail to enlarge' : 'File ready to upload on submit'}</p>
                                            </div>
                                            <button type="button" onClick={handleEvidenceRemove} className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0">
                                                <X className="w-3.5 h-3.5" />Remove
                                            </button>
                                        </div>
                                    )}
                                </label>
                                <label className={`${labelCls} sm:col-span-2`}>{t('billing.payment_note')}
                                    <textarea value={note} onChange={(event) => setNote(event.target.value.slice(0, 500))} placeholder={t('billing.payment_note')} rows={2} className={`${inputCls} resize-none`} />
                                </label>
                            </div>
                        </section>
                    </div>

                    <aside className="lg:sticky lg:top-6 self-start">
                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-5 space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{selectedPlan?.name}</h2>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 capitalize">{interval} billing{intent?.reference_number ? ` · Ref ${intent.reference_number}` : ''}</p>
                            </div>
                            <div className="border-t border-gray-100 dark:border-gray-800 pt-4">
                                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total due</p>
                                <p className="text-3xl font-bold text-blue-600 mt-1">{formatCurrency(amount, pc)}</p>
                            </div>
                            <label className="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <input type="checkbox" checked={termsAccepted} onChange={(event) => setTermsAccepted(event.target.checked)} className="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span>{t('billing.terms_agree')}</span>
                            </label>
                            {error && <p className="text-xs text-red-600 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-2.5">{error}</p>}
                            <button type="submit" disabled={submitting || !termsAccepted} className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <Upload className="w-4 h-4" />{submitting ? 'Submitting…' : 'Submit Payment'}
                            </button>
                            <p className="text-[11px] text-gray-400 dark:text-gray-500 text-center">After submission, our team will review your payment, usually within 24 hours.</p>
                            <Link href={adminUrl('/admin/billing/upgrade')} className="block text-center text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{t('billing.back_to_plans')}</Link>
                        </section>
                    </aside>
                </form>
            </div>

            {lightboxOpen && previewUrl && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" onClick={() => setLightboxOpen(false)}>
                    <button type="button" aria-label="Close preview" onClick={() => setLightboxOpen(false)} className="absolute top-4 right-4 w-9 h-9 inline-flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">
                        <X className="w-5 h-5" />
                    </button>
                    <img src={previewUrl} alt="Payment evidence enlarged preview" className="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain" onClick={(event) => event.stopPropagation()} />
                </div>
            )}
        </AdminLayout>
    );
}
