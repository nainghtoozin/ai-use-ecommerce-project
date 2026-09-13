import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { ChevronUp, ChevronDown } from 'lucide-react';

const TABS = [
    ['configuration', 'Configuration'],
    ['delivery', 'Delivery'],
    ['packaging', 'Packaging'],
    ['cod', 'COD Rules'],
];

const SAVE_STATUS = { IDLE: 'idle', UNSAVED: 'unsaved', SAVING: 'saving', SAVED: 'saved', FAILED: 'failed' };
const DEBOUNCE_MS = 1000;

const PRESET_DESCRIPTIONS = {
    modern: 'Clean borders, medium spacing, rounded corners',
    minimal: 'Flat design, tight spacing, sharp corners',
    compact: 'Dense layout, smaller elements, efficient space',
};

const PRESET_DEFAULTS = {
    modern: { card_style: 'bordered', section_spacing: 'normal', border_radius: 'medium', button_style: 'solid' },
    minimal: { card_style: 'flat', section_spacing: 'compact', border_radius: 'none', button_style: 'outline' },
    compact: { card_style: 'bordered', section_spacing: 'compact', border_radius: 'small', button_style: 'solid' },
};

export default function CheckoutManagement({ storefront, deliveryServices: initialDelivery, packagingOptions: initialPackaging, codRules: initialCod, cities, revision }) {
    const { tenant } = usePage().props;
    const [tab, setTab] = useState('configuration');
    const [checkout, setCheckout] = useState(storefront?.checkout || {});
    const [saveStatus, setSaveStatus] = useState(SAVE_STATUS.IDLE);
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);
    const [previewMode, setPreviewMode] = useState('desktop');

    const dirtyRef = useRef(false);
    const timerRef = useRef(null);
    const checkoutRef = useRef(checkout);
    const savingRef = useRef(false);
    const pendingAfterSaveRef = useRef(false);
    const doSaveRef = useRef(null);
    const flushActionRef = useRef(null);
    checkoutRef.current = checkout;

    const hasUnpublished = revision?.has_unpublished_changes;
    const publishedRevision = revision?.published?.revision_number;
    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/checkout` : null;

    const statusColor = { idle: 'text-emerald-600', unsaved: 'text-amber-600', saving: 'text-blue-600', saved: 'text-emerald-600', failed: 'text-red-600' };
    const statusLabel = { idle: 'All changes saved', unsaved: 'Unsaved changes', saving: 'Saving\u2026', saved: '\u2713 Draft saved', failed: 'Couldn\'t save changes' };

    const doSave = useCallback(() => {
        if (savingRef.current) { pendingAfterSaveRef.current = true; return; }
        savingRef.current = true;
        setSaveStatus(SAVE_STATUS.SAVING);
        setSaveSuccess(null);
        setSaveError(null);
        const data = new FormData();
        data.append('_method', 'PUT');
        const c = checkoutRef.current;
        if (c.title !== undefined) data.append('checkout[title]', c.title || '');
        if (c.subtitle !== undefined) data.append('checkout[subtitle]', c.subtitle || '');
        if (c.show_branding !== undefined) data.append('checkout[show_branding]', c.show_branding ? '1' : '0');
        if (c.sections) {
            Object.entries(c.sections).forEach(([key, val]) => {
                if (val.visible !== undefined) data.append(`checkout[sections][${key}][visible]`, val.visible ? '1' : '0');
                if (val.title !== undefined) data.append(`checkout[sections][${key}][title]`, val.title);
                if (val.order !== undefined) data.append(`checkout[sections][${key}][order]`, val.order);
            });
        }
        if (c.button_labels) {
            Object.entries(c.button_labels).forEach(([key, val]) => {
                if (val !== undefined) data.append(`checkout[button_labels][${key}]`, val);
            });
        }
        if (c.appearance) {
            Object.entries(c.appearance).forEach(([key, val]) => {
                if (val !== undefined) data.append(`checkout[appearance][${key}]`, String(val));
            });
        }
        if (c.layout) {
            Object.entries(c.layout).forEach(([key, val]) => {
                if (val !== undefined) data.append(`checkout[layout][${key}]`, String(val));
            });
        }
        if (c.visual) {
            Object.entries(c.visual).forEach(([key, val]) => {
                if (val !== undefined) data.append(`checkout[visual][${key}]`, String(val));
            });
        }
        router.post(adminUrl('/admin/storefront/checkout'), data, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savingRef.current = false;
                dirtyRef.current = false;
                setSaveStatus(SAVE_STATUS.SAVED);
                setSaveSuccess('Checkout settings saved to draft.');
                window.setTimeout(() => setSaveSuccess(null), 4000);
                if (pendingAfterSaveRef.current) { pendingAfterSaveRef.current = false; dirtyRef.current = true; if (doSaveRef.current) doSaveRef.current(); }
            },
            onError: () => { savingRef.current = false; setSaveStatus(SAVE_STATUS.FAILED); setSaveError('Could not save changes.'); },
            onFinish: () => { savingRef.current = false; },
        });
    }, []);

    doSaveRef.current = doSave;

    const markDirty = useCallback(() => {
        dirtyRef.current = true;
        setSaveStatus(SAVE_STATUS.UNSAVED);
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => { if (dirtyRef.current && doSaveRef.current) doSaveRef.current(); }, DEBOUNCE_MS);
    }, []);

    const updateCheckout = useCallback((path, value) => {
        setCheckout(prev => {
            const next = { ...prev };
            const keys = path.split('.');
            let ref = next;
            for (let i = 0; i < keys.length - 1; i++) {
                if (!ref[keys[i]]) ref[keys[i]] = {};
                ref = ref[keys[i]];
            }
            ref[keys[keys.length - 1]] = value;
            return next;
        });
        markDirty();
    }, [markDirty]);

    useEffect(() => () => { if (timerRef.current) clearTimeout(timerRef.current); }, []);

    const flushBeforeAction = useCallback((action) => {
        if (timerRef.current) clearTimeout(timerRef.current);
        if (dirtyRef.current || savingRef.current) {
            pendingAfterSaveRef.current = false;
            savingRef.current = false;
            dirtyRef.current = true;
            if (doSaveRef.current) doSaveRef.current();
            setTimeout(action, 300);
        } else { action(); }
    }, []);

    flushActionRef.current = flushBeforeAction;

    const handleRetry = useCallback(() => { dirtyRef.current = true; if (doSaveRef.current) doSaveRef.current(); }, []);

    const handlePreview = useCallback(() => {
        if (dirtyRef.current || savingRef.current) { flushBeforeAction(() => { if (previewUrl) window.open(previewUrl + '?preview=1', '_blank'); }); }
        else if (previewUrl) window.open(previewUrl + '?preview=1', '_blank');
    }, [previewUrl, flushBeforeAction]);

    const handlePublishClick = useCallback(() => {
        flushBeforeAction(() => { setShowPublishConfirm(true); });
    }, [flushBeforeAction]);

    const confirmPublish = useCallback(() => {
        router.post(adminUrl('/admin/storefront/publish'), {}, {
            preserveScroll: true,
            onSuccess: () => { setShowPublishConfirm(false); setSaveSuccess('Published! Checkout changes are now live.'); },
        });
    }, []);

    return (
        <AdminLayout>
            <Head title="Checkout Management" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4 sm:mb-6">
                    <div>
                        <p className="text-sm font-medium text-blue-600">Storefront</p>
                        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Checkout Management</h1>
                        <p className="text-sm text-gray-500 mt-1 hidden sm:block">Configure your checkout experience, delivery, packaging, and COD rules.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {hasUnpublished && <span className="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">Unpublished draft</span>}
                        {publishedRevision && <span className="px-2.5 py-1.5 rounded-lg bg-green-50 text-green-700 text-xs font-medium">Live #{publishedRevision}</span>}
                        {previewUrl && <button type="button" onClick={handlePreview} className="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Preview</button>}
                        {hasUnpublished && <button type="button" onClick={handlePublishClick} className="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs sm:text-sm font-semibold hover:bg-green-700">Publish</button>}
                    </div>
                </div>

                {saveSuccess && <div role="status" className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 sm:p-4 text-sm text-emerald-700">{saveSuccess}</div>}
                {saveError && <div role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 sm:p-4 text-sm text-red-700">{saveError}</div>}

                <div className="flex flex-wrap items-center justify-between gap-2 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span className={`text-xs font-medium ${statusColor[saveStatus]}`}>
                        {saveStatus === SAVE_STATUS.FAILED ? (
                            <button type="button" onClick={handleRetry} className="underline hover:no-underline">{statusLabel[saveStatus]} \u2014 Retry</button>
                        ) : statusLabel[saveStatus]}
                    </span>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div className="border-b border-gray-200 dark:border-gray-800 overflow-x-auto">
                        <nav className="flex min-w-max px-3" aria-label="Checkout management">
                            {TABS.map(([key, label]) => (
                                <button key={key} type="button" onClick={() => setTab(key)} className={`px-3 sm:px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap ${tab === key ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-700'}`}>{label}</button>
                            ))}
                        </nav>
                    </div>

                    <div className="p-4 sm:p-6">
                        {tab === 'configuration' && (
                            <ConfigurationTab checkout={checkout} updateCheckout={updateCheckout} tenant={tenant} />
                        )}
                        {tab === 'delivery' && (
                            <DeliveryTab deliveryServices={initialDelivery} cities={cities} />
                        )}
                        {tab === 'packaging' && (
                            <PackagingTab packagingOptions={initialPackaging} />
                        )}
                        {tab === 'cod' && (
                            <CodTab codRules={initialCod} cities={cities} />
                        )}
                    </div>
                </div>
            </div>

            {showPublishConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Publish checkout changes?</h2>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Your draft changes will become visible to customers on the checkout page.</p>
                        <div className="flex justify-end gap-2 mt-6">
                            <button type="button" onClick={() => setShowPublishConfirm(false)} className="px-4 py-2 rounded-lg border text-sm">Cancel</button>
                            <button type="button" onClick={confirmPublish} className="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Publish Changes</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

function ConfigurationTab({ checkout, updateCheckout, tenant }) {
    const sections = checkout?.sections || {
        address: { visible: true, title: 'Delivery Address', order: 1 },
        delivery: { visible: true, title: 'Delivery Options', order: 2 },
        payment: { visible: true, title: 'Payment Method', order: 3 },
    };

    const sortedSections = Object.entries(sections).sort((a, b) => (a[1].order || 0) - (b[1].order || 0));

    const moveSection = (key, direction) => {
        const idx = sortedSections.findIndex(([k]) => k === key);
        const targetIdx = idx + direction;
        if (targetIdx < 0 || targetIdx >= sortedSections.length) return;
        const [targetKey] = sortedSections[targetIdx];
        const currentOrder = sections[key]?.order || idx + 1;
        const targetOrder = sections[targetKey]?.order || targetIdx + 1;
        updateCheckout(`sections.${key}.order`, targetOrder);
        updateCheckout(`sections.${targetKey}.order`, currentOrder);
    };

    return (
        <div className="space-y-8">
            <div>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Checkout Sections</h2>
                        <p className="text-xs text-gray-500 mt-0.5">Enable, disable, and reorder checkout sections.</p>
                    </div>
                    {tenant?.slug && <a href={`/store/${tenant.slug}/checkout`} target="_blank" rel="noreferrer" className="text-xs sm:text-sm text-blue-600 hover:underline whitespace-nowrap">Preview Checkout &rarr;</a>}
                </div>
                <div className="space-y-3">
                    {sortedSections.map(([key, section], idx) => (
                        <div key={key} className="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex items-center gap-2 flex-1 min-w-0">
                                    <span className="text-xs font-bold text-gray-400 w-6 text-center">{section.order || idx + 1}</span>
                                    <span className="text-sm font-semibold text-gray-900 dark:text-gray-100 capitalize">{key}</span>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Toggle label="Visible" checked={section.visible !== false} onChange={(v) => updateCheckout(`sections.${key}.visible`, v)} />
                                    <div className="flex gap-1">
                                        <button type="button" onClick={() => moveSection(key, -1)} disabled={idx === 0} aria-label="Move section up" className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                            <ChevronUp className="w-4 h-4" aria-hidden="true" />
                                        </button>
                                        <button type="button" onClick={() => moveSection(key, 1)} disabled={idx === sortedSections.length - 1} aria-label="Move section down" className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                            <ChevronDown className="w-4 h-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Field label="Section Title" value={section.title || ''} onChange={(v) => updateCheckout(`sections.${key}.title`, v)} />
                                <Field label="Display Order" type="number" value={section.order || idx + 1} onChange={(v) => updateCheckout(`sections.${key}.order`, parseInt(v, 10) || 1)} />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800 pt-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Page Text</h2>
                <p className="text-xs text-gray-500 mb-4">Customize the checkout page heading and description.</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label="Checkout Title" value={checkout?.title || ''} onChange={(v) => updateCheckout('title', v)} placeholder="Checkout" />
                    <Field label="Subtitle" value={checkout?.subtitle || ''} onChange={(v) => updateCheckout('subtitle', v)} placeholder="Complete your order" />
                </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800 pt-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Button Labels</h2>
                <p className="text-xs text-gray-500 mb-4">Customize the text on checkout action buttons.</p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label="Continue to Delivery" value={checkout?.button_labels?.continue_to_delivery || ''} onChange={(v) => updateCheckout('button_labels.continue_to_delivery', v)} placeholder="Continue to Delivery" />
                    <Field label="Continue to Payment" value={checkout?.button_labels?.continue_to_payment || ''} onChange={(v) => updateCheckout('button_labels.continue_to_payment', v)} placeholder="Continue to Payment" />
                    <Field label="Place Order" value={checkout?.button_labels?.place_order || ''} onChange={(v) => updateCheckout('button_labels.place_order', v)} placeholder="Place Order" />
                </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800 pt-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Visual Style</h2>
                <p className="text-xs text-gray-500 mb-4">Choose a visual preset or customize individual elements.</p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    {['modern', 'minimal', 'compact'].map((preset) => {
                        const isActive = (checkout?.visual?.preset || 'modern') === preset;
                        return (
                            <button key={preset} type="button" onClick={() => { updateCheckout('visual.preset', preset); Object.entries(PRESET_DEFAULTS[preset] || {}).forEach(([k, v]) => updateCheckout(`appearance.${k}`, v)); }}
                                className={`p-4 rounded-lg border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'}`}>
                                <div className="flex items-center justify-between mb-1">
                                    <span className="text-sm font-semibold text-gray-900 dark:text-gray-100 capitalize">{preset}</span>
                                    {isActive && <span className="w-2 h-2 rounded-full bg-blue-500"></span>}
                                </div>
                                <p className="text-xs text-gray-500">{PRESET_DESCRIPTIONS[preset]}</p>
                            </button>
                        );
                    })}
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <SelectField label="Card Style" value={checkout?.appearance?.card_style || 'bordered'} options={['bordered', 'raised', 'flat', 'soft']} onChange={(v) => updateCheckout('appearance.card_style', v)} />
                    <SelectField label="Section Spacing" value={checkout?.appearance?.section_spacing || 'normal'} options={['compact', 'normal', 'relaxed']} onChange={(v) => updateCheckout('appearance.section_spacing', v)} />
                    <SelectField label="Border Radius" value={checkout?.appearance?.border_radius || 'medium'} options={['none', 'small', 'medium', 'large']} onChange={(v) => updateCheckout('appearance.border_radius', v)} />
                    <SelectField label="Button Style" value={checkout?.appearance?.button_style || 'solid'} options={['solid', 'outline']} onChange={(v) => updateCheckout('appearance.button_style', v)} />
                    <SelectField label="Order Summary Style" value={checkout?.visual?.order_summary_style || 'card'} options={['card', 'inline']} onChange={(v) => updateCheckout('visual.order_summary_style', v)} />
                    <Toggle label="Compact Mode" checked={checkout?.appearance?.compact_mode || false} onChange={(v) => updateCheckout('appearance.compact_mode', v)} />
                </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800 pt-6">
                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Layout</h2>
                <p className="text-xs text-gray-500 mb-4">Control the order summary position and mobile display.</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SelectField label="Order Summary Position" value={checkout?.layout?.order_summary_position || 'right'} options={['right', 'left', 'bottom']} onChange={(v) => updateCheckout('layout.order_summary_position', v)} />
                    <Toggle label="Show Order Summary on Mobile" checked={checkout?.layout?.show_order_summary_on_mobile !== false} onChange={(v) => updateCheckout('layout.show_order_summary_on_mobile', v)} />
                </div>
                <div className="mt-4 flex items-center gap-3">
                    <Toggle label="Show Store Logo in Header" checked={checkout?.visual?.show_store_logo !== false} onChange={(v) => updateCheckout('visual.show_store_logo', v)} />
                </div>
            </div>

            <div className="rounded-lg border border-blue-100 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-900 p-4 mt-6">
                <p className="text-xs text-blue-700 dark:text-blue-300">Changes are saved as draft and published when you publish your storefront. Business logic (pricing, fees, stock validation, COD eligibility) is server-controlled.</p>
            </div>
        </div>
    );
}

function DeliveryTab({ deliveryServices, cities }) {
    const sorted = [...deliveryServices].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

    function handleToggle(id) {
        router.post(adminUrl(`/admin/delivery-services/${id}/toggle`), {}, { preserveScroll: true });
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Delivery Services</h2>
                    <p className="text-xs text-gray-500 mt-0.5">Manage delivery methods, fees, and city-specific pricing.</p>
                </div>
                <Link href={adminUrl('/admin/delivery-services/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Service
                </Link>
            </div>

            {!sorted.length ? (
                <div className="text-center py-12 text-gray-500 dark:text-gray-400 text-sm">
                    No delivery services configured yet.
                    <Link href={adminUrl('/admin/delivery-services/create')} className="block mt-2 text-blue-600 hover:underline">Create your first delivery service</Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {sorted.map((service) => (
                        <div key={service.id} className="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{service.name}</span>
                                        <span className="text-xs text-gray-400 font-mono">{service.code}</span>
                                        {service.is_active ? (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                        ) : (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs text-gray-500">
                                        <span>Base: <strong className="text-gray-700 dark:text-gray-300">{service.base_fee}</strong></span>
                                        {service.fee_per_kg && <span>Per Kg: <strong className="text-gray-700 dark:text-gray-300">{service.fee_per_kg}</strong></span>}
                                        <span>ETA: <strong className="text-gray-700 dark:text-gray-300">{service.min_days}-{service.max_days} days</strong></span>
                                        {service.pricing?.length > 0 && <span className="text-blue-600">{service.pricing.length} city overrides</span>}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    <button type="button" onClick={() => handleToggle(service.id)} className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors ${service.is_active ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-red-50 text-red-700 hover:bg-red-100'}`}>
                                        {service.is_active ? 'Deactivate' : 'Activate'}
                                    </button>
                                    <Link href={adminUrl(`/admin/delivery-services/${service.id}/edit`)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function PackagingTab({ packagingOptions }) {
    const sorted = [...packagingOptions].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

    function handleToggle(id) {
        router.post(adminUrl(`/admin/packaging-options/${id}/toggle`), {}, { preserveScroll: true });
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Packaging Options</h2>
                    <p className="text-xs text-gray-500 mt-0.5">Manage packaging types and fees offered during checkout.</p>
                </div>
                <Link href={adminUrl('/admin/packaging-options/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Packaging
                </Link>
            </div>

            {!sorted.length ? (
                <div className="text-center py-12 text-gray-500 dark:text-gray-400 text-sm">
                    No packaging options configured yet.
                    <Link href={adminUrl('/admin/packaging-options/create')} className="block mt-2 text-blue-600 hover:underline">Create your first packaging option</Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {sorted.map((option) => (
                        <div key={option.id} className="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{option.name}</span>
                                        <span className="text-xs text-gray-400 font-mono">{option.code}</span>
                                        {option.is_active ? (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                        ) : (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-x-4 mt-1.5 text-xs text-gray-500">
                                        <span>Fee: <strong className="text-gray-700 dark:text-gray-300">{option.fee}</strong></span>
                                        <span>Order: <strong className="text-gray-700 dark:text-gray-300">{option.sort_order}</strong></span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    <button type="button" onClick={() => handleToggle(option.id)} className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors ${option.is_active ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-red-50 text-red-700 hover:bg-red-100'}`}>
                                        {option.is_active ? 'Deactivate' : 'Activate'}
                                    </button>
                                    <Link href={adminUrl(`/admin/packaging-options/${option.id}/edit`)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function CodTab({ codRules, cities }) {
    function handleToggle(id) {
        router.post(adminUrl(`/admin/cod-rules/${id}/toggle`), {}, { preserveScroll: true });
    }

    function getEligibilitySummary(rule) {
        const parts = [];
        if (rule.min_order_amount !== null || rule.max_order_amount !== null) {
            let range = '';
            if (rule.min_order_amount !== null && rule.max_order_amount !== null) range = `${rule.min_order_amount} - ${rule.max_order_amount}`;
            else if (rule.min_order_amount !== null) range = `Min: ${rule.min_order_amount}`;
            else range = `Max: ${rule.max_order_amount}`;
            parts.push(`Amount: ${range}`);
        }
        if (rule.allowed_city_ids?.length > 0) parts.push(`${rule.allowed_city_ids.length} cities allowed`);
        if (rule.excluded_city_ids?.length > 0) parts.push(`${rule.excluded_city_ids.length} cities excluded`);
        return parts.length > 0 ? parts.join(' \u00b7 ') : 'All orders eligible';
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">COD Rules</h2>
                    <p className="text-xs text-gray-500 mt-0.5">Configure Cash on Delivery eligibility, fees, and city restrictions.</p>
                </div>
                <Link href={adminUrl('/admin/cod-rules/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Rule
                </Link>
            </div>

            {!codRules?.length ? (
                <div className="text-center py-12 text-gray-500 dark:text-gray-400 text-sm">
                    No COD rules configured yet.
                    <Link href={adminUrl('/admin/cod-rules/create')} className="block mt-2 text-blue-600 hover:underline">Create your first COD rule</Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {codRules.map((rule) => (
                        <div key={rule.id} className="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{rule.name}</span>
                                        {rule.is_active ? (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                        ) : (
                                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs text-gray-500">
                                        <span>Fee: <strong className="text-gray-700 dark:text-gray-300">{rule.cod_fee}</strong></span>
                                        {rule.apply_cod_fee_to_total && <span className="text-gray-400">(applied to total)</span>}
                                        <span>{getEligibilitySummary(rule)}</span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    <button type="button" onClick={() => handleToggle(rule.id)} className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors ${rule.is_active ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-red-50 text-red-700 hover:bg-red-100'}`}>
                                        {rule.is_active ? 'Deactivate' : 'Activate'}
                                    </button>
                                    <Link href={adminUrl(`/admin/cod-rules/${rule.id}/edit`)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</Link>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function Field({ label, value, onChange, type = 'text', placeholder = '' }) {
    return (
        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {label}
            <input type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
                className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" />
        </label>
    );
}

function SelectField({ label, value, options, onChange }) {
    return (
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {label}
            <select value={value} onChange={(e) => onChange(e.target.value)}
                className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal">
                {options.map((opt) => <option key={opt} value={opt}>{opt.charAt(0).toUpperCase() + opt.slice(1)}</option>)}
            </select>
        </label>
    );
}

function Toggle({ label, checked, onChange }) {
    return (
        <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="rounded border-gray-300 text-blue-600" />
            {label}
        </label>
    );
}
