import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Billing/StatusBadge';
import { Check, Copy, CheckCheck, ArrowRight, Clock, ShieldCheck, Upload, Eye, Zap } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';
import { formatCurrency, getPlatformCurrencyConfig } from '@/Utils/currency';

const pc = getPlatformCurrencyConfig(usePage().props.platform_setting);

function formatBytes(v) {
    if (v === null || v === undefined) return null;
    if (v >= 1024) return (v / 1024).toFixed(1) + ' GB';
    return v + ' MB';
}

function ProgressBar({ value, max }) {
    const pct = max > 0 ? Math.min(Math.round((value / max) * 100), 100) : 0;
    return (
        <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div className="h-full rounded-full bg-blue-500" style={{ width: `${pct}%` }} />
        </div>
    );
}

function StepIcon({ step, current }) {
    if (step < current) return <Check className="w-4 h-4 text-white" />;
    if (step === current) return <div className="w-4 h-4 bg-blue-600 rounded-full border-2 border-blue-200" />;
    return <div className="w-4 h-4 bg-gray-100 rounded-full border-2 border-gray-200" />;
}

function StepLine({ step, current }) {
    return (
        <div className={`h-0.5 flex-1 mx-2 ${step <= current ? 'bg-blue-500' : 'bg-gray-200'}`} />
    );
}

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
        <button
            onClick={handleCopy}
            className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
            aria-label={`Copy reference number ${text}`}
        >
            {copied ? <CheckCheck className="w-3.5 h-3.5 text-green-600" /> : <Copy className="w-3.5 h-3.5" />}
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}

const steps = [
    { label: 'Plan Selection', key: 'plan' },
    { label: 'Checkout', key: 'checkout' },
    { label: 'Payment', key: 'payment' },
    { label: 'Review', key: 'review' },
];

