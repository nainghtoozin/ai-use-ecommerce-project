import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';
import { assetUrl } from '@/Utils/helpers';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';
 
export default function Checkout({ cartItems, subtotal, paymentMethods, cities, errors, appliedPromotion: initialAppliedPromotion, discountAmount: initialDiscountAmount, autoPromotions }) {
    const { auth, platform_setting, website_info } = usePage().props;
    const cc = getCurrencyConfig(platform_setting, website_info);
    const [localAppliedPromotion, setLocalAppliedPromotion] = useState(initialAppliedPromotion || null);
    const [localDiscount, setLocalDiscount] = useState(initialDiscountAmount || 0);
    const [promotionCode, setPromotionCode] = useState('');
    const [promoLoading, setPromoLoading] = useState(false);
    const [promoMessage, setPromoMessage] = useState(null);
    const [promoError, setPromoError] = useState(false);

    useEffect(() => {
        setLocalAppliedPromotion(initialAppliedPromotion || null);
        setLocalDiscount(initialDiscountAmount || 0);
    }, [initialAppliedPromotion, initialDiscountAmount]);

    async function applyPromotion(code) {
        if (!code?.trim()) return;
        setPromoLoading(true);
        setPromoMessage(null);
        setPromoError(false);
        try {
            const res = await axios.post('/cart/apply-promotion', { code });
            if (res.data?.success) {
                setLocalAppliedPromotion({
                    code: res.data.promotion_code,
                    name: res.data.promotion_name,
                    discount: res.data.discount,
                });
                setLocalDiscount(prev => Number(prev) + Number(res.data.discount));
                setPromotionCode('');
                setPromoMessage(res.data.message || 'Promotion applied!');
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to apply promotion.';
            setPromoMessage(msg);
            setPromoError(true);
        } finally {
            setPromoLoading(false);
            setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
        }
    }

    async function removePromotion() {
        setPromoLoading(true);
        setPromoMessage(null);
        setPromoError(false);
        try {
            const res = await axios.post('/cart/remove-promotion');
            if (res.data?.success) {
                const removedDiscount = localAppliedPromotion?.discount || 0;
                setLocalAppliedPromotion(null);
                setLocalDiscount(prev => Math.max(0, Number(prev) - Number(removedDiscount)));
                setPromoMessage(res.data.message || 'Promotion removed.');
            }
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to remove promotion.';
            setPromoMessage(msg);
            setPromoError(true);
        } finally {
            setPromoLoading(false);
            setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
        }
    }
    const [step, setStep] = useState(1);
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState(errors || {});

    const [form, setForm] = useState({
        first_name: auth?.user?.name?.split(' ')[0] || '',
        last_name: auth?.user?.name?.split(' ').slice(1).join(' ') || '',
        email: auth?.user?.email || '',
        phone: '',
        address: '',
        city_id: '',
        township_id: '',
        postal_code: '',
        notes: '',
        payment_method_id: '',
        payer_name: '',
        transaction_id: '',
        payment_screenshot: null,
    });

    const [townships, setTownships] = useState([]);
    const [copiedId, setCopiedId] = useState(null);
    const [screenshotPreview, setScreenshotPreview] = useState(null);
    const [qrPreview, setQrPreview] = useState(null);
    const [fileError, setFileError] = useState('');
    const [selectedFileName, setSelectedFileName] = useState('');
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploading, setUploading] = useState(false);

    function copyToClipboard(text, id) {
        navigator.clipboard.writeText(text).then(() => {
            setCopiedId(id);
            setTimeout(() => setCopiedId(null), 2000);
        }).catch(() => {});
    }

    function updateField(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));
        setFormErrors((prev) => ({ ...prev, [field]: null }));
    }

    function handleScreenshotFile(e) {
        const file = e.target.files[0];
        setFileError('');
        setSelectedFileName('');

        if (file) {
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                setFileError('Only JPG, PNG, and WebP images are accepted.');
                e.target.value = '';
                return;
            }

            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                setFileError('File is too large. Maximum size is 5MB.');
                e.target.value = '';
                return;
            }

            setSelectedFileName(`${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB)`);
            updateField('payment_screenshot', file);
            const reader = new FileReader();
            reader.onloadend = () => setScreenshotPreview(reader.result);
            reader.readAsDataURL(file);
        } else {
            updateField('payment_screenshot', null);
            setScreenshotPreview(null);
        }
    }

    function handleRemoveFile() {
        updateField('payment_screenshot', null);
        setScreenshotPreview(null);
        setSelectedFileName('');
        setFileError('');
    }

    function fetchTownships(cityId) {
        if (!cityId) {
            setTownships([]);
            return;
        }
        axios.get(`/api/townships/${cityId}`).then((res) => {
            setTownships(res.data?.townships || []);
        }).catch(() => {
            setTownships([]);
        });
    }

    const city = cities?.find((c) => c.id == form.city_id);
    const selectedPaymentMethod = paymentMethods?.find((pm) => pm.id == form.payment_method_id);
    const deliveryFee = city?.delivery_fee || 0;
    const totalDiscount = Number(localDiscount) || 0;
    const total = Number(subtotal) + Number(deliveryFee) - totalDiscount;
    const totalItems = cartItems?.reduce((s, i) => s + i.quantity, 0) || 0;

    function handleSubmit(e) {
        e.preventDefault();
        setSubmitting(true);
        setFormErrors({});
        setUploading(true);
        setUploadProgress(0);

        const progressInterval = setInterval(() => {
            setUploadProgress((prev) => {
                if (prev >= 90) {
                    clearInterval(progressInterval);
                    return 90;
                }
                return prev + Math.random() * 15;
            });
        }, 300);

        router.post('/checkout', form, {
            preserveScroll: true,
            onError: (errs) => {
                clearInterval(progressInterval);
                setUploadProgress(0);
                setUploading(false);
                setFormErrors(errs);
                setSubmitting(false);
            },
            onSuccess: () => {
                clearInterval(progressInterval);
                setUploadProgress(100);
                setTimeout(() => router.visit('/orders'), 400);
            },
        });
    }

    if (!cartItems?.length) {
        return (
            <ShopLayout>
                <div className="max-w-7xl mx-auto px-4 py-16 text-center">
                    <h2 className="text-xl font-medium text-gray-900">Your cart is empty</h2>
                    <Link href="/cart" className="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        View Cart
                    </Link>
                </div>
            </ShopLayout>
        );
    }

