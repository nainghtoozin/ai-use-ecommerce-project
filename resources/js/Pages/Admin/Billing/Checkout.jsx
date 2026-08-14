import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Check, ChevronDown, Upload, ShieldCheck } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';
import { useTranslation } from '@/Utils/useTranslation';

function today() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export default function AdminBillingCheckout({ intent, selectedPlan, currentPlan, subscription, allFeatureDefs, paymentMethods = [] }) {
    const { props } = usePage();
    const { t } = useTranslation();
    const pc = getPlatformCurrencyConfig(props.platform_setting);
    const [selectedMethod, setSelectedMethod] = useState(null);
    const [senderName, setSenderName] = useState('');
    const [senderAccount, setSenderAccount] = useState('');
    const [transactionReference, setTransactionReference] = useState('');
    const [evidence, setEvidence] = useState(null);
    const [note, setNote] = useState('');
    const [termsAccepted, setTermsAccepted] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const transferDate = today();
    const amount = Number(intent?.amount ?? 0);
    const interval = intent?.billing_cycle || 'monthly';

    const submit = (event) => {
        event.preventDefault();
        if (!termsAccepted || !selectedMethod || !senderName.trim() || !senderAccount.trim() || !transactionReference.trim() || !evidence) {
            setError(t('billing.checkout_complete_required'));
            return;
        }

        const form = new FormData();
        form.append('intent_reference', intent?.reference_number || '');
        form.append('sender_name', senderName.trim());
        form.append('sender_account', senderAccount.trim());
        form.append('transaction_reference', transactionReference.trim());
        form.append('transferred_amount', String(amount));
        form.append('transfer_date', transferDate);
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

    const featureList = selectedPlan?.features?.filter((feature) => feature.enabled) || [];

    return (
        <AdminLayout>
            <Head title={t('billing.checkout')} />
            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-5xl mx-auto">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{t('billing.checkout')}</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{t('billing.checkout_review')}</p>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-5">
                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 sm:p-6">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{t('billing.order_summary')}</h2>
                            <div className="mt-4 flex items-start justify-between gap-4">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100">{selectedPlan?.name}</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{selectedPlan?.description}</p>
                                </div>
                                <div className="text-right shrink-0">
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{formatCurrency(amount, pc)}</p>
                                    <p className="text-xs text-gray-500 capitalize">{interval}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5 text-sm">
                                <div><span className="text-gray-500">{t('billing.subscription_start')}</span><p className="font-medium">{subscription?.starts_at || today()}</p></div>
                                <div><span className="text-gray-500">{t('billing.subscription_expiry')}</span><p className="font-medium">{subscription?.expires_at || t('billing.no_expiry')}</p></div>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-sm">
                                {[
                                    ['product_limit', t('billing.products')],
                                    ['staff_limit', t('billing.staff')],
                                    ['storage_limit', t('billing.storage')],
                                    ['orders_monthly_limit', t('billing.monthly_orders')],
                                ].map(([key, label]) => (
                                    <div key={key}><span className="text-gray-500">{label}</span><p className="font-medium">{selectedPlan?.limits?.[key] === null ? 'Unlimited' : selectedPlan?.limits?.[key] ?? '—'}</p></div>
                                ))}
                            </div>
                            <button type="button" onClick={() => setDetailsOpen(!detailsOpen)} className="mt-5 inline-flex items-center gap-2 text-sm font-medium text-blue-600">
                                {t('billing.view_plan_details')} <ChevronDown className={`w-4 h-4 transition-transform ${detailsOpen ? 'rotate-180' : ''}`} />
                            </button>
                            {detailsOpen && (
                                <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 border-t border-gray-100 dark:border-gray-800 pt-4">
                                    {featureList.map((feature) => {
                                        const definition = allFeatureDefs?.find((item) => item.key === feature.key);
                                        return <div key={feature.key} className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"><Check className="w-4 h-4 text-emerald-500" />{definition?.label || feature.key}</div>;
                                    })}
                                </div>
                            )}
                        </section>

                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 sm:p-6">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{t('billing.payment_method')}</h2>
                            <div className="mt-4 space-y-3">
                                {paymentMethods.map((method) => (
                                    <button type="button" key={method.id} onClick={() => setSelectedMethod(method.id)} className={`w-full text-left rounded-lg border-2 p-4 ${selectedMethod === method.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 dark:border-gray-700'}`}>
                                        <div className="flex items-center justify-between"><span className="font-semibold">{method.bank_name || method.name}</span><span className={`w-4 h-4 rounded-full border-2 ${selectedMethod === method.id ? 'border-blue-500 bg-blue-500' : 'border-gray-300'}`} /></div>
                                        <p className="text-sm text-gray-500 mt-1">{method.account_name} · {method.account_number}</p>
                                        {method.instructions && <p className="text-xs text-gray-400 mt-2">{method.instructions}</p>}
                                    </button>
                                ))}
                            </div>
                        </section>

                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 sm:p-6">
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{t('billing.payment_information')}</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                <input value={senderName} onChange={(event) => setSenderName(event.target.value)} placeholder={t('billing.account_holder_name')} className="px-3 py-2 text-sm border border-gray-300 rounded-lg" />
                                <input value={senderAccount} onChange={(event) => setSenderAccount(event.target.value)} placeholder={t('billing.sender_account')} className="px-3 py-2 text-sm border border-gray-300 rounded-lg" />
                                <input value={transactionReference} onChange={(event) => setTransactionReference(event.target.value)} placeholder={t('billing.transaction_reference')} className="px-3 py-2 text-sm border border-gray-300 rounded-lg sm:col-span-2" />
                                <label className="text-sm text-gray-500">{t('billing.payment_date')}<input value={transferDate} readOnly className="mt-1 w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg" /></label>
                                <label className="text-sm text-gray-500">{t('billing.payment_evidence')}<input type="file" accept="image/*" onChange={(event) => setEvidence(event.target.files?.[0] || null)} className="mt-1 w-full text-sm" /></label>
                                <textarea value={note} onChange={(event) => setNote(event.target.value.slice(0, 500))} placeholder={t('billing.payment_note')} rows={2} className="px-3 py-2 text-sm border border-gray-300 rounded-lg sm:col-span-2" />
                            </div>
                        </section>
                    </div>

                    <aside className="space-y-5">
                        <section className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                            <h2 className="text-base font-semibold">{t('billing.final_amount')}</h2>
                            <div className="flex items-center justify-between mt-4"><span className="text-gray-500">{selectedPlan?.name} · {interval}</span><strong>{formatCurrency(amount, pc)}</strong></div>
                        </section>
                        <section className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800"><ShieldCheck className="w-5 h-5 mb-2" />{t('billing.terms_text')}</section>
                        <label className="flex items-start gap-2 text-sm text-gray-600"><input type="checkbox" checked={termsAccepted} onChange={(event) => setTermsAccepted(event.target.checked)} className="mt-0.5" />{t('billing.terms_agree')}</label>
                        {error && <p className="text-sm text-red-600">{error}</p>}
                        <button type="submit" disabled={submitting || !termsAccepted} className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"><Upload className="w-4 h-4" />{submitting ? t('billing.submitting') : t('billing.confirm_payment')}</button>
                        <Link href={adminUrl('/admin/billing/upgrade')} className="block text-center text-sm text-gray-500 hover:text-gray-700">{t('billing.back_to_plans')}</Link>
                    </aside>
                </form>
            </div>
        </AdminLayout>
    );
}
