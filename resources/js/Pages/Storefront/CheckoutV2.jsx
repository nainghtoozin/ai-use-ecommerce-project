import { useState, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';
import { formatCurrency, getCurrencyConfig } from '@/Utils/currency';

const SectionIcon = ({ complete, children }) => (
  <div className={`w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 transition-all ${complete ? 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'}`}>
    {complete ? (
      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
    ) : children}
  </div>
);

const RadioButton = ({ selected }) => (
  <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors flex-shrink-0 ${selected ? 'border-[var(--theme-color)]' : 'border-gray-300 dark:border-gray-600'}`} aria-hidden="true">
    {selected && <div className="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)]" />}
  </div>
);

const SelectionCard = ({ selected, onClick, children, disabled = false }) => (
  <button type="button" onClick={onClick} disabled={disabled}
    className={`w-full text-left p-3.5 sm:p-4 rounded-xl border-2 cursor-pointer transition-all min-h-[52px] focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 ${
      selected
        ? 'border-[var(--theme-color)] bg-[var(--theme-color)]/5 shadow-sm'
        : 'border-gray-100 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900'
    } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
    role="radio" aria-checked={selected}
  >
    {children}
  </button>
);

const feeDisplay = (fee, cc) => {
  if (!fee || fee <= 0) return null;
  return `+${formatCurrency(fee, cc)}`;
};

export default function StorefrontCheckoutV2({
  tenant, cartItems, subtotal, paymentMethods, cities,
  deliveryServices, packagingOptions = [], errors,
  appliedPromotion: initialAppliedPromotion,
  discountAmount: initialDiscountAmount, autoPromotions,
  addresses = [], defaultAddress = null, previewMode = null,
}) {
  const { auth, platform_setting, website_info, storefront } = usePage().props;
  const labels = storefront?.content?.labels || {};
  const checkoutConfig = storefront?.checkout || {};
  const checkoutTitle = checkoutConfig.title || 'Checkout';
  const checkoutSubtitle = checkoutConfig.subtitle || 'Complete your order';
  const buttonLabels = checkoutConfig.button_labels || {};

  const [submitting, setSubmitting] = useState(false);
  const [formErrors, setFormErrors] = useState(errors || {});
  const [submitError, setSubmitError] = useState(null);
  const [mobileSummaryOpen, setMobileSummaryOpen] = useState(false);

  const [localAppliedPromotion, setLocalAppliedPromotion] = useState(initialAppliedPromotion || null);
  const [localDiscount, setLocalDiscount] = useState(initialDiscountAmount || 0);
  const [promotionCode, setPromotionCode] = useState('');
  const [promoLoading, setPromoLoading] = useState(false);
  const [promoMessage, setPromoMessage] = useState(null);
  const [promoError, setPromoError] = useState(false);

  const [form, setForm] = useState({
    first_name: auth?.user?.first_name || auth?.user?.name?.split(' ')[0] || '',
    last_name: auth?.user?.last_name || auth?.user?.name?.split(' ').slice(1).join(' ') || '',
    email: auth?.user?.email || '',
    phone: '', address: '', city_id: '', township_id: '', postal_code: '',
    notes: '', payment_method_id: '', payer_name: '', sender_account_number: '',
    transaction_id: '', payment_date: '', payment_time: '', payment_note: '',
    payment_screenshot: null,
  });

  const [townships, setTownships] = useState([]);
  const [townshipsLoading, setTownshipsLoading] = useState(false);
  const [screenshotPreview, setScreenshotPreview] = useState(null);
  const [selectedFileName, setSelectedFileName] = useState('');
  const [fileError, setFileError] = useState('');
  const [showAddressPicker, setShowAddressPicker] = useState(false);
  const [selectedAddress, setSelectedAddress] = useState(null);
  const [selectedDeliveryService, setSelectedDeliveryService] = useState(null);
  const [selectedPackaging, setSelectedPackaging] = useState(null);

  const cc = getCurrencyConfig(platform_setting, website_info);

  function inputClass(field) {
    const err = formErrors[field];
    return `w-full border rounded-lg px-4 py-2.5 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition-all focus:outline-none focus:ring-2 ${
      err ? 'border-red-400 dark:border-red-500 focus:ring-red-400/40' : 'border-gray-200 dark:border-gray-700 focus:border-[var(--theme-color)] focus:ring-[var(--theme-color)]/30'
    }`;
  }

  useEffect(() => {
    if (defaultAddress) {
      setSelectedAddress(defaultAddress);
      setForm(prev => ({
        ...prev, first_name: defaultAddress.first_name, last_name: defaultAddress.last_name,
        phone: defaultAddress.phone, address: defaultAddress.address_line,
        city_id: defaultAddress.city_id?.toString() || '',
        township_id: defaultAddress.township_id?.toString() || '',
        postal_code: defaultAddress.postal_code || '',
      }));
      if (defaultAddress.city_id) fetchTownships(defaultAddress.city_id).then(data => {
        if (defaultAddress.township_id) {
          const t = data.find(tw => tw.id == defaultAddress.township_id);
          if (t?.postal_code) setForm(prev => ({ ...prev, postal_code: t.postal_code }));
        }
      });
    }
  }, [defaultAddress]);

  useEffect(() => {
    setLocalAppliedPromotion(initialAppliedPromotion || null);
    setLocalDiscount(initialDiscountAmount || 0);
  }, [initialAppliedPromotion, initialDiscountAmount]);

  useEffect(() => {
    if (form.payment_method_id) {
      const pm = paymentMethods?.find(p => p.id == form.payment_method_id);
      if (pm?.type !== 'cod') {
        if (!form.payment_date) {
          const d = new Date();
          updateField('payment_date', d.toISOString().split('T')[0]);
        }
        if (!form.payment_time) {
          const d = new Date();
          updateField('payment_time', d.toTimeString().slice(0, 5));
        }
      }
    }
  }, [form.payment_method_id]);

  function selectAddress(addr) {
    setSelectedAddress(addr);
    setSelectedDeliveryService(null);
    setForm(prev => ({
      ...prev, first_name: addr.first_name, last_name: addr.last_name,
      phone: addr.phone, address: addr.address_line,
      city_id: addr.city_id?.toString() || '',
      township_id: addr.township_id?.toString() || '',
      postal_code: addr.postal_code || '',
    }));
    if (addr.city_id) fetchTownships(addr.city_id).then(data => {
      if (addr.township_id) {
        const t = data.find(tw => tw.id == addr.township_id);
        if (t?.postal_code) setForm(prev => ({ ...prev, postal_code: t.postal_code }));
      }
    });
    setShowAddressPicker(false);
  }

  function updateField(field, value) {
    setForm(prev => ({ ...prev, [field]: value }));
    setFormErrors(prev => ({ ...prev, [field]: null }));
  }

  function fetchTownships(cityId) {
    if (!cityId) { setTownships([]); return Promise.resolve([]); }
    setTownshipsLoading(true);
    return axios.get(`/api/townships/${cityId}`).then(r => {
      const data = r.data?.townships || [];
      setTownships(data);
      return data;
    }).catch(() => { setTownships([]); return []; }).finally(() => setTownshipsLoading(false));
  }

  function handleScreenshotFile(e) {
    const file = e.target.files[0];
    setFileError(''); setSelectedFileName('');
    if (!file) return;
    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) { setFileError('Only JPG, PNG, WebP accepted.'); e.target.value = ''; return; }
    if (file.size > 5 * 1024 * 1024) { setFileError('File too large. Max 5MB.'); e.target.value = ''; return; }
    setSelectedFileName(`${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB)`);
    updateField('payment_screenshot', file);
    const reader = new FileReader();
    reader.onloadend = () => setScreenshotPreview(reader.result);
    reader.readAsDataURL(file);
  }

  function handleRemoveFile() {
    updateField('payment_screenshot', null);
    setScreenshotPreview(null); setSelectedFileName(''); setFileError('');
  }

  async function applyPromotion(code) {
    if (!code?.trim()) return;
    setPromoLoading(true); setPromoMessage(null); setPromoError(false);
    try {
      const res = await axios.post('/cart/apply-promotion', { code });
      if (res.data?.success) {
        setLocalAppliedPromotion({ code: res.data.promotion_code, name: res.data.promotion_name, discount: res.data.discount });
        setLocalDiscount(prev => Number(prev) + Number(res.data.discount));
        setPromotionCode('');
        setPromoMessage(res.data.message || 'Promotion applied!');
      }
    } catch (err) {
      setPromoMessage(err.response?.data?.message || 'Failed to apply.');
      setPromoError(true);
    } finally {
      setPromoLoading(false);
      setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
    }
  }

  async function removePromotion() {
    setPromoLoading(true); setPromoMessage(null); setPromoError(false);
    try {
      const res = await axios.post('/cart/remove-promotion');
      if (res.data?.success) {
        setLocalDiscount(prev => Math.max(0, Number(prev) - Number(localAppliedPromotion?.discount || 0)));
        setLocalAppliedPromotion(null);
        setPromoMessage(res.data.message || 'Promotion removed.');
      }
    } catch (err) {
      setPromoMessage('Failed to remove.');
      setPromoError(true);
    } finally {
      setPromoLoading(false);
      setTimeout(() => { setPromoMessage(null); setPromoError(false); }, 4000);
    }
  }

  function copyToClipboard(text, id) {
    navigator.clipboard.writeText(text).then(() => {
      setTimeout(() => copyToClipboard._copied = null, 2000);
    }).catch(() => {});
  }

  const city = cities?.find(c => c.id == form.city_id);
  const selectedPayment = paymentMethods?.find(pm => pm.id == form.payment_method_id);
  const deliveryFee = selectedDeliveryService?.base_fee ?? city?.delivery_fee ?? 0;
  const packagingFee = selectedPackaging?.fee || 0;
  const codFee = selectedPayment?.type === 'cod' ? (selectedPayment?.cod_fee || 0) : 0;
  const totalDiscount = Number(localDiscount) || 0;
  const totalBeforeCod = Number(subtotal) + Number(deliveryFee) + packagingFee - totalDiscount;
  const total = totalBeforeCod + codFee;
  const totalItems = Array.isArray(cartItems) ? cartItems.reduce((s, i) => s + i.quantity, 0) : 0;
  const isCod = selectedPayment?.type === 'cod';

  const isAddressValid = form.first_name?.trim() && form.last_name?.trim() && form.phone?.trim() && form.address?.trim() && form.city_id;
  const isDeliveryReady = isAddressValid;
  const isPaymentReady = isAddressValid && (selectedDeliveryService || (deliveryServices && deliveryServices.length === 0));

  function canPlaceOrder() {
    return !!(form.first_name?.trim() && form.last_name?.trim() && form.phone?.trim() && form.address?.trim() && form.payment_method_id && form.city_id);
  }

  function handleSubmit(e) {
    e.preventDefault();
    if (submitting) return;
    setSubmitting(true); setFormErrors({}); setSubmitError(null);
    const formData = new FormData();
    Object.keys(form).forEach(key => {
      if (form[key] !== null && form[key] !== undefined) formData.append(key, form[key]);
    });
    if (selectedDeliveryService) formData.append('delivery_service_id', selectedDeliveryService.id);
    if (selectedPackaging) formData.append('packaging_id', selectedPackaging.id);
    router.post(`/store/${tenant.slug}/checkout`, formData, {
      forceFormData: true, preserveScroll: true,
      onError: (errs) => { setFormErrors(errs); setSubmitting(false); },
      onFinish: () => setSubmitting(false),
    });
  }

  if (!cartItems?.length) {
    return (
      <ShopLayout>
        <div className="max-w-lg mx-auto px-4 py-20 text-center">
          <div className="w-20 h-20 mx-auto mb-6 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
            <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Your cart is empty</h2>
          <p className="text-gray-500 dark:text-gray-400 mb-8">Add some items before checking out.</p>
          <Link href={`/store/${tenant.slug}/cart`} className="inline-flex items-center gap-2 px-8 py-3 bg-[var(--theme-color)] text-white font-semibold rounded-xl shadow-sm hover:opacity-90 transition-opacity">
            View Cart
          </Link>
        </div>
      </ShopLayout>
    );
  }

  if (!auth?.user) {
    return (
      <ShopLayout>
        <div className="max-w-md mx-auto px-4 py-20 text-center">
          <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
            <div className="w-16 h-16 mx-auto mb-5 rounded-2xl bg-[var(--theme-color)]/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            </div>
            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Sign in to checkout</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">Please sign in to complete your order.</p>
            <Link href={`/store/${tenant.slug}/login`} className="inline-flex items-center justify-center w-full px-6 py-3 bg-[var(--theme-color)] text-white font-semibold rounded-xl shadow-sm hover:opacity-90 transition-opacity">
              Sign In
            </Link>
            <Link href={`/store/${tenant.slug}/cart`} className="block mt-4 text-sm font-medium text-[var(--theme-color)] hover:opacity-80">{buttonLabels.back_to_cart || 'Back to Cart'}</Link>
          </div>
        </div>
      </ShopLayout>
    );
  }

  const sectionChecks = {
    address: isAddressValid,
    delivery: isDeliveryReady && !!selectedDeliveryService,
    packaging: true,
    payment: !!form.payment_method_id,
    summary: true,
  };
  const sortedSections = Object.entries(checkoutConfig.sections || {})
    .filter(([, s]) => s.visible !== false)
    .sort((a, b) => (a[1].order || 0) - (b[1].order || 0));

  function OrderSummaryPanel() {
    return (
      <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
        <div className="flex items-center justify-between mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Order Summary</h3>
          <span className="text-xs text-gray-500 dark:text-gray-400">{totalItems} item{totalItems !== 1 ? 's' : ''}</span>
        </div>

        <div className="max-h-44 overflow-y-auto space-y-3 mb-4 scrollbar-thin">
          {cartItems.map(item => (
            <div key={item.cart_key || item.id} className="flex gap-3">
              {item.photo1_url && (
                <div className="w-12 h-12 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 flex-shrink-0">
                  <img src={item.photo1_url} alt={item.name} className="w-full h-full object-cover" />
                </div>
              )}
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{item.name}</p>
                {item.variant_name && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{item.variant_name}</p>}
                <div className="flex items-center justify-between mt-1">
                  <span className="text-xs text-gray-400">Qty: {item.quantity}</span>
                  <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(Number(item.price) * Number(item.quantity), cc)}</span>
                </div>
              </div>
            </div>
          ))}
        </div>

        {localAppliedPromotion && (
          <div className="mb-3 p-2.5 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-900/40">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-green-700 dark:text-green-400">{localAppliedPromotion.code}</span>
              <span className="text-xs font-semibold text-green-600 dark:text-green-400">-{formatCurrency(localAppliedPromotion.discount, cc)}</span>
            </div>
          </div>
        )}

        <div className="space-y-2.5 text-sm">
          <div className="flex justify-between">
            <span className="text-gray-500 dark:text-gray-400">Subtotal</span>
            <span className="font-medium text-gray-900 dark:text-gray-100">{formatCurrency(subtotal, cc)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-500 dark:text-gray-400">Delivery</span>
            <span className="text-gray-900 dark:text-gray-100">{deliveryFee > 0 ? formatCurrency(deliveryFee, cc) : <span className="text-amber-600 dark:text-amber-400 text-xs font-medium">TBD</span>}</span>
          </div>
          {packagingFee > 0 && (
            <div className="flex justify-between">
              <span className="text-gray-500 dark:text-gray-400">Packaging</span>
              <span className="text-gray-900 dark:text-gray-100">{formatCurrency(packagingFee, cc)}</span>
            </div>
          )}
          {isCod && codFee > 0 && (
            <div className="flex justify-between text-orange-600 dark:text-orange-400">
              <span>COD Fee</span>
              <span>{formatCurrency(codFee, cc)}</span>
            </div>
          )}
          {totalDiscount > 0 && (
            <div className="flex justify-between text-green-600 dark:text-green-400">
              <span>Discount</span>
              <span>-{formatCurrency(totalDiscount, cc)}</span>
            </div>
          )}
          <div className="flex justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Total</span>
            <span className="text-base font-bold text-[var(--theme-color)]">{formatCurrency(total, cc)}</span>
          </div>
        </div>

        <button type="button" onClick={handleSubmit} disabled={!canPlaceOrder() || submitting}
          className="w-full mt-4 py-3.5 bg-[var(--theme-color)] text-white text-sm font-bold rounded-xl shadow-sm hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
          {submitting && (
            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          )}
          {submitting ? (labels.placing_order || 'Placing Order...') : (buttonLabels.place_order || labels.place_order || 'Place Order')}
        </button>

        {submitError && (
          <div className="mt-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-900/40">
            <p className="text-xs text-red-600 dark:text-red-400 text-center flex items-center justify-center gap-1.5">
              <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              {submitError}
            </p>
          </div>
        )}

        <div className="mt-3 p-2.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800">
          <div className="flex items-center justify-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            <span>{checkoutConfig.messages?.payment_verification || 'Order confirmed after payment verification'}</span>
          </div>
        </div>
      </div>
    );
  }

  function PromoSection() {
    return (
      <div className="mt-3">
        {!localAppliedPromotion ? (
          <div className="flex gap-2">
            <input type="text" value={promotionCode} onChange={e => setPromotionCode(e.target.value)}
              placeholder={labels.promo_placeholder || 'Promo code'} maxLength={50}
              className="flex-1 border border-gray-200 dark:border-gray-700 rounded-xl px-3.5 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)]"
              onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), applyPromotion(promotionCode))} />
            <button type="button" onClick={() => applyPromotion(promotionCode)} disabled={promoLoading || !promotionCode.trim()}
              className="px-5 py-2 bg-[var(--theme-color)] text-white text-sm font-semibold rounded-xl disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 transition-opacity">
              {promoLoading ? <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg> : 'Apply'}
            </button>
          </div>
        ) : (
          <div className="flex items-center justify-between p-2.5 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-900/40">
            <div className="flex items-center gap-2 flex-1 min-w-0">
              <svg className="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
              <span className="text-sm font-medium text-green-800 dark:text-green-300 truncate">{localAppliedPromotion.code}</span>
              <span className="text-xs text-green-600 dark:text-green-400 font-semibold">-{formatCurrency(localAppliedPromotion.discount, cc)}</span>
            </div>
            <button type="button" onClick={removePromotion} disabled={promoLoading}
              className="text-xs font-medium text-red-500 hover:text-red-700 dark:hover:text-red-400 px-2 py-1 disabled:opacity-50">
              Remove
            </button>
          </div>
        )}
        {promoMessage && (
          <div className={`mt-1.5 text-xs px-3 py-2 rounded-xl ${promoError ? 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400' : 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400'}`}>
            {promoMessage}
          </div>
        )}
      </div>
    );
  }

  return (
    <ShopLayout previewMode={previewMode}>
      <Head title="Checkout" />
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">

        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Link href={`/store/${tenant.slug}/cart`} className="flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
              Cart
            </Link>
          </div>
          <div className="text-right">
            <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">{checkoutTitle}</h1>
            {checkoutSubtitle && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{checkoutSubtitle}</p>}
          </div>
        </div>

        <nav aria-label="Checkout progress" className="mb-6">
          <div className="flex items-center gap-2 sm:gap-4 overflow-x-auto">
            {sortedSections.map(([key, section], i) => {
              const done = sectionChecks[key] ?? false;
              return (
                <div key={key} className="flex items-center flex-1 min-w-0">
                  <div className="flex items-center gap-2 min-w-0">
                    <div className={`w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all flex-shrink-0 ${
                      done ? 'bg-[var(--theme-color)] text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'
                    }`}>
                      {done ? (
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                      ) : i + 1}
                    </div>
                    <span className={`text-xs font-medium truncate hidden sm:inline ${done ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'}`}>{section.title || key}</span>
                  </div>
                  {i < sortedSections.length - 1 && (
                    <div className={`flex-1 h-0.5 mx-2 sm:mx-3 rounded-full ${done ? 'bg-[var(--theme-color)]' : 'bg-gray-100 dark:bg-gray-800'}`} />
                  )}
                </div>
              );
            })}
          </div>
        </nav>

        <div className="lg:hidden mb-4">
          <button type="button" onClick={() => setMobileSummaryOpen(!mobileSummaryOpen)}
            aria-expanded={mobileSummaryOpen} aria-controls="mobile-summary"
            className="w-full flex items-center justify-between p-3.5 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50">
            <span className="flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300">
              <svg className="w-4 h-4 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
              Order Summary ({totalItems})
            </span>
            <span className="flex items-center gap-2">
              <span className="font-bold text-gray-900 dark:text-gray-100">{formatCurrency(total, cc)}</span>
              <svg className={`w-4 h-4 text-gray-400 transition-transform ${mobileSummaryOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
            </span>
          </button>
          {mobileSummaryOpen && <div id="mobile-summary" className="mt-2"><OrderSummaryPanel /></div>}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-5 gap-5 lg:gap-6">
          <div className="lg:col-span-3 space-y-4">

            {/* Address Section */}
            <section id="section-address" className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 sm:p-6 shadow-sm">
              <div className="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100 dark:border-gray-800">
                <SectionIcon complete={isAddressValid}>
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </SectionIcon>
                <div className="flex-1 min-w-0">
                  <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Delivery Address</h2>
                  {addresses.length > 0 && (
                    <button type="button" onClick={() => setShowAddressPicker(!showAddressPicker)}
                      className="text-xs font-medium text-[var(--theme-color)] hover:opacity-80 transition-opacity">
                      {showAddressPicker ? 'Hide saved addresses' : 'Choose saved address'}
                    </button>
                  )}
                </div>
              </div>

              {showAddressPicker && addresses.length > 0 && (
                <div className="mb-5 space-y-2">
                  {addresses.map(addr => (
                    <SelectionCard key={addr.id} selected={selectedAddress?.id === addr.id} onClick={() => selectAddress(addr)}>
                      <div className="flex items-center gap-3">
                        <RadioButton selected={selectedAddress?.id === addr.id} />
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{addr.label}</span>
                            {addr.is_default && <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Default</span>}
                          </div>
                          <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{addr.address_line}</p>
                        </div>
                      </div>
                    </SelectionCard>
                  ))}
                </div>
              )}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <label htmlFor="checkout-first-name" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">First name <span className="text-red-400">*</span></label>
                  <input id="checkout-first-name" type="text" value={form.first_name}
                    onChange={e => updateField('first_name', e.target.value)}
                    aria-required="true" aria-invalid={!!formErrors.first_name}
                    aria-describedby={formErrors.first_name ? 'err-fn' : undefined}
                    className={inputClass('first_name')} placeholder="John" />
                  {formErrors.first_name && <p id="err-fn" role="alert" className="text-red-500 text-xs mt-1">{formErrors.first_name}</p>}
                </div>
                <div>
                  <label htmlFor="checkout-last-name" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Last name <span className="text-red-400">*</span></label>
                  <input id="checkout-last-name" type="text" value={form.last_name}
                    onChange={e => updateField('last_name', e.target.value)}
                    aria-required="true" aria-invalid={!!formErrors.last_name}
                    aria-describedby={formErrors.last_name ? 'err-ln' : undefined}
                    className={inputClass('last_name')} placeholder="Doe" />
                  {formErrors.last_name && <p id="err-ln" role="alert" className="text-red-500 text-xs mt-1">{formErrors.last_name}</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mt-3 sm:mt-4">
                <div>
                  <label htmlFor="checkout-email" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Email <span className="text-gray-400 font-normal">(optional)</span></label>
                  <input id="checkout-email" type="email" value={form.email}
                    onChange={e => updateField('email', e.target.value)}
                    className={inputClass('email')} placeholder="john@example.com" />
                </div>
                <div>
                  <label htmlFor="checkout-phone" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Phone <span className="text-red-400">*</span></label>
                  <input id="checkout-phone" type="tel" value={form.phone}
                    onChange={e => updateField('phone', e.target.value)}
                    aria-required="true" aria-invalid={!!formErrors.phone}
                    aria-describedby={formErrors.phone ? 'err-ph' : undefined}
                    className={inputClass('phone')} placeholder="09xxxxxxxxx" />
                  {formErrors.phone && <p id="err-ph" role="alert" className="text-red-500 text-xs mt-1">{formErrors.phone}</p>}
                </div>
              </div>

              <div className="mt-3 sm:mt-4">
                <label htmlFor="checkout-address" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Delivery address <span className="text-red-400">*</span></label>
                <textarea id="checkout-address" value={form.address}
                  onChange={e => updateField('address', e.target.value)} rows="2"
                  aria-required="true" aria-invalid={!!formErrors.address}
                  aria-describedby={formErrors.address ? 'err-ad' : undefined}
                  className={`${inputClass('address')} resize-none`} placeholder="Street, building, ward..." />
                {formErrors.address && <p id="err-ad" role="alert" className="text-red-500 text-xs mt-1">{formErrors.address}</p>}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mt-3 sm:mt-4">
                <div>
                  <label htmlFor="checkout-city" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">City <span className="text-red-400">*</span></label>
                  <select id="checkout-city" value={form.city_id}
                    onChange={e => { updateField('city_id', e.target.value); fetchTownships(e.target.value); setForm(p => ({ ...p, township_id: '', postal_code: '' })); setSelectedDeliveryService(null); }}
                    aria-required="true" aria-invalid={!!formErrors.city_id}
                    aria-describedby={formErrors.city_id ? 'err-ci' : undefined}
                    className={`${inputClass('city_id')} appearance-none`}>
                    <option value="">Select city</option>
                    {cities?.map(c => <option key={c.id} value={c.id}>{c.name} ({formatCurrency(c.delivery_fee || 0, cc)})</option>)}
                  </select>
                  {formErrors.city_id && <p id="err-ci" role="alert" className="text-red-500 text-xs mt-1">{formErrors.city_id}</p>}
                </div>
                <div>
                  <label htmlFor="checkout-township" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Township</label>
                  <select id="checkout-township" value={form.township_id}
                    onChange={e => { updateField('township_id', e.target.value); const t = townships.find(tw => tw.id == e.target.value); setForm(p => ({ ...p, postal_code: t?.postal_code || '' })); }}
                    disabled={!form.city_id} aria-busy={townshipsLoading}
                    className={`${inputClass('township_id')} appearance-none ${!form.city_id ? 'bg-gray-50 dark:bg-gray-800/50 cursor-not-allowed' : ''}`}>
                    <option value="">{form.city_id ? (townshipsLoading ? 'Loading...' : 'Select township') : 'Select city first'}</option>
                    {!townshipsLoading && townships.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                  </select>
                </div>
                <div>
                  <label htmlFor="checkout-postal" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Postal code</label>
                  <input id="checkout-postal" type="text" value={form.postal_code} readOnly tabIndex={-1}
                    aria-readonly="true"
                    className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 cursor-not-allowed" />
                </div>
              </div>

              <div className="mt-3 sm:mt-4">
                <label htmlFor="checkout-notes" className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Order notes <span className="text-gray-400 font-normal">(optional)</span></label>
                <textarea id="checkout-notes" value={form.notes}
                  onChange={e => updateField('notes', e.target.value)} rows="2"
                  className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 resize-none focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all"
                  placeholder="Special instructions for your order..." />
              </div>

              <div className="mt-3 sm:mt-4">
                <PromoSection />
              </div>
            </section>

            {/* Delivery Section */}
            <section id="section-delivery" className={`bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 sm:p-6 shadow-sm transition-all ${!isDeliveryReady ? 'opacity-60' : ''}`}>
              <div className="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
                <SectionIcon complete={!!selectedDeliveryService}>
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                </SectionIcon>
                <div className="flex-1 min-w-0">
                  <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Delivery</h2>
                  {city && <p className="text-xs text-gray-500 dark:text-gray-400">Delivering to {city.name}</p>}
                </div>
              </div>

              {!isDeliveryReady && (
                <div className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
                  <p className="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-2">
                    <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Complete your address first to see delivery options.
                  </p>
                </div>
              )}

              {isDeliveryReady && deliveryServices?.length > 0 && (
                <div role="radiogroup" aria-label="Delivery service" className="space-y-2.5">
                  {deliveryServices.map(service => {
                    const isSelected = selectedDeliveryService?.id === service.id;
                    const actualFee = service.base_fee;
                    return (
                      <SelectionCard key={service.id} selected={isSelected} onClick={() => setSelectedDeliveryService(service)}>
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-3 flex-1 min-w-0">
                            <RadioButton selected={isSelected} />
                            <div className="min-w-0">
                              <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{service.name}</p>
                              <p className="text-xs text-gray-500 dark:text-gray-400">{service.eta_label}</p>
                            </div>
                          </div>
                          <div className="text-right flex-shrink-0 ml-3">
                            <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(actualFee, cc)}</p>
                          </div>
                        </div>
                      </SelectionCard>
                    );
                  })}
                </div>
              )}

              {isDeliveryReady && (!deliveryServices || deliveryServices.length === 0) && (
                <div className="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-800 text-center">
                  <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Standard Delivery</p>
                  <p className="text-lg font-bold text-[var(--theme-color)] mt-1">{formatCurrency(city?.delivery_fee || 0, cc)}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Estimated 2–5 business days</p>
                </div>
              )}

              {isDeliveryReady && packagingOptions?.length > 0 && (
                <div className="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800">
                  <p className="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">Packaging</p>
                  <div role="radiogroup" aria-label="Packaging option" className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    {packagingOptions.map(option => {
                      const isSelected = selectedPackaging?.id === option.id;
                      return (
                        <SelectionCard key={option.id} selected={isSelected} onClick={() => setSelectedPackaging(option)}>
                          <div className="text-center">
                            <RadioButton selected={isSelected} />
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-2">{option.name}</p>
                            {option.description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{option.description}</p>}
                            <p className="text-xs font-semibold text-gray-900 dark:text-gray-100 mt-1">
                              {option.fee > 0 ? `+${formatCurrency(option.fee, cc)}` : 'Free'}
                            </p>
                          </div>
                        </SelectionCard>
                      );
                    })}
                  </div>
                </div>
              )}
            </section>

            {/* Payment Section */}
            <section id="section-payment" className={`bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 sm:p-6 shadow-sm transition-all ${!isPaymentReady ? 'opacity-60' : ''}`}>
              <div className="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
                <SectionIcon complete={!!form.payment_method_id}>
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                </SectionIcon>
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Payment</h2>
              </div>

              {!isPaymentReady && (
                <div className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
                  <p className="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-2">
                    <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Complete your address and select delivery first.
                  </p>
                </div>
              )}

              {isPaymentReady && formErrors.payment_method_id && (
                <div role="alert" className="mb-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-900/40">
                  <p className="text-xs text-red-600 dark:text-red-400">{formErrors.payment_method_id}</p>
                </div>
              )}

              {isPaymentReady && (
                <div role="radiogroup" aria-label="Payment method">
                  <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    {paymentMethods?.map(pm => {
                      const isSelected = form.payment_method_id == pm.id;
                      const isCod = pm.type === 'cod';
                      return (
                        <button key={pm.id} type="button" role="radio" aria-checked={isSelected}
                          onClick={() => updateField('payment_method_id', pm.id)}
                          className={`relative rounded-xl border-2 p-3 text-center cursor-pointer transition-all min-h-[72px] focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 ${
                            isSelected
                              ? 'border-[var(--theme-color)] bg-[var(--theme-color)]/5 shadow-sm'
                              : 'border-gray-100 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900'
                          }`}>
                          <div className="flex flex-col items-center gap-1">
                            <div className="w-8 h-8 rounded-lg bg-gray-50 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                              {pm.qr_image_url ? (
                                <img src={pm.qr_image_url} alt="" className="w-full h-full object-contain" />
                              ) : (
                                <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                              )}
                            </div>
                            <span className="text-xs font-semibold text-gray-900 dark:text-gray-100 leading-tight">{pm.name}</span>
                            {isCod && <span className="text-[10px] text-green-600 dark:text-green-400">Pay on delivery</span>}
                          </div>
                          {isSelected && (
                            <div className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-[var(--theme-color)] rounded-full flex items-center justify-center shadow-sm">
                              <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
                            </div>
                          )}
                        </button>
                      );
                    })}
                  </div>

                  {form.payment_method_id && selectedPayment && selectedPayment.type !== 'cod' && (
                    <div className="mt-4 p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800 space-y-3">
                      <div className="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
                        <div className="flex-1 min-w-0">
                          <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Account</p>
                          <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{selectedPayment.account_name || 'N/A'}</p>
                          <p className="text-xs">{selectedPayment.account_number || 'N/A'}</p>
                          {selectedPayment.bank_name && <p className="text-xs">{selectedPayment.bank_name}</p>}
                        </div>
                      </div>

                      <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
                        <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Payment Information</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                          <div>
                            <label htmlFor="checkout-payer-name" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Payer name</label>
                            <input id="checkout-payer-name" type="text" value={form.payer_name}
                              onChange={e => updateField('payer_name', e.target.value)}
                              className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all"
                              placeholder="Name on account" />
                          </div>
                          <div>
                            <label htmlFor="checkout-transaction-id" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Transaction ID</label>
                            <input id="checkout-transaction-id" type="text" value={form.transaction_id}
                              onChange={e => updateField('transaction_id', e.target.value)}
                              className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all"
                              placeholder="Transaction reference" />
                          </div>
                          <div>
                            <label htmlFor="checkout-payment-date" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date</label>
                            <input id="checkout-payment-date" type="date" value={form.payment_date}
                              onChange={e => updateField('payment_date', e.target.value)}
                              className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all" />
                          </div>
                          <div>
                            <label htmlFor="checkout-payment-time" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Time</label>
                            <input id="checkout-payment-time" type="time" value={form.payment_time}
                              onChange={e => updateField('payment_time', e.target.value)}
                              className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all" />
                          </div>
                        </div>
                        <div className="mt-2.5">
                          <label htmlFor="checkout-payment-note" className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Note <span className="text-gray-400 font-normal">(optional)</span></label>
                          <input id="checkout-payment-note" type="text" value={form.payment_note}
                            onChange={e => updateField('payment_note', e.target.value)}
                            className="w-full border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all"
                            placeholder="Additional payment info..." />
                        </div>
                      </div>

                      <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
                        <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Payment Proof</p>
                        <div className="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:border-[var(--theme-color)]/50 focus-within:border-[var(--theme-color)]/50 transition-colors bg-white dark:bg-gray-900">
                          <input type="file" accept="image/jpeg,image/png,image/webp" onChange={handleScreenshotFile}
                            className="absolute w-0 h-0 opacity-0" id="checkout-screenshot"
                            aria-describedby={fileError ? 'err-ss' : selectedFileName ? 'sf' : undefined} />
                          <label htmlFor="checkout-screenshot" className="cursor-pointer block">
                            <svg className="w-6 h-6 mx-auto text-gray-400 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <p className="text-xs text-gray-600 dark:text-gray-400 font-medium">Upload screenshot</p>
                            <p className="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">JPG, PNG, WebP (max 5MB)</p>
                          </label>
                        </div>
                        {fileError && <p id="err-ss" role="alert" className="text-red-500 text-xs mt-1.5">{fileError}</p>}
                        {selectedFileName && !fileError && (
                          <p id="sf" className="text-xs text-green-600 dark:text-green-400 mt-1.5 flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                            {selectedFileName}
                            <button type="button" onClick={handleRemoveFile} className="text-red-500 hover:text-red-700 ml-auto">Remove</button>
                          </p>
                        )}
                        {screenshotPreview && (
                          <div className="mt-2 inline-block">
                            <img src={screenshotPreview} alt="Preview" className="w-16 h-16 rounded-xl object-cover border border-gray-200 dark:border-gray-700 shadow-sm" />
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </section>
          </div>

          <div className="hidden lg:block lg:col-span-2">
            <div className="sticky top-4">
              <OrderSummaryPanel />
            </div>
          </div>
        </div>
      </div>
    </ShopLayout>
  );
}