return (
        <ShopLayout>
            <Head title="Checkout" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 className="text-xl sm:text-2xl font-bold text-gray-900 mb-6 sm:mb-8">Checkout</h1>

                {Object.values(formErrors).filter(Boolean).map((err, i) => (
                    <div key={i} className="mb-4 sm:mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                        {err}
                    </div>
                ))}

                {auth?.user?.subscription_expired && (
                    <div className="mb-4 sm:mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                        <svg className="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span>Your subscription has expired. Please renew your subscription to place orders.</span>
                    </div>
                )}
                {auth?.user?.subscription_past_due && (
                    <div className="mb-4 sm:mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                        <svg className="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span>Your subscription payment is past due. Please update your billing information to avoid service interruption.</span>
                    </div>
                )}

                {/* Steps indicator */}
                <div className="flex items-center mb-6 sm:mb-8">
                    {['Shipping', 'Payment', 'Review'].map((label, i) => (
                        <div key={label} className="flex items-center flex-1">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium ${
                                step > i + 1 ? 'bg-green-600 text-white' : step === i + 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'
                            }`}>
                                {step > i + 1 ? '✓' : i + 1}
                            </div>
                            <span className={`ml-2 text-sm font-medium ${step === i + 1 ? 'text-blue-600' : 'text-gray-500'}`}>
                                {label}
                            </span>
                            {i < 2 && <div className={`flex-1 h-0.5 mx-4 ${step > i + 1 ? 'bg-green-600' : 'bg-gray-200'}`}></div>}
                        </div>
                    ))}
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Step 1: Shipping */}
                    {step === 1 && (
                        <div className="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                            <h2 className="text-lg font-semibold text-gray-900">Shipping Information</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                    <input type="text" value={form.first_name} onChange={(e) => updateField('first_name', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {formErrors.first_name && <p className="text-red-500 text-xs mt-1">{formErrors.first_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                    <input type="text" value={form.last_name} onChange={(e) => updateField('last_name', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {formErrors.last_name && <p className="text-red-500 text-xs mt-1">{formErrors.last_name}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" value={form.email} onChange={(e) => updateField('email', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                    <input type="tel" value={form.phone} onChange={(e) => updateField('phone', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    {formErrors.phone && <p className="text-red-500 text-xs mt-1">{formErrors.phone}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                                <textarea value={form.address} onChange={(e) => updateField('address', e.target.value)} rows="2" className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
                                {formErrors.address && <p className="text-red-500 text-xs mt-1">{formErrors.address}</p>}
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                    <select value={form.city_id} onChange={(e) => { updateField('city_id', e.target.value); fetchTownships(e.target.value); setForm((p) => ({ ...p, township_id: '' })); }} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select City</option>
                                        {cities?.map((c) => <option key={c.id} value={c.id}>{c.name} ({formatCurrency(c.delivery_fee, cc)})</option>)}
                                    </select>
                                    {formErrors.city_id && <p className="text-red-500 text-xs mt-1">{formErrors.city_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Township *</label>
                                    <select value={form.township_id} onChange={(e) => updateField('township_id', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Township</option>
                                        {townships.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                                    <input type="text" value={form.postal_code} onChange={(e) => updateField('postal_code', e.target.value)} className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Order Notes</label>
                                <textarea value={form.notes} onChange={(e) => updateField('notes', e.target.value)} rows="2" className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Special instructions..." />
                            </div>
                            <div className="flex justify-end pt-4 border-t">
                                <button type="button" onClick={() => setStep(2)} className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Continue to Payment →</button>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Payment */}
                    {step === 2 && (
                        <div className="space-y-4">
                            <h2 className="text-xl sm:text-2xl font-bold text-gray-900">Payment Method</h2>
                            {formErrors.payment_method_id && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                                    {formErrors.payment_method_id}
                                </div>
                            )}

                            <div className="space-y-3">
                                {paymentMethods?.map((pm) => {
                                    const isSelected = form.payment_method_id == pm.id;
                                    return (
                                        <div
                                            key={pm.id}
                                            onClick={() => updateField('payment_method_id', pm.id)}
                                            className={`rounded-xl border-2 transition-all cursor-pointer select-none ${
                                                isSelected
                                                    ? 'border-blue-500 bg-blue-50/50 shadow-sm'
                                                    : 'border-gray-200 bg-white hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="p-4">
                                                <div className="flex items-center gap-3">
                                                    <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors ${
                                                        isSelected ? 'border-blue-500' : 'border-gray-300'
                                                    }`}>
                                                        {isSelected && <div className="w-2.5 h-2.5 rounded-full bg-blue-500" />}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="font-medium text-gray-900">{pm.name}</p>
                                                        {pm.description && <p className="text-sm text-gray-500 mt-0.5">{pm.description}</p>}
                                                    </div>
                                                    <svg className={`w-5 h-5 transition-transform ${isSelected ? 'rotate-90 text-blue-500' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>

                                                {isSelected && (
                                                    <>
                                                        {pm.type === 'cod' ? (
                                                            <div className="mt-4 pt-4 border-t border-blue-200">
                                                                <div className="bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                                                                    <p className="text-sm text-green-800 font-medium">Cash on Delivery</p>
                                                                    <p className="text-sm text-green-700 mt-1">Pay when your order is delivered.</p>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <div className="mt-4 pt-4 border-t border-blue-200">
                                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                                        <div className="space-y-3">
                                                                            <div>
                                                                                <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Account Name</p>
                                                                                <p className="text-sm font-semibold text-gray-900 mt-0.5">{pm.account_name || 'N/A'}</p>
                                                                            </div>
                                                                            <div>
                                                                                <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Account Number</p>
                                                                                <div className="flex items-center gap-2 mt-0.5">
                                                                                    <p className="text-sm font-semibold text-gray-900">{pm.account_number || 'N/A'}</p>
                                                                                    {pm.account_number && (
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={(e) => { e.stopPropagation(); copyToClipboard(pm.account_number, pm.id); }}
                                                                                            className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-md transition-colors ${
                                                                                                copiedId === pm.id
                                                                                                    ? 'bg-green-100 text-green-700'
                                                                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                                                            }`}
                                                                                        >
                                                                                            {copiedId === pm.id ? (
                                                                                                <><svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg> Copied</>
                                                                                            ) : (
                                                                                                <><svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg> Copy</>
                                                                                            )}
                                                                                        </button>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                            {pm.bank_name && (
                                                                                <div>
                                                                                    <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Bank</p>
                                                                                    <p className="text-sm font-semibold text-gray-900 mt-0.5">{pm.bank_name}</p>
                                                                                </div>
                                                                            )}
                                                                        </div>
 {pm.qr_image_url && (
                                                                              <div className="flex justify-center sm:justify-end items-start mt-3 sm:mt-0">
                                                                                  <div className="text-center sm:text-right">
                                                                                      <p className="text-xs text-gray-500 uppercase tracking-wide font-medium mb-2">Scan to Pay</p>
                                                                                      <button type="button" onClick={(e) => { e.stopPropagation(); setQrPreview(pm.qr_image_url); }} className="block">
                                                                                          <img
                                                                                              src={pm.qr_image_url}
                                                                                              alt={`${pm.name} QR`}
                                                                                              className="w-36 h-36 sm:w-44 sm:h-44 rounded-xl border border-gray-200 object-cover cursor-pointer hover:opacity-90 transition-opacity"
                                                                                          />
                                                                                      </button>
                                                                                      <button type="button" onClick={(e) => { e.stopPropagation(); setQrPreview(pm.qr_image_url); }}
                                                                                          className="text-xs text-blue-600 hover:text-blue-800 mt-1 font-medium">
                                                                                          Tap to enlarge
                                                                                      </button>
                                                                                  </div>
                                                                              </div>
                                                                          )}
                                                                    </div>
                                                                    <div className="mt-4 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                                                        <p className="text-xs font-semibold text-blue-800 uppercase tracking-wide mb-2">How to Pay</p>
                                                                        <ol className="text-xs text-blue-700 space-y-1.5 list-decimal list-inside leading-relaxed">
                                                                            <li>Transfer the <strong>exact amount</strong> to the account above</li>
                                                                            <li>Take a <strong>clear screenshot</strong> of your payment confirmation</li>
                                                                            <li>Enter the <strong>sender account name</strong> and <strong>transaction ID</strong></li>
                                                                            <li>Upload the screenshot below to complete your order</li>
                                                                        </ol>
                                                                    </div>
                                                                </div>

                                                                <div className="mt-4 pt-4 border-t border-blue-200 space-y-4">
                                                                    <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Transfer Details</p>
                                                                    <div>
                                                                        <label htmlFor="payer_name" className="block text-sm font-medium text-gray-700 mb-1">Sender Account Name</label>
                                                                        <input id="payer_name" type="text" value={form.payer_name} onChange={(e) => updateField('payer_name', e.target.value)}
                                                                            placeholder="Name on your bank/wallet account"
                                                                            className="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                                                        {formErrors.payer_name && <p className="mt-1 text-xs text-red-600">{formErrors.payer_name}</p>}
                                                                    </div>
                                                                    <div>
                                                                        <label htmlFor="transaction_id" className="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                                                                        <input id="transaction_id" type="text" value={form.transaction_id} onChange={(e) => updateField('transaction_id', e.target.value)}
                                                                            placeholder="Transaction reference number"
                                                                            className="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                                                        {formErrors.transaction_id && <p className="mt-1 text-xs text-red-600">{formErrors.transaction_id}</p>}
                                                                    </div>
                                                                    <div>
                                                                        <label htmlFor="payment_screenshot" className="block text-sm font-medium text-gray-700 mb-1">Payment Screenshot</label>
                                                                        <input id="payment_screenshot" type="file" accept="image/jpeg,image/png,image/webp"
                                                                            onChange={handleScreenshotFile}
                                                                            className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
                                                                        {fileError && <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1"><svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>{fileError}</p>}
                                                                        {selectedFileName && !fileError && (
                                                                            <p className="mt-1.5 text-xs text-gray-500 flex items-center gap-1">
                                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                                                {selectedFileName}
                                                                            </p>
                                                                        )}
                                                                        {formErrors.payment_screenshot && <p className="mt-1 text-xs text-red-600">{formErrors.payment_screenshot}</p>}
                                                                        {screenshotPreview && (
                                                                            <div className="mt-3 flex items-start gap-3">
                                                                                <div className="relative">
                                                                                    <img src={screenshotPreview} alt="Payment Screenshot Preview" className="w-32 h-32 sm:w-36 sm:h-36 rounded-lg border border-gray-200 object-cover" />
                                                                                    <button type="button" onClick={handleRemoveFile}
                                                                                        className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-0.5 shadow hover:bg-red-600 transition-colors">
                                                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        )}
                                                                    </div>

                                                                    <div className="flex items-start gap-2.5 bg-gray-50 rounded-lg px-4 py-3">
                                                                        <svg className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                                        </svg>
                                                                        <p className="text-xs text-gray-500 leading-relaxed">
                                                                            Your payment proof will be reviewed by our team. We will notify you once your payment is verified.
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="flex justify-between pt-2">
                                <button type="button" onClick={() => setStep(1)} className="px-6 py-2.5 text-gray-600 hover:text-gray-900 font-medium transition-colors">← Back</button>
                                <button
                                    type="button"
                                    onClick={() => setStep(3)}
                                    disabled={!form.payment_method_id}
                                    className="px-6 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    Review Order →
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Review */}
                    {step === 3 && (
                        <div className="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8 items-start">
                            {/* Left column - Review details */}
                            <div className="lg:col-span-3 space-y-6">
                                <h2 className="text-xl sm:text-2xl font-bold text-gray-900">Review Order</h2>

                                {/* Shipping Information */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-base font-semibold text-gray-900 flex items-center gap-2">
                                            <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            Shipping To
                                        </h3>
                                        <button type="button" onClick={() => setStep(1)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                                    </div>
                                    <div className="space-y-1.5 text-sm">
                                        <p className="font-medium text-gray-900">{form.first_name} {form.last_name}</p>
                                        <p className="text-gray-600">{form.phone}</p>
                                        <p className="text-gray-600">{form.address}</p>
                                        {form.city_id && city && (
                                            <p className="text-gray-600">
                                                {city.name}
                                                {form.township_id ? `, ${townships.find(t => t.id == form.township_id)?.name || ''}` : ''}
                                                {form.postal_code ? ` - ${form.postal_code}` : ''}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Items */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 className="text-base font-semibold text-gray-900 mb-4">Items ({totalItems})</h3>
                                    <div className="divide-y divide-gray-100">
                                        {cartItems.map((item) => (
                                            <div key={item.cart_key || item.id} className="flex gap-4 py-3 first:pt-0 last:pb-0">
                                                {item.photo1_url && (
                                                    <div className="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                                        <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                                                    </div>
                                                )}
                                                <div className="flex-1 min-w-0 self-center">
                                                    <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                                                    {item.variant_name && (
                                                        <span className="inline-block mt-0.5 px-1.5 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded text-xs font-medium">
                                                            {item.variant_name}
                                                        </span>
                                                    )}
                                                    <p className="text-xs text-gray-500 mt-0.5">Qty: {item.quantity}</p>
                                                </div>
                                                <p className="text-sm font-semibold text-gray-900 whitespace-nowrap self-center">
                                                    {formatCurrency(Number(item.price) * Number(item.quantity), cc)}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Right column - Order Summary (sticky) */}
                            <div className="lg:col-span-2 lg:sticky lg:top-24">
                                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 className="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
                                        <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                        Order Summary
                                    </h3>

                                    {/* Payment Method */}
                                    <div className="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Payment</p>
                                            <p className="text-sm font-medium text-gray-900 mt-0.5">
                                                {paymentMethods?.find(pm => pm.id == form.payment_method_id)?.name || 'Not selected'}
                                            </p>
                                        </div>
                                        <button type="button" onClick={() => setStep(2)} className="text-xs text-blue-600 hover:text-blue-800 font-medium">Change</button>
                                    </div>

                                    {/* Totals */}
                                    <div className="space-y-3">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Subtotal ({totalItems} item{totalItems !== 1 ? 's' : ''})</span>
                                            <span className="text-gray-900 font-medium">{formatCurrency(subtotal, cc)}</span>
                                        </div>

                                        {totalDiscount > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="flex items-center gap-1.5 text-emerald-600">
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    Discount
                                                </span>
                                                <span className="font-medium text-emerald-600">-{formatCurrency(totalDiscount, cc)}</span>
                                            </div>
                                        )}

                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Delivery Fee</span>
                                            <span className={`font-medium ${deliveryFee > 0 ? 'text-gray-900' : 'text-green-600'}`}>
                                                {deliveryFee > 0 ? `${formatCurrency(deliveryFee, cc)}` : 'Free'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Savings Summary */}
                                    {totalDiscount > 0 && (
                                        <div className="mt-4 pt-4 border-t border-gray-200">
                                            <div className="bg-gradient-to-r from-emerald-50 to-green-50 rounded-xl border border-emerald-200 p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <p className="text-sm font-bold text-emerald-800">You Save</p>
                                                </div>
                                                <p className="text-2xl font-extrabold text-emerald-700">
                                                    {formatCurrency(totalDiscount, cc)}
                                                </p>
                                                <p className="text-xs text-emerald-600 mt-0.5">
                                                    on this order with applied discounts
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {/* Promotion Section */}
                                    <div className="mt-4 pt-4 border-t border-gray-200 space-y-3">

                                        {/* Auto-apply Promotions */}
                                        {autoPromotions?.length > 0 && !localAppliedPromotion && (
                                            <div>
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Available Promotions</p>
                                                <div className="space-y-2">
                                                    {autoPromotions.map((ap) => (
                                                        <div key={ap.id} className="flex items-center justify-between bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl px-4 py-3">
                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="px-2 py-0.5 bg-blue-600 text-white text-xs font-bold rounded-md">
                                                                        {ap.type === 'percentage' ? `${ap.value}%` : ap.type === 'fixed' ? `${formatCurrency(ap.discount, cc)}` : 'FREE'}
                                                                    </span>
                                                                    <p className="text-sm font-semibold text-gray-900 truncate">{ap.name}</p>
                                                                </div>
                                                                <p className="text-xs text-gray-500 mt-1 ml-1">
                                                                    {ap.type === 'percentage' ? `${ap.value}% Off` : ap.type === 'fixed' ? `${formatCurrency(ap.discount, cc)} Off` : 'Free Shipping'}
                                                                    {ap.description ? ` — ${ap.description}` : ''}
                                                                </p>
                                                            </div>
                                                            <button type="button" onClick={() => applyPromotion(ap.code)}
                                                                disabled={promoLoading}
                                                                className="ml-3 px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors shrink-0 shadow-sm">
                                                                Apply
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Promotion Code Input */}
                                        {!localAppliedPromotion && (
                                            <div>
                                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Have a promo code?</p>
                                                <div className="flex gap-2">
                                                    <input type="text" value={promotionCode} onChange={e => setPromotionCode(e.target.value)}
                                                        placeholder="Enter code" maxLength={50}
                                                        className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                        onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), applyPromotion(promotionCode))} />
                                                    <button type="button" onClick={() => applyPromotion(promotionCode)}
                                                        disabled={promoLoading || !promotionCode.trim()}
                                                        className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                        {promoLoading ? (
                                                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                            </svg>
                                                        ) : 'Apply'}
                                                    </button>
                                                </div>
                                            </div>
                                        )}

                                        {/* Applied Promotion */}
                                        {localAppliedPromotion && (
                                            <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                                                <div className="flex items-center justify-between">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <svg className="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            <p className="text-sm font-semibold text-emerald-800 truncate">{localAppliedPromotion.name || 'Promotion'}</p>
                                                            <span className="px-1.5 py-0.5 bg-emerald-600 text-white text-xs font-bold rounded">-{formatCurrency(localAppliedPromotion.discount, cc)}</span>
                                                        </div>
                                                        <div className="flex items-center gap-2 mt-1 ml-5">
                                                            <span className="text-xs font-mono font-medium text-emerald-700">{localAppliedPromotion.code}</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => copyToClipboard(localAppliedPromotion.code, 'promo')}
                                                                className={`text-xs font-medium px-1.5 py-0.5 rounded transition-colors ${
                                                                    copiedId === 'promo' ? 'text-green-700' : 'text-gray-400 hover:text-gray-600'
                                                                }`}
                                                                title="Copy code"
                                                            >
                                                                {copiedId === 'promo' ? (
                                                                    <><svg className="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg> Copied</>
                                                                ) : (
                                                                    <><svg className="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg> Copy</>
                                                                )}
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <button type="button" onClick={removePromotion}
                                                        disabled={promoLoading}
                                                        className="ml-3 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors shrink-0">
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        )}

                                        {/* Promotion Feedback Message */}
                                        {promoMessage && (
                                            <div className={`text-xs px-3 py-2 rounded-lg flex items-center gap-1.5 transition-all duration-300 ${
                                                promoError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                            }`}>
                                                <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    {promoError ? (
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    ) : (
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    )}
                                                </svg>
                                                {promoMessage}
                                            </div>
                                        )}
                                    </div>

                                    {/* Divider & Total */}
                                    <div className="mt-4 pt-4 border-t border-gray-200">
                                        <div className="flex justify-between items-baseline">
                                            <span className="text-base font-bold text-gray-900">Total</span>
                                            <span className="text-xl font-extrabold text-gray-900">{formatCurrency(total, cc)}</span>
                                        </div>
                                    </div>

                                    {/* Action buttons */}
                                    <div className="mt-6 space-y-3">
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            className="w-full py-3.5 bg-green-600 text-white rounded-xl font-semibold text-base hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2 shadow-sm"
                                        >
                                            {submitting ? (
                                                <>
                                                    <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Placing Order...
                                                </>
                                            ) : (
                                                `Place Order — ${formatCurrency(total, cc)}`
                                            )}
                                        </button>
                                        {uploading && (
                                            <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                                <div
                                                    className="bg-green-500 h-full rounded-full transition-all duration-300 ease-out"
                                                    style={{ width: `${Math.min(uploadProgress, 100)}%` }}
                                                />
                                            </div>
                                        )}
                                        {uploading && (
                                            <p className="text-xs text-gray-400 text-center">
                                                {form.payment_screenshot ? 'Uploading payment proof...' : 'Processing your order...'}
                                            </p>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => setStep(2)}
                                            className="w-full py-2.5 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
                                        >
                                            ← Back to Payment
                                        </button>
                                    </div>

                                    {/* Trust message */}
                                    <p className="mt-5 pt-4 border-t border-gray-100 text-xs text-gray-400 text-center leading-relaxed">
                                        Your payment proof will be reviewed by our team. Your order will be securely processed.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                </form>

                {/* QR Preview Modal */}
                {qrPreview && (
                    <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" onClick={() => setQrPreview(null)}>
                        <div className="relative max-w-md w-full" onClick={(e) => e.stopPropagation()}>
                            <button onClick={() => setQrPreview(null)}
                                className="absolute -top-3 -right-3 bg-white rounded-full p-1 shadow-lg hover:bg-gray-100 z-10">
                                <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <img src={qrPreview} alt="QR Code Full Size"
                                className="w-full rounded-lg shadow-2xl" />
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