export default function AdminBillingCheckout({ intent, selectedPlan, currentPlan, subscription, allFeatureDefs, plans }) {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions || [];
    const can = (perm) => permissions.includes(perm);

    const yearlySavingsAmount = selectedPlan?.monthly_price && selectedPlan?.yearly_price
        ? (parseFloat(selectedPlan.monthly_price) * 12) - parseFloat(selectedPlan.yearly_price)
        : 0;
    const hasYearlySavings = yearlySavingsAmount > 0;

    const handleContinue = () => {
        router.get(adminUrl('/admin/billing/payment'), {
            intent: intent?.reference_number,
            plan: selectedPlan?.slug,
        }, { preserveState: false });
    };

    const handleBack = () => {
        router.get(adminUrl('/admin/billing/upgrade'), {}, { preserveState: false });
    };

    const gainedFeatures = (() => {
        if (!currentPlan || !selectedPlan || !allFeatureDefs) return [];
        const currentKeys = currentPlan.features?.filter(f => f.enabled).map(f => f.key) || [];
        const targetKeys = selectedPlan.features?.filter(f => f.enabled).map(f => f.key) || [];
        const gained = targetKeys.filter(k => !currentKeys.includes(k));
        return allFeatureDefs.filter(d => gained.includes(d.key));
    })();

    return (
        <AdminLayout>
            <Head title="Checkout" />

            <div className="p-6 lg:p-8 space-y-6 max-w-4xl mx-auto">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Checkout</h1>
                    <p className="text-sm text-gray-500 mt-1">Review your order before proceeding to payment</p>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 sm:p-6">
                    <div className="flex items-center justify-between">
                        {steps.map((s, i) => (
                            <div key={s.key} className="flex items-center">
                                <div className="flex flex-col items-center gap-1.5">
                                    <StepIcon step={i} current={1} />
                                    <span className={`text-[10px] font-medium ${i <= 1 ? 'text-blue-600' : 'text-gray-400'}`}>{s.label}</span>
                                </div>
                                {i < steps.length - 1 && <StepLine step={i} current={1} />}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Order Summary</h2>
                            </div>
                            <div className="p-6">
                                <div className="flex items-start justify-between mb-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-lg font-bold text-gray-900">{selectedPlan?.name}</h3>
                                            <StatusBadge status={subscription?.status || 'active'} size="sm" />
                                        </div>
                                        <p className="text-sm text-gray-500 mt-0.5">{selectedPlan?.description}</p>
                                    </div>
                                </div>

                                <div className="flex items-baseline gap-1 mb-6">
                                    <span className="text-3xl font-extrabold text-gray-900">
                                        {selectedPlan?.monthly_price === 0 ? 'Free' :
                                            selectedPlan?.monthly_price !== null ? formatCurrency(selectedPlan.monthly_price, pc) : '—'}
                                    </span>
                                    {selectedPlan?.monthly_price > 0 && (
                                        <span className="text-sm text-gray-400">/month</span>
                                    )}
                                    {hasYearlySavings && (
                                        <span className="ml-2 px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-700 rounded-full">
                                            Save {formatCurrency(yearlySavingsAmount, pc)}/yr
                                        </span>
                                    )}
                                </div>

                                {intent && (
                                    <div className="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                                        <div>
                                            <span className="text-xs text-gray-400">Reference Number</span>
                                            <p className="text-sm font-mono font-semibold text-gray-900">{intent.reference_number}</p>
                                        </div>
                                        <CopyButton text={intent.reference_number} />
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">What's Included</h2>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-6">
                                    {selectedPlan?.features?.map(f => {
                                        const def = allFeatureDefs?.find(d => d.key === f.key);
                                        return (
                                            <div key={f.key} className="flex items-center gap-2">
                                                {f.enabled ? (
                                                    <span className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                                        <Check className="w-3 h-3 text-emerald-600" />
                                                    </span>
                                                ) : (
                                                    <span className="w-4 h-4 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                        <div className="w-2 h-0.5 bg-gray-300 rounded" />
                                                    </span>
                                                )}
                                                <span className={`text-sm ${f.enabled ? 'text-gray-900' : 'text-gray-400'}`}>{def?.label || f.key}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Plan Limits</h2>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {selectedPlan?.limits && Object.entries({
                                        product_limit: 'Products',
                                        staff_limit: 'Staff',
                                        storage_limit: 'Storage',
                                        orders_monthly_limit: 'Monthly Orders',
                                        coupon_limit: 'Coupons',
                                        promotion_limit: 'Promotions',
                                        flash_sale_limit: 'Flash Sales',
                                    }).map(([key, label]) => {
                                        const val = selectedPlan.limits[key];
                                        const unlimited = val === null;
                                        const display = unlimited ? 'Unlimited' : key === 'storage_limit' ? formatBytes(val) : val?.toLocaleString();
                                        return (
                                            <div key={key}>
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="text-sm text-gray-600">{label}</span>
                                                    <span className={`text-sm font-semibold ${unlimited ? 'text-blue-600' : 'text-gray-900'}`}>{display}</span>
                                                </div>
                                                {!unlimited && val > 0 && <ProgressBar value={val} max={val} />}
                                                {unlimited && <div className="w-full h-2 bg-gradient-to-r from-blue-200 to-blue-100 rounded-full opacity-60" />}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        {currentPlan && gainedFeatures.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h2 className="text-base font-semibold text-gray-900">What You'll Gain</h2>
                                </div>
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-4 p-3 bg-blue-50 rounded-lg">
                                        <ArrowRight className="w-5 h-5 text-blue-500 flex-shrink-0" />
                                        <div className="text-sm">
                                            <span className="font-medium text-gray-900">{currentPlan.name}</span>
                                            <span className="text-gray-400 mx-2">→</span>
                                            <span className="font-medium text-blue-600">{selectedPlan?.name}</span>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {gainedFeatures.map(f => (
                                            <div key={f.key} className="flex items-center gap-2 text-sm">
                                                <Zap className="w-4 h-4 text-amber-500 flex-shrink-0" />
                                                <span className="text-gray-700">{f.label}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Next Steps</h2>
                            </div>
                            <div className="p-6">
                                <div className="space-y-0">
                                    {[
                                        { icon: Check, color: 'text-emerald-500', bg: 'bg-emerald-100', label: 'Confirm Checkout', desc: 'Review and confirm your plan selection' },
                                        { icon: Upload, color: 'text-blue-500', bg: 'bg-blue-100', label: 'Upload Payment Evidence', desc: 'Submit your payment receipt or transaction screenshot' },
                                        { icon: Eye, color: 'text-purple-500', bg: 'bg-purple-100', label: 'Admin Review', desc: 'Our team verifies your payment (typically within 24 hours)' },
                                        { icon: ShieldCheck, color: 'text-green-500', bg: 'bg-green-100', label: 'Subscription Activated', desc: 'Your store is upgraded with full access' },
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
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="text-base font-semibold text-gray-900">Price Breakdown</h2>
                            </div>
                            <div className="p-6">
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-600">{selectedPlan?.name} Plan</span>
                                        <span className="font-semibold text-gray-900">
                                            {selectedPlan?.monthly_price === 0 ? 'Free' :
                                                selectedPlan?.monthly_price !== null ? formatCurrency(selectedPlan.monthly_price, pc) : '—'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-400">Billing Cycle</span>
                                        <span className="font-medium text-gray-700 capitalize">{intent?.billing_cycle || 'monthly'}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-400">Currency</span>
                                        <span className="font-medium text-gray-700">{intent?.currency || 'MMK'}</span>
                                    </div>
                                    <div className="border-t border-gray-100 pt-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-semibold text-gray-900">Subtotal</span>
                                            <span className="text-sm font-semibold text-gray-900">
                                                {selectedPlan?.monthly_price === 0 ? 'Free' :
                                                    selectedPlan?.monthly_price !== null ? formatCurrency(selectedPlan.monthly_price, pc) : '—'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between text-xs text-gray-400">
                                        <span>Tax</span>
                                        <span>Calculated at payment</span>
                                    </div>
                                    <div className="border-t border-gray-100 pt-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-base font-bold text-gray-900">Total</span>
                                            <span className="text-base font-bold text-gray-900">
                                                {selectedPlan?.monthly_price === 0 ? 'Free' :
                                                    selectedPlan?.monthly_price !== null ? formatCurrency(selectedPlan.monthly_price, pc) : '—'}
                                            </span>
                                        </div>
                                        {hasYearlySavings && (
                                            <p className="text-xs text-emerald-600 font-medium mt-1 text-right">
                                                Save {formatCurrency(yearlySavingsAmount, pc)}/yr with yearly billing
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {currentPlan && (
                            <div className="bg-white rounded-xl border border-gray-200">
                                <div className="px-6 py-4 border-b border-gray-100">
                                    <h3 className="text-sm font-semibold text-gray-900">Plan Comparison</h3>
                                </div>
                                <div className="p-4">
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500">Current</span>
                                            <span className="font-semibold text-gray-700">{currentPlan.name}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500">Selected</span>
                                            <span className="font-semibold text-blue-600">{selectedPlan?.name}</span>
                                        </div>
                                    </div>
                                    <div className="mt-4 space-y-2.5">
                                        {[
                                            { label: 'Products', current: currentPlan.product_limit, selected: selectedPlan?.product_limit, fmt: v => v === null ? '∞' : v?.toLocaleString() },
                                            { label: 'Staff', current: currentPlan.staff_limit, selected: selectedPlan?.staff_limit, fmt: v => v === null ? '∞' : v?.toLocaleString() },
                                            { label: 'Storage', current: currentPlan.storage_limit, selected: selectedPlan?.storage_limit, fmt: v => v === null ? '∞' : formatBytes(v) },
                                        ].map(({ label, current, selected, fmt }) => {
                                            const better = selected === null || (selected !== null && current !== null && selected >= current);
                                            return (
                                                <div key={label} className="flex items-center justify-between">
                                                    <span className="text-xs text-gray-500">{label}</span>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs text-gray-400">{fmt(current)}</span>
                                                        <ArrowRight className="w-3 h-3 text-gray-300" />
                                                        <span className={`text-xs font-semibold ${better ? 'text-green-600' : 'text-gray-700'}`}>{fmt(selected)}</span>
                                                        {better && selected !== current && <Check className="w-3 h-3 text-green-500" />}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="bg-blue-50 rounded-xl border border-blue-200 p-4">
                            <div className="flex items-start gap-2.5">
                                <ShieldCheck className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="text-sm font-semibold text-blue-800">Payment is NOT processed on this page</p>
                                    <p className="text-xs text-blue-600 mt-1">
                                        After continuing, you will upload payment evidence through our secure portal.
                                        Our team will review your payment and activate your subscription.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <button
                                onClick={handleContinue}
                                className="w-full px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Continue to Payment
                            </button>
                            <button
                                onClick={handleBack}
                                className="w-full px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
                            >
                                Back to Plans
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
