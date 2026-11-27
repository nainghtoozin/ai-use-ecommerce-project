import { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from '@inertiajs/react';
import { Check, ChevronDown, MapPin, Search, ShoppingBag, X } from 'lucide-react';
import { formatCurrency } from '@/Utils/currency';

const SectionIcon = ({ complete, children }) => (
  <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${complete ? 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'}`}>
    {complete ? (
      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
    ) : children}
  </div>
);

const RadioButton = ({ selected }) => (
  <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${selected ? 'border-[var(--theme-color)]' : 'border-gray-300 dark:border-gray-600'}`} aria-hidden="true">
    {selected && <div className="w-2 h-2 rounded-full bg-[var(--theme-color)]" />}
  </div>
);

const selectionCardClass = (selected, disabled = false) => {
  if (disabled) return 'border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 cursor-not-allowed';
  if (selected) return 'border-[var(--theme-color)] bg-blue-50 dark:bg-blue-950/30 shadow-sm cursor-pointer';
  return 'border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600 cursor-pointer';
};

const SelectionCard = ({ selected, onClick, children, disabled = false }) => (
  <button type="button" onClick={onClick} disabled={disabled}
    className={`w-full text-left p-3 rounded-xl border-2 transition-all min-h-[44px] focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 ${selectionCardClass(selected, disabled)}`}
    role="radio" aria-checked={selected}>
    {children}
  </button>
);

function SelectionSheet({ open, title, options, selectedId, search, loading, onSearch, onSelect, onClose }) {
  if (!open || typeof document === 'undefined') return null;

  return createPortal(
    <div className="fixed inset-0 z-[100] flex items-end justify-center" role="dialog" aria-modal="true" aria-label={title}>
      <div className="absolute inset-0 bg-gray-950/50 backdrop-blur-[2px]" onClick={onClose} aria-hidden="true" />
      <div className="relative w-full max-w-md max-h-[min(75vh,560px)] flex flex-col bg-white dark:bg-gray-900 rounded-t-2xl shadow-2xl border-t border-gray-200 dark:border-gray-800 pb-[calc(env(safe-area-inset-bottom)+0.5rem)]">
        <div className="flex items-center justify-between gap-3 px-4 pt-3 pb-2.5 border-b border-gray-100 dark:border-gray-800">
          <div className="min-w-0">
            <h3 className="text-sm font-bold text-gray-900 dark:text-gray-100">{title}</h3>
          </div>
          <button type="button" onClick={onClose} aria-label="Close" className="w-8 h-8 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>
        <div className="px-4 py-2.5 border-b border-gray-100 dark:border-gray-800">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" aria-hidden="true" />
            <input
              type="search"
              value={search}
              onChange={(event) => onSearch(event.target.value)}
              placeholder={`Search ${title.replace('Select ', '').toLowerCase()}`}
              aria-label={`Search ${title}`}
              className="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 pl-9 pr-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)]"
            />
          </div>
        </div>
        <div className="flex-1 min-h-0 overflow-y-auto overscroll-contain px-2 py-2">
          {loading ? (
            <p className="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading options...</p>
          ) : options.length === 0 ? (
            <p className="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No matches found.</p>
          ) : (
            options.map((option) => {
              const selected = String(option.id) === String(selectedId);
              return (
                <button
                  key={option.id}
                  type="button"
                  onClick={() => onSelect(option.id)}
                  className={`w-full flex items-center justify-between gap-3 px-3 py-3 rounded-lg text-left transition-colors min-h-[48px] ${
                    selected ? 'bg-[var(--theme-color)]/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/70'
                  }`}
                >
                  <span className="min-w-0 flex-1">
                    <span className={`block text-sm font-medium truncate ${selected ? 'text-[var(--theme-color)]' : 'text-gray-900 dark:text-gray-100'}`}>{option.name}</span>
                    {option.meta && <span className="block text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{option.meta}</span>}
                  </span>
                  {selected && <Check className="w-4 h-4 flex-shrink-0 text-[var(--theme-color)]" aria-hidden="true" />}
                </button>
              );
            })
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

export default function MobileCheckoutView({ ctx }) {
  const [summaryOpen, setSummaryOpen] = useState(false);
  const {
    tenant, cartItems, subtotal, paymentMethods, cities, deliveryServices, packagingOptions,
    form, formErrors, submitError, submitting,
    townships, townshipsLoading, selectedDeliveryService, selectedPackaging, quote, quoting, quoteError,
    localAppliedPromotion, localAppliedCoupon, localDiscount, couponCode, promoLoading, promoMessage, promoError,
    screenshotPreview, selectedFileName, fileError, showAddressPicker, selectedAddress, addresses, profilePhone,
    updateField, selectAddress, fetchTownships, handleScreenshotFile, handleRemoveFile, applyCoupon, removeCoupon, handleSubmit,
    setShowAddressPicker, setSelectedDeliveryService, setSelectedPackaging, setCouponCode, setForm, setFormErrors,
    city, township, isOtherCity, isOtherTownship, isOtherLocation, selectedPayment, quotedService, visibleServices,
    codAvailable, codUnavailableReason, deliveryFee, packagingFee, totalDiscount, total, totalItems,
    isAddressValid, isDeliveryReady, isPaymentReady, canPlaceOrder,
    tr, labels, checkoutConfig, buttonLabels, cc, primaryActionStyle, auth,
  } = ctx;

  const [activeSheet, setActiveSheet] = useState(null);
  const [sheetSearch, setSheetSearch] = useState('');

  const closeSheet = useCallback(() => {
    setActiveSheet(null);
    setSheetSearch('');
  }, []);

  useEffect(() => {
    if (!activeSheet) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') closeSheet();
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [activeSheet, closeSheet]);

  const cityOptions = useMemo(() => {
    const options = (cities || []).map((item) => ({
      id: String(item.id),
      name: item.name,
      meta: Array.isArray(item.townships) && item.townships.length > 0 ? `${item.townships.length} townships` : null,
    }));
    options.push({ id: 'other', name: tr('other', 'Other'), meta: null });
    return options;
  }, [cities, tr]);

  const townshipOptions = useMemo(() => {
    if (isOtherCity || !form.city_id) return [];
    const options = (townships || []).map((item) => ({
      id: String(item.id),
      name: item.name,
      meta: item.delivery_fee ? `${formatCurrency(item.delivery_fee, cc)} delivery` : null,
    }));
    options.push({ id: 'other', name: tr('other', 'Other'), meta: null });
    return options;
  }, [townships, isOtherCity, form.city_id, tr, cc]);

  const sheetTitle = activeSheet === 'city' ? 'Select City / Region' : 'Select Township';
  const sheetOptions = useMemo(() => {
    const source = activeSheet === 'city' ? cityOptions : townshipOptions;
    const query = sheetSearch.trim().toLowerCase();
    if (!query) return source;
    return source.filter((option) => option.name.toLowerCase().includes(query) || (option.meta || '').toLowerCase().includes(query));
  }, [activeSheet, cityOptions, townshipOptions, sheetSearch]);
  const sheetSelectedId = activeSheet === 'city' ? form.city_id : form.township_id;

  const clearFieldErrors = (fields) => {
    setFormErrors((previous) => {
      const next = { ...previous };
      fields.forEach((field) => { delete next[field]; });
      return next;
    });
  };

  const handleCitySelect = (value) => {
    const cityId = String(value);
    if (cityId !== 'other') fetchTownships(cityId);
    setForm((previous) => ({ ...previous, city_id: cityId, township_id: cityId === 'other' ? 'other' : '', postal_code: '' }));
    clearFieldErrors(['city_id', 'township_id']);
    setSelectedDeliveryService(null);
    closeSheet();
  };

  const handleTownshipSelect = (value) => {
    const townshipId = String(value);
    const selectedTownship = townships.find((item) => String(item.id) === townshipId);
    setForm((previous) => ({
      ...previous,
      township_id: townshipId,
      postal_code: townshipId === 'other' ? '' : (selectedTownship?.postal_code || ''),
    }));
    clearFieldErrors(['township_id']);
    setSelectedDeliveryService(null);
    closeSheet();
  };

  const inputClass = (field, { disabled = false, selected = false } = {}) => {
    const base = 'w-full border-[1px] rounded-lg px-3 py-2.5 text-sm transition-all focus:outline-none focus:ring-2';
    if (disabled) {
      return `${base} border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 cursor-not-allowed`;
    }
    const err = formErrors[field];
    if (err) {
      return `${base} border-red-400 dark:border-red-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-red-400/40`;
    }
    if (selected) {
      return `${base} border-[var(--theme-color)] bg-blue-50 dark:bg-blue-950/30 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-[var(--theme-color)] focus:ring-[var(--theme-color)]/30`;
    }
    return `${base} border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-[var(--theme-color)] focus:ring-[var(--theme-color)]/30`;
  };

  const townshipDisabled = !form.city_id || isOtherCity;
  const citySelected = !!form.city_id;
  const townshipSelected = !!form.township_id && !townshipDisabled;
  const couponApplyDisabled = promoLoading || !couponCode.trim();
  const placeOrderDisabled = !canPlaceOrder() || submitting || !!quoteError;

  if (!cartItems?.length) {
    return (
      <div className="px-4 py-16 text-center">
        <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
          <ShoppingBag className="w-8 h-8 text-gray-400" />
        </div>
        <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Your cart is empty</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">Add some items before checking out.</p>
        <Link href={`/store/${tenant.slug}/cart`} style={primaryActionStyle} className="inline-flex items-center justify-center px-6 py-3 text-white font-semibold text-sm rounded-xl">View Cart</Link>
      </div>
    );
  }

  if (!auth?.user) {
    return (
      <div className="px-4 py-16 text-center">
        <div className="max-w-sm mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Sign in to checkout</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-5">Please sign in to complete your order.</p>
          <Link href={`/store/${tenant.slug}/login`} style={primaryActionStyle} className="inline-flex items-center justify-center w-full px-6 py-3 text-white font-semibold text-sm rounded-xl">Sign In</Link>
          <Link href={`/store/${tenant.slug}/cart`} className="block mt-3 text-sm font-medium text-[var(--theme-color)]">{buttonLabels?.back_to_cart || 'Back to Cart'}</Link>
        </div>
      </div>
    );
  }

  return (
    <div className="px-4 pt-3 pb-28 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link href={`/store/${tenant.slug}/cart`} className="flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
          Cart
        </Link>
        <div className="text-right">
          <h1 className="text-lg font-bold text-gray-900 dark:text-gray-100">{checkoutConfig?.title || 'Checkout'}</h1>
          {checkoutConfig?.subtitle && <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{checkoutConfig.subtitle}</p>}
        </div>
      </div>

      {/* Delivery Address */}
      <section className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
        <div className="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
          <SectionIcon complete={isAddressValid}>
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          </SectionIcon>
          <div className="flex-1 min-w-0">
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Delivery Address</h2>
            {addresses?.length > 0 && (
              <button type="button" onClick={() => setShowAddressPicker(!showAddressPicker)} className="text-xs font-medium text-[var(--theme-color)] hover:opacity-80 mt-0.5">
                {showAddressPicker ? 'Hide saved addresses' : 'Choose saved address'}
              </button>
            )}
          </div>
        </div>

        {showAddressPicker && addresses?.length > 0 && (
          <div className="mb-4 space-y-2">
            {addresses.map(addr => (
              <SelectionCard key={addr.id} selected={selectedAddress?.id === addr.id} onClick={() => selectAddress(addr)}>
                <div className="flex items-center gap-3">
                  <RadioButton selected={selectedAddress?.id === addr.id} />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{addr.label}</span>
                      {addr.is_default && <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-green-100 text-green-700">Default</span>}
                    </div>
                    <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{addr.address_line}</p>
                  </div>
                </div>
              </SelectionCard>
            ))}
          </div>
        )}

        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">First name <span className="text-red-400">*</span></label>
              <input type="text" value={form.first_name} onChange={e => updateField('first_name', e.target.value)} aria-required="true" className={inputClass('first_name')} placeholder="John" />
              {formErrors.first_name && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.first_name}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Last name <span className="text-red-400">*</span></label>
              <input type="text" value={form.last_name} onChange={e => updateField('last_name', e.target.value)} aria-required="true" className={inputClass('last_name')} placeholder="Doe" />
              {formErrors.last_name && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.last_name}</p>}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Email <span className="text-gray-400 font-normal">(optional)</span></label>
              <input type="email" value={form.email} onChange={e => updateField('email', e.target.value)} className={inputClass('email')} placeholder="john@example.com" />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Phone <span className="text-red-400">*</span></label>
              <input type="tel" value={form.phone} onChange={e => updateField('phone', e.target.value)} aria-required="true" className={inputClass('phone')} placeholder="09xxxxxxxxx" />
              {formErrors.phone && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.phone}</p>}
            </div>
          </div>
          <div>
            <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Delivery address <span className="text-red-400">*</span></label>
            <textarea value={form.address} onChange={e => updateField('address', e.target.value)} rows="2" aria-required="true" className={`${inputClass('address')} resize-none`} placeholder="Street, building, ward..." />
            {formErrors.address && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.address}</p>}
          </div>
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">City / Region <span className="text-red-400">*</span></label>
              <button type="button" onClick={() => setActiveSheet('city')} aria-haspopup="dialog" aria-required="true"
                className={`${inputClass('city_id', { selected: citySelected })} flex items-center justify-between gap-2 text-left min-h-[44px]`}>
                <span className="flex items-center gap-2 min-w-0">
                  <MapPin className={`w-4 h-4 flex-shrink-0 ${citySelected ? 'text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-500'}`} aria-hidden="true" />
                  <span className={`truncate ${citySelected ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'}`}>
                    {city?.name || (isOtherCity ? tr('other', 'Other') : 'Select city / region')}
                  </span>
                  {citySelected && <Check className="w-3.5 h-3.5 flex-shrink-0 text-[var(--theme-color)]" aria-hidden="true" />}
                </span>
                <ChevronDown className="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
              </button>
              {formErrors.city_id && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.city_id}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Township</label>
              <button type="button" onClick={() => { if (!townshipDisabled) setActiveSheet('township'); }} disabled={townshipDisabled} aria-haspopup="dialog"
                className={`${inputClass('township_id', { disabled: townshipDisabled, selected: townshipSelected })} flex items-center justify-between gap-2 text-left min-h-[44px]`}>
                <span className={`flex items-center gap-2 min-w-0 truncate ${townshipDisabled ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100'}`}>
                  {townshipSelected && <Check className="w-3.5 h-3.5 flex-shrink-0 text-[var(--theme-color)]" aria-hidden="true" />}
                  <span className="truncate">{isOtherCity ? tr('other', 'Other') : townshipsLoading ? 'Loading townships...' : township?.name || (form.township_id === 'other' ? tr('other', 'Other') : 'Select township')}</span>
                </span>
                <ChevronDown className={`w-4 h-4 flex-shrink-0 ${townshipDisabled ? 'text-gray-300 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500'}`} aria-hidden="true" />
              </button>
              {!form.city_id && <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Select city first</p>}
              {formErrors.township_id && <p role="alert" className="text-red-500 text-xs mt-1">{formErrors.township_id}</p>}
            </div>
          </div>
          {!isOtherTownship && form.postal_code && (
            <div>
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Postal code</label>
              <input type="text" value={form.postal_code} readOnly tabIndex={-1} aria-readonly="true" className={inputClass('postal_code', { disabled: true })} />
            </div>
          )}
          {isOtherTownship && (
            <div role="alert" className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
              <p className="text-xs text-amber-700 dark:text-amber-400">{tr('other_location_warning', 'Other location selected. Please enter your complete and accurate delivery address.')}</p>
            </div>
          )}
          <div>
            <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">Order notes <span className="text-gray-400 font-normal">(optional)</span></label>
            <textarea value={form.notes} onChange={e => updateField('notes', e.target.value)} rows="2" className="w-full border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 resize-none focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all" placeholder="Special instructions..." />
          </div>
        </div>
      </section>

      {/* Delivery */}
      <section className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
        <div className="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-gray-800">
          <SectionIcon complete={isDeliveryReady}>
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
          </SectionIcon>
          <div className="flex-1 min-w-0">
            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Delivery</h2>
            {city && <p className="text-xs text-gray-500 dark:text-gray-400">Delivering to {city.name}</p>}
          </div>
        </div>

        {!isDeliveryReady && (
          <div className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
            <p className="text-xs text-amber-700 dark:text-amber-400">Complete your address first to see delivery options.</p>
          </div>
        )}

        {isDeliveryReady && isOtherTownship && (
          <div className="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800 text-center">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Other Location Delivery</p>
            <p className="text-lg font-bold text-[var(--theme-color)] mt-1">{formatCurrency(quote?.delivery?.township_fee ?? 5000, cc)}</p>
          </div>
        )}

        {isDeliveryReady && !isOtherTownship && visibleServices?.length > 0 && (
          <div role="radiogroup" aria-label="Delivery service" className="space-y-2">
            {visibleServices.map(service => {
              const isSelected = selectedDeliveryService?.id === service.id;
              const quoted = (id) => quote?.services?.find(s => String(s.id) === String(id));
              const q = quoted(service.id);
              const actualFee = q ? q.fee : service.base_fee;
              const etaLabel = q?.eta_label || service.eta_label;
              return (
                <SelectionCard key={service.id} selected={isSelected} onClick={() => setSelectedDeliveryService(service)}>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <RadioButton selected={isSelected} />
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{service.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{etaLabel}</p>
                      </div>
                    </div>
                    <p className="text-sm font-bold text-gray-900 dark:text-gray-100 flex-shrink-0 ml-3">{formatCurrency(actualFee, cc)}</p>
                  </div>
                </SelectionCard>
              );
            })}
          </div>
        )}

        {isDeliveryReady && !isOtherTownship && form.township_id && visibleServices?.length === 0 && (
          <div className="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800 text-center">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">No delivery services available</p>
          </div>
        )}

        {isDeliveryReady && packagingOptions?.length > 0 && (
          <div className="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
            <p className="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Packaging</p>
            <div role="radiogroup" aria-label="Packaging option" className="grid grid-cols-2 gap-2">
              {packagingOptions.map(option => {
                const isSelected = selectedPackaging?.id === option.id;
                return (
                  <SelectionCard key={option.id} selected={isSelected} onClick={() => setSelectedPackaging(option)}>
                    <div className="text-center">
                      <RadioButton selected={isSelected} />
                      <p className="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1.5">{option.name}</p>
                      <p className="text-xs font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{option.fee > 0 ? `+${formatCurrency(option.fee, cc)}` : 'Free'}</p>
                    </div>
                  </SelectionCard>
                );
              })}
            </div>
          </div>
        )}
      </section>

      {/* Payment */}
      <section className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
        <div className="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100 dark:border-gray-800">
          <SectionIcon complete={!!form.payment_method_id}>
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
          </SectionIcon>
          <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Payment</h2>
        </div>

        {!isPaymentReady && (
          <div className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
            <p className="text-xs text-amber-700 dark:text-amber-400">Complete your address first to see payment methods.</p>
          </div>
        )}

        {isPaymentReady && formErrors.payment_method_id && (
          <div role="alert" className="mb-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-900/40">
            <p className="text-xs text-red-600 dark:text-red-400">{formErrors.payment_method_id}</p>
          </div>
        )}

        {isPaymentReady && (!paymentMethods || paymentMethods.length === 0) && (
          <div className="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-900/40">
            <p className="text-xs text-amber-700 dark:text-amber-400">No payment methods available.</p>
          </div>
        )}

        {isPaymentReady && paymentMethods?.length > 0 && (
          <div role="radiogroup" aria-label="Payment method" className="space-y-2">
            {paymentMethods?.map(pm => {
              const isSelected = form.payment_method_id == pm.id;
              const isCod = pm.type === 'cod';
              const codDisabled = isCod && codAvailable === false;
              const description = isCod ? 'Pay when you receive' : (pm.description || null);
              return (
                <button key={pm.id} type="button" role="radio" aria-checked={isSelected} aria-disabled={codDisabled}
                  onClick={() => { if (!codDisabled) updateField('payment_method_id', pm.id); }}
                  className={`w-full flex items-center gap-3 rounded-xl border-2 px-3 py-2.5 text-left transition-all min-h-[52px] focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 ${selectionCardClass(isSelected, codDisabled)}`}>
                  <div className={`w-8 h-8 flex-shrink-0 rounded-lg flex items-center justify-center overflow-hidden ${isCod && !codDisabled ? 'bg-emerald-100 dark:bg-emerald-900/40' : codDisabled ? 'bg-gray-200 dark:bg-gray-700' : 'bg-gray-50 dark:bg-gray-800'}`}>
                    {pm.qr_image_url ? (
                      <img src={pm.qr_image_url} alt="" className="w-full h-full object-contain" />
                    ) : isCod ? (
                      <svg className="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    ) : (
                      <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className={`text-sm font-semibold truncate ${codDisabled ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100'}`}>{isCod ? 'Cash on Delivery' : pm.name}</p>
                    {description && <p className="text-[11px] text-gray-500 dark:text-gray-400 truncate">{description}</p>}
                    {codDisabled && <p className="text-[11px] text-amber-600 dark:text-amber-400 truncate">{codUnavailableReason || 'Not available'}</p>}
                  </div>
                  <RadioButton selected={isSelected} />
                </button>
              );
            })}
          </div>
        )}

        {isPaymentReady && selectedPayment && selectedPayment.type !== 'cod' && (
          <div className="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800 space-y-3">
            {(selectedPayment.account_name || selectedPayment.account_number || selectedPayment.bank_name) && (
              <div>
                <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Account</p>
                {selectedPayment.account_name && <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{selectedPayment.account_name}</p>}
                {selectedPayment.account_number && <p className="text-xs text-gray-600 dark:text-gray-400">{selectedPayment.account_number}</p>}
                {selectedPayment.bank_name && <p className="text-xs text-gray-600 dark:text-gray-400">{selectedPayment.bank_name}</p>}
              </div>
            )}
            {selectedPayment.instructions && (
              <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
                <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">How to pay</p>
                <p className="text-xs text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-line">{selectedPayment.instructions}</p>
              </div>
            )}
            {selectedPayment.qr_image_url && (
              <img src={selectedPayment.qr_image_url} alt="QR" className="w-24 h-24 mx-auto object-contain rounded-lg border border-gray-200 dark:border-gray-700" />
            )}
            <div className="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-2">
              <p className="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment information</p>
              {[
                { field: 'payer_name', label: 'Payer name', placeholder: 'Name on account', type: 'text' },
                { field: 'sender_account_number', label: 'Sender account', placeholder: 'Account number', type: 'text' },
                { field: 'transaction_id', label: 'Transaction ID', placeholder: 'Transaction reference', type: 'text' },
                { field: 'payment_date', label: 'Date', placeholder: '', type: 'date' },
                { field: 'payment_time', label: 'Time', placeholder: '', type: 'time' },
                { field: 'payment_note', label: 'Note (optional)', placeholder: 'Additional payment info', type: 'text' },
              ].map(({ field, label, placeholder, type }) => (
                <div key={field}>
                  <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">{label}</label>
                  <input type={type} value={form[field] || ''} onChange={event => updateField(field, event.target.value)} className={inputClass(field)} placeholder={placeholder} />
                </div>
              ))}
            </div>
            <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
              <label className="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Payment screenshot</label>
              <input type="file" accept="image/jpeg,image/png,image/webp" onChange={handleScreenshotFile} className="w-full text-xs text-gray-500" />
              {selectedFileName && <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-1 truncate">{selectedFileName}</p>}
              {fileError && <p role="alert" className="text-red-500 text-xs mt-1">{fileError}</p>}
              {screenshotPreview && (
                <div className="mt-2 relative inline-block">
                  <img src={screenshotPreview} alt="Preview" className="w-20 h-20 object-cover rounded-lg border border-gray-200" />
                  <button type="button" onClick={handleRemoveFile} aria-label="Remove payment screenshot" className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center">×</button>
                </div>
              )}
            </div>
          </div>
        )}

        {isPaymentReady && form.payment_method_id === 'cod' && codAvailable === false && (
          <p className="mt-3 text-xs text-amber-600 dark:text-amber-400">{codUnavailableReason || 'COD not available'}</p>
        )}
      </section>

      {/* Coupon */}
      <section className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Coupon</h2>
        <div className="flex gap-2">
          <input type="text" value={couponCode} onChange={e => setCouponCode(e.target.value)} placeholder="Enter coupon code" className="flex-1 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)]/30" />
          <button type="button" onClick={() => applyCoupon(couponCode)} disabled={couponApplyDisabled}
            style={couponApplyDisabled && !promoLoading ? undefined : primaryActionStyle}
            className={`px-4 py-2.5 text-sm font-semibold rounded-lg transition-[filter,transform] ${
              couponApplyDisabled && !promoLoading
                ? 'bg-blue-100 text-blue-800 dark:bg-slate-700 dark:text-slate-200 cursor-not-allowed'
                : 'text-white hover:brightness-95 active:brightness-90'
            }`}>
            {promoLoading ? '...' : 'Apply'}
          </button>
        </div>
        {promoMessage && (
          <p className={`text-xs mt-2 ${promoError ? 'text-red-500' : 'text-green-600'}`}>{promoMessage}</p>
        )}
        {(localAppliedCoupon?.code || localAppliedPromotion?.code) && (
          <div className="mt-3 flex items-center justify-between p-2.5 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-100 dark:border-green-900/40">
            <span className="text-xs font-medium text-green-700 dark:text-green-400">
              {localAppliedCoupon?.code || localAppliedPromotion?.code} applied
            </span>
            <button type="button" onClick={removeCoupon} className="text-xs text-red-500 hover:text-red-700">Remove</button>
          </div>
        )}
      </section>

      {/* Order Summary (compact, collapsible) */}
      <section className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <button type="button" onClick={() => setSummaryOpen(v => !v)} className="w-full flex items-center justify-between px-4 py-3.5">
          <div className="flex items-center gap-2">
            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Order Summary</span>
            <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${summaryOpen ? 'rotate-180' : ''}`} />
          </div>
          <span className="text-base font-extrabold text-gray-900 dark:text-gray-100">{formatCurrency(total, cc)}</span>
        </button>
        {summaryOpen && (
          <div className="px-4 pb-4 pt-1 space-y-2 text-sm border-t border-gray-50 dark:border-gray-800">
            <div className="flex justify-between text-gray-500 dark:text-gray-400">
              <span>Subtotal ({totalItems} items)</span>
              <span>{formatCurrency(subtotal, cc)}</span>
            </div>
            {localDiscount > 0 && (
              <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                <span>Discount</span>
                <span className="font-medium">-{formatCurrency(localDiscount, cc)}</span>
              </div>
            )}
            <div className="flex justify-between text-gray-500 dark:text-gray-400">
              <span>Delivery</span>
              <span>{formatCurrency(deliveryFee, cc)}</span>
            </div>
            {packagingFee > 0 && (
              <div className="flex justify-between text-gray-500 dark:text-gray-400">
                <span>Packaging</span>
                <span>{formatCurrency(packagingFee, cc)}</span>
              </div>
            )}
            <div className="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-800">
              <span className="text-base font-bold text-gray-900 dark:text-gray-100">Total</span>
              <span className="text-lg font-extrabold text-gray-900 dark:text-gray-100">{formatCurrency(total, cc)}</span>
            </div>
          </div>
        )}
      </section>

      {submitError && (
        <div className="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-900/40">
          <p className="text-xs text-red-600 dark:text-red-400 text-center">{submitError}</p>
        </div>
      )}

      <SelectionSheet
        open={activeSheet === 'city'}
        title={sheetTitle}
        options={sheetOptions}
        selectedId={sheetSelectedId}
        search={sheetSearch}
        loading={false}
        onSearch={setSheetSearch}
        onSelect={handleCitySelect}
        onClose={closeSheet}
      />
      <SelectionSheet
        open={activeSheet === 'township'}
        title={sheetTitle}
        options={sheetOptions}
        selectedId={sheetSelectedId}
        search={sheetSearch}
        loading={townshipsLoading}
        onSearch={setSheetSearch}
        onSelect={handleTownshipSelect}
        onClose={closeSheet}
      />

      {/* Sticky Place Order bar */}
      <div className="fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] left-0 right-0 z-40 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-t border-gray-100 dark:border-gray-800 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
        <div className="flex items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Total Payment</p>
            <p className="text-xl font-extrabold text-gray-900 dark:text-gray-100 leading-tight">{formatCurrency(total, cc)}</p>
          </div>
          <button type="button" onClick={handleSubmit} disabled={placeOrderDisabled}
            style={placeOrderDisabled && !submitting ? undefined : primaryActionStyle}
            className={`flex-1 max-w-[62%] py-3.5 font-semibold text-sm rounded-xl flex items-center justify-center gap-2 transition-[filter,transform] ${
              placeOrderDisabled && !submitting
                ? 'bg-blue-100 text-blue-800 dark:bg-slate-700 dark:text-slate-200 cursor-not-allowed shadow-none'
                : 'text-white hover:brightness-95 active:brightness-90 shadow-sm'
            }`}>
            {submitting && <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth={4} /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>}
            {submitting ? (labels.placing_order || 'Placing Order...') : (buttonLabels?.place_order || labels.place_order || 'Place Order')}
          </button>
        </div>
        {!canPlaceOrder() && (
          <p className="text-[11px] text-amber-600 dark:text-amber-400 mt-1.5 text-center">Complete address and payment method to place your order.</p>
        )}
      </div>
    </div>
  );
}