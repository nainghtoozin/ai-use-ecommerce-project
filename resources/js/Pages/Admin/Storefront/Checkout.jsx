import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { ChevronUp, ChevronDown, LayoutGrid, Type, Palette, Columns, Monitor, Eye, Settings, MapPin, Truck, Package, CreditCard, FileText, GripVertical, Pencil, MessageSquare, Receipt, Smartphone, Tablet, Laptop, Maximize } from 'lucide-react';

const SAVE_STATUS = { IDLE: 'idle', UNSAVED: 'unsaved', SAVING: 'saving', SAVED: 'saved', FAILED: 'failed' };
const DEBOUNCE_MS = 1000;

const PRESET_DESCRIPTIONS = {
    modern: 'Clean borders, medium spacing, rounded corners',
    minimal: 'Flat design, tight spacing, sharp corners',
    elegant: 'Refined shadows, generous spacing, smooth radius',
    compact: 'Dense layout, smaller elements, efficient space',
    friendly: 'Soft colors, rounded shapes, welcoming feel',
};

const PRESET_DEFAULTS = {
    modern: { card_style: 'bordered', section_spacing: 'normal', border_radius: 'medium', button_style: 'solid', input_style: 'bordered', button_size: 'md', section_style: 'card', product_image_size: 'medium' },
    minimal: { card_style: 'flat', section_spacing: 'compact', border_radius: 'none', button_style: 'outline', input_style: 'borderless', button_size: 'sm', section_style: 'minimal', product_image_size: 'small' },
    elegant: { card_style: 'raised', section_spacing: 'relaxed', border_radius: 'large', button_style: 'solid', input_style: 'underlined', button_size: 'lg', section_style: 'card', product_image_size: 'large' },
    compact: { card_style: 'bordered', section_spacing: 'compact', border_radius: 'small', button_style: 'solid', input_style: 'bordered', button_size: 'sm', section_style: 'minimal', product_image_size: 'small' },
    friendly: { card_style: 'soft', section_spacing: 'normal', border_radius: 'large', button_style: 'solid', input_style: 'bordered', button_size: 'md', section_style: 'card', product_image_size: 'medium' },
};

const EDITOR_SECTIONS = [
    { key: 'sections', label: 'Sections', icon: LayoutGrid },
    { key: 'content', label: 'Content', icon: Type },
    { key: 'appearance', label: 'Appearance', icon: Palette },
    { key: 'layout', label: 'Layout', icon: Columns },
    { key: 'responsive', label: 'Responsive', icon: Monitor },
];

const MANAGEMENT_TABS = [
    ['delivery', 'Delivery'],
    ['packaging', 'Packaging'],
    ['cod', 'COD Rules'],
];

export default function CheckoutManagement({ storefront, deliveryServices: initialDelivery, packagingOptions: initialPackaging, codRules: initialCod, cities, revision }) {
    const { tenant } = usePage().props;
    const [editorTab, setEditorTab] = useState('sections');
    const [mgmtTab, setMgmtTab] = useState(null);
    const [checkout, setCheckout] = useState(storefront?.checkout || {});
    const [saveStatus, setSaveStatus] = useState(SAVE_STATUS.IDLE);
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);

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
    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/checkout/preview` : null;

    const statusColor = { idle: 'text-emerald-600', unsaved: 'text-amber-600', saving: 'text-blue-600', saved: 'text-emerald-600', failed: 'text-red-600' };
    const statusLabel = { idle: 'All changes saved', unsaved: 'Unsaved changes', saving: 'Saving\u2026', saved: '\u2713 Draft saved', failed: 'Couldn\'t save changes' };

    const doSave = useCallback(() => {
        if (savingRef.current) { pendingAfterSaveRef.current = true; return; }
        savingRef.current = true;
        setSaveStatus(SAVE_STATUS.SAVING);
        setSaveSuccess(null);
        setSaveError(null);
        router.put(adminUrl('/admin/storefront/checkout'), { checkout: checkoutRef.current }, {
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
        if (dirtyRef.current || savingRef.current) { flushBeforeAction(() => { if (previewUrl) window.open(previewUrl, '_blank'); }); }
        else if (previewUrl) window.open(previewUrl, '_blank');
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
            <Head title="Checkout Editor" />
            <div className="max-w-full mx-auto">
                {/* Editor Header */}
                <div className="sticky top-0 z-30 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                    <div className="px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-14">
                            <div className="flex items-center gap-3 min-w-0">
                                <Link href={adminUrl('/admin/storefront')} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-sm shrink-0">
                                    Storefront
                                </Link>
                                <span className="text-gray-300 dark:text-gray-600">/</span>
                                <h1 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">Checkout Editor</h1>
                                {hasUnpublished && <span className="hidden sm:inline-flex px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-medium uppercase tracking-wide">Draft</span>}
                                {publishedRevision && <span className="hidden sm:inline-flex px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-medium uppercase tracking-wide">Live #{publishedRevision}</span>}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`text-[11px] font-medium ${statusColor[saveStatus]}`}>
                                    {saveStatus === SAVE_STATUS.FAILED ? (
                                        <button type="button" onClick={handleRetry} className="underline hover:no-underline">{statusLabel[saveStatus]}</button>
                                    ) : statusLabel[saveStatus]}
                                </span>
                                {previewUrl && <button type="button" onClick={handlePreview} className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"><Eye className="w-3.5 h-3.5" />Preview</button>}
                                {hasUnpublished && <button type="button" onClick={handlePublishClick} className="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700 transition-colors">Publish</button>}
                            </div>
                        </div>
                    </div>
                </div>

                {saveSuccess && <div role="status" className="mx-4 sm:mx-6 lg:mx-8 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{saveSuccess}</div>}
                {saveError && <div role="alert" className="mx-4 sm:mx-6 lg:mx-8 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{saveError}</div>}

                {/* Main Layout: Sidebar + Content */}
                <div className="flex min-h-[calc(100vh-56px)]">
                    {/* Left Sidebar */}
                    <aside className="hidden lg:flex flex-col w-56 border-r border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 shrink-0">
                        <div className="p-3">
                            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-2 mb-2">Editor</p>
                            <nav className="space-y-0.5">
                                {EDITOR_SECTIONS.map(({ key, label, icon: Icon }) => (
                                    <button key={key} type="button" onClick={() => { setEditorTab(key); setMgmtTab(null); }}
                                        className={`w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-medium transition-colors ${editorTab === key && !mgmtTab ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'}`}>
                                        <Icon className="w-4 h-4 shrink-0" />
                                        {label}
                                    </button>
                                ))}
                            </nav>
                        </div>
                        <div className="p-3 border-t border-gray-200 dark:border-gray-800">
                            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-2 mb-2">Manage</p>
                            <nav className="space-y-0.5">
                                {MANAGEMENT_TABS.map(([key, label]) => (
                                    <button key={key} type="button" onClick={() => { setMgmtTab(key); setEditorTab(null); }}
                                        className={`w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-medium transition-colors ${mgmtTab === key ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200'}`}>
                                        <Settings className="w-4 h-4 shrink-0" />
                                        {label}
                                    </button>
                                ))}
                            </nav>
                        </div>
                    </aside>

                    {/* Mobile tab bar */}
                    <div className="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-2 py-1.5 flex gap-1 overflow-x-auto">
                        {EDITOR_SECTIONS.map(({ key, label, icon: Icon }) => (
                            <button key={key} type="button" onClick={() => { setEditorTab(key); setMgmtTab(null); }}
                                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors ${editorTab === key && !mgmtTab ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                                <Icon className="w-3.5 h-3.5" />{label}
                            </button>
                        ))}
                        <span className="w-px bg-gray-200 dark:bg-gray-700 my-1" />
                        {MANAGEMENT_TABS.map(([key, label]) => (
                            <button key={key} type="button" onClick={() => { setMgmtTab(key); setEditorTab(null); }}
                                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors ${mgmtTab === key ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                                <Settings className="w-3.5 h-3.5" />{label}
                            </button>
                        ))}
                    </div>

                    {/* Main Content Area */}
                    <main className="flex-1 min-w-0 pb-20 lg:pb-0">
                        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                            {/* Editor Sections */}
                            {editorTab === 'sections' && <SectionsEditor checkout={checkout} updateCheckout={updateCheckout} tenant={tenant} />}
                            {editorTab === 'content' && <ContentEditor checkout={checkout} updateCheckout={updateCheckout} />}
                            {editorTab === 'appearance' && <AppearanceEditor checkout={checkout} updateCheckout={updateCheckout} />}
                            {editorTab === 'layout' && <LayoutEditor checkout={checkout} updateCheckout={updateCheckout} />}
                            {editorTab === 'responsive' && <ResponsiveEditor checkout={checkout} updateCheckout={updateCheckout} />}

                            {/* Management Tabs */}
                            {mgmtTab === 'delivery' && <DeliveryTab deliveryServices={initialDelivery} cities={cities} />}
                            {mgmtTab === 'packaging' && <PackagingTab packagingOptions={initialPackaging} />}
                            {mgmtTab === 'cod' && <CodTab codRules={initialCod} cities={cities} />}
                        </div>
                    </main>
                </div>
            </div>

            {showPublishConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Publish checkout changes?</h2>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Your draft changes will become visible to customers on the checkout page.</p>
                        <div className="flex justify-end gap-2 mt-6">
                            <button type="button" onClick={() => setShowPublishConfirm(false)} className="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Cancel</button>
                            <button type="button" onClick={confirmPublish} className="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">Publish Changes</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

/* ────────────────────────────────────────────
   Editor Section Panels
   ──────────────────────────────────────────── */

function SectionsEditor({ checkout, updateCheckout, tenant }) {
    const [editingKey, setEditingKey] = useState(null);

    const sections = checkout?.sections || {
        address: { visible: true, title: 'Delivery Address', order: 1 },
        delivery: { visible: true, title: 'Delivery Options', order: 2 },
        packaging: { visible: true, title: 'Packaging', order: 3 },
        payment: { visible: true, title: 'Payment Method', order: 4 },
        summary: { visible: true, title: 'Order Summary', order: 5 },
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

    const sectionMeta = {
        address: { icon: MapPin, label: 'Delivery Address', desc: 'Customer fills in shipping details', color: 'bg-blue-500', lightBg: 'bg-blue-50 dark:bg-blue-900/20' },
        delivery: { icon: Truck, label: 'Delivery Options', desc: 'Delivery service and fee selection', color: 'bg-purple-500', lightBg: 'bg-purple-50 dark:bg-purple-900/20' },
        packaging: { icon: Package, label: 'Packaging', desc: 'Packaging option selection', color: 'bg-pink-500', lightBg: 'bg-pink-50 dark:bg-pink-900/20' },
        payment: { icon: CreditCard, label: 'Payment Method', desc: 'Payment method selection', color: 'bg-amber-500', lightBg: 'bg-amber-50 dark:bg-amber-900/20' },
        coupon: { icon: Receipt, label: 'Coupon', desc: 'Coupon / promo code input', color: 'bg-green-500', lightBg: 'bg-green-50 dark:bg-green-900/20' },
        summary: { icon: FileText, label: 'Order Summary', desc: 'Order summary display', color: 'bg-gray-500', lightBg: 'bg-gray-50 dark:bg-gray-800' },
        notes: { icon: MessageSquare, label: 'Order Notes', desc: 'Customer order notes', color: 'bg-teal-500', lightBg: 'bg-teal-50 dark:bg-teal-900/20' },
    };

    const canEditTitle = ['address', 'delivery', 'packaging', 'payment', 'summary', 'coupon', 'notes'];

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Sections</h2>
                    <p className="text-sm text-gray-500 mt-1">Reorder and toggle checkout sections. Each section maps to a checkout step.</p>
                </div>
                <div className="flex items-center gap-2 text-xs text-gray-400">
                    <GripVertical className="w-3.5 h-3.5" />
                    <span className="hidden sm:inline">Use arrows to reorder</span>
                </div>
            </div>

            {/* Section list */}
            <div className="space-y-2">
                {sortedSections.map(([key, section], idx) => {
                    const meta = sectionMeta[key] || { icon: FileText, label: key, desc: 'Custom section', color: 'bg-gray-400', lightBg: 'bg-gray-50 dark:bg-gray-800' };
                    const Icon = meta.icon;
                    const isVisible = section.visible !== false;
                    const isFirst = idx === 0;
                    const isLast = idx === sortedSections.length - 1;
                    const isEditing = editingKey === key;
                    const canTitle = canEditTitle.includes(key);

                    return (
                        <div key={key} className={`rounded-xl border transition-all ${isVisible ? 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50'}`}>
                            <div className="flex items-center gap-3 p-3 sm:p-4">
                                {/* Drag handle */}
                                <div className="hidden sm:flex flex-col items-center gap-0.5 text-gray-300 dark:text-gray-600 shrink-0">
                                    <GripVertical className="w-4 h-4" />
                                </div>

                                {/* Icon */}
                                <div className={`w-10 h-10 rounded-lg ${isVisible ? meta.lightBg : 'bg-gray-100 dark:bg-gray-800'} flex items-center justify-center shrink-0`}>
                                    <Icon className={`w-5 h-5 ${isVisible ? meta.color.replace('bg-', 'text-').replace('-500', '-600') : 'text-gray-400'}`} />
                                </div>

                                {/* Content */}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className={`text-sm font-semibold ${isVisible ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'}`}>{section.title || meta.label}</span>
                                        {isVisible ? (
                                            <span className="px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wider bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">On</span>
                                        ) : (
                                            <span className="px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wider bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">Off</span>
                                        )}
                                    </div>
                                    <p className={`text-xs mt-0.5 ${isVisible ? 'text-gray-500' : 'text-gray-400 dark:text-gray-500'}`}>{meta.desc}</p>
                                </div>

                                {/* Controls */}
                                <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
                                    {/* Edit title button */}
                                    {canTitle && (
                                        <button type="button" onClick={() => setEditingKey(isEditing ? null : key)} aria-label="Edit section title"
                                            className={`w-8 h-8 flex items-center justify-center rounded-lg border transition-colors ${isEditing ? 'border-blue-300 bg-blue-50 dark:bg-blue-900/30 text-blue-600' : 'border-gray-200 dark:border-gray-700 text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-600'}`}>
                                            <Pencil className="w-3.5 h-3.5" />
                                        </button>
                                    )}

                                    {/* Toggle */}
                                    <button type="button" role="switch" aria-checked={isVisible} aria-label={`Toggle ${meta.label}`}
                                        onClick={() => updateCheckout(`sections.${key}.visible`, !isVisible)}
                                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${isVisible ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
                                        <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${isVisible ? 'translate-x-5' : 'translate-x-0'}`} />
                                    </button>

                                    {/* Reorder */}
                                    <div className="flex gap-0.5">
                                        <button type="button" onClick={() => moveSection(key, -1)} disabled={isFirst}
                                            aria-label="Move section up"
                                            className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                                            <ChevronUp className="w-4 h-4" />
                                        </button>
                                        <button type="button" onClick={() => moveSection(key, 1)} disabled={isLast}
                                            aria-label="Move section down"
                                            className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                                            <ChevronDown className="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Inline title editor */}
                            {isEditing && canTitle && (
                                <div className="px-4 pb-4 pt-1 border-t border-gray-100 dark:border-gray-800">
                                    <label className="text-xs font-medium text-gray-500 mb-1 block">Section Title</label>
                                    <input type="text" value={section.title || ''} onChange={(e) => updateCheckout(`sections.${key}.title`, e.target.value)}
                                        placeholder={meta.label}
                                        className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                                    <p className="text-[11px] text-gray-400 mt-1">Displayed as the section heading on the checkout page.</p>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Summary */}
            <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div className="flex-1">
                    <p className="text-xs font-medium text-gray-500 mb-2">Section Order</p>
                    <div className="flex flex-wrap gap-1.5">
                        {sortedSections.map(([key, section], idx) => {
                            const meta = sectionMeta[key] || { label: key, color: 'bg-gray-400' };
                            const isVisible = section.visible !== false;
                            return (
                                <span key={key} className={`inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium ${isVisible ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' : 'bg-gray-50 dark:bg-gray-900 text-gray-400 line-through'}`}>
                                    <span className={`w-1.5 h-1.5 rounded-full ${isVisible ? meta.color : 'bg-gray-300'}`} />
                                    {idx + 1}. {section.title || meta.label}
                                </span>
                            );
                        })}
                    </div>
                </div>
            </div>

            {tenant?.slug && (
                <div className="rounded-lg border border-blue-100 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-900 p-4">
                    <p className="text-xs text-blue-700 dark:text-blue-300">Section order and visibility are saved as draft. Publish to make changes live. Business logic (pricing, fees, stock validation) is server-controlled.</p>
                </div>
            )}
        </div>
    );
}

function ContentEditor({ checkout, updateCheckout }) {
    const sections = checkout?.sections || {};

    const sectionFields = [
        { key: 'address', label: 'Delivery Address', placeholder: 'Delivery Address' },
        { key: 'delivery', label: 'Delivery Options', placeholder: 'Delivery Options' },
        { key: 'packaging', label: 'Packaging', placeholder: 'Packaging' },
        { key: 'payment', label: 'Payment Method', placeholder: 'Payment Method' },
        { key: 'summary', label: 'Order Summary', placeholder: 'Order Summary' },
    ];

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Content</h2>
                <p className="text-sm text-gray-500 mt-1">Customize all customer-facing text, headings, and messages.</p>
            </div>

            {/* Page Header */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-5">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Page Header</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Checkout Title" value={checkout?.title || ''} onChange={(v) => updateCheckout('title', v)} placeholder="Checkout" />
                        <Field label="Subtitle" value={checkout?.subtitle || ''} onChange={(v) => updateCheckout('subtitle', v)} placeholder="Complete your order" />
                    </div>
                </div>
            </div>

            {/* Section Titles */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-5">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Section Titles</h3>
                    <p className="text-xs text-gray-500 mb-4">Headings displayed above each checkout section.</p>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {sectionFields.map(({ key, label, placeholder }) => (
                        <Field key={key} label={label} value={sections[key]?.title || ''} onChange={(v) => updateCheckout(`sections.${key}.title`, v)} placeholder={placeholder} />
                    ))}
                </div>
            </div>

            {/* Button Labels */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-5">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Button Labels</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Place Order" value={checkout?.button_labels?.place_order || ''} onChange={(v) => updateCheckout('button_labels.place_order', v)} placeholder="Place Order" />
                        <Field label="Back to Cart" value={checkout?.button_labels?.back_to_cart || ''} onChange={(v) => updateCheckout('button_labels.back_to_cart', v)} placeholder="Back to Cart" />
                    </div>
                </div>
            </div>

            {/* Messages */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-5">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Messages</h3>
                    <p className="text-xs text-gray-500 mb-4">Informational messages shown to customers during checkout.</p>
                </div>
                <div className="space-y-4">
                    <TextareaField label="Payment Verification Message" value={checkout?.messages?.payment_verification || ''} onChange={(v) => updateCheckout('messages.payment_verification', v)} placeholder="e.g. Your payment will be verified within 24 hours." />
                    <TextareaField label="Order Confirmation Message" value={checkout?.messages?.order_confirmation || ''} onChange={(v) => updateCheckout('messages.order_confirmation', v)} placeholder="e.g. Thank you! We'll process your order shortly." />
                </div>
            </div>
        </div>
    );
}

function AppearanceEditor({ checkout, updateCheckout }) {
    const appearance = checkout?.appearance || {};
    const visual = checkout?.visual || {};

    return (
        <div className="space-y-8">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Appearance</h2>
                <p className="text-sm text-gray-500 mt-1">Choose a design preset or fine-tune individual elements.</p>
            </div>

            {/* ── Presets ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Design Preset</h3>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    {['modern', 'minimal', 'elegant', 'compact', 'friendly'].map((preset) => {
                        const isActive = (visual.preset || 'modern') === preset;
                        return (
                            <button key={preset} type="button" onClick={() => { updateCheckout('visual.preset', preset); Object.entries(PRESET_DEFAULTS[preset] || {}).forEach(([k, v]) => updateCheckout(`appearance.${k}`, v)); }}
                                className={`relative p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 shadow-sm' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                {isActive && <span className="absolute top-2 right-2 w-2 h-2 rounded-full bg-blue-500" />}
                                <div className="mb-2">
                                    <PresetPreview preset={preset} isActive={isActive} />
                                </div>
                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100 capitalize block">{preset}</span>
                                <p className="text-[11px] text-gray-500 mt-0.5 leading-tight">{PRESET_DESCRIPTIONS[preset]}</p>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Card Style ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Card Style</h3>
                <p className="text-xs text-gray-500 mb-4">How section cards appear on the checkout page.</p>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { value: 'bordered', label: 'Bordered', border: 'border-2 border-gray-300 dark:border-gray-600', shadow: '' },
                        { value: 'raised', label: 'Raised', border: 'border border-gray-200 dark:border-gray-700', shadow: 'shadow-md' },
                        { value: 'flat', label: 'Flat', border: 'border-0', shadow: '' },
                        { value: 'soft', label: 'Soft', border: 'border-0', shadow: 'bg-gray-50 dark:bg-gray-800' },
                    ].map(({ value, label, border, shadow }) => {
                        const isActive = (appearance.card_style || 'bordered') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.card_style', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className={`h-12 rounded-lg ${border} ${shadow} bg-white dark:bg-gray-900 mb-2 flex items-center justify-center`}>
                                    <div className="w-6 h-1.5 bg-gray-200 dark:bg-gray-700 rounded" />
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Input Style ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Input Style</h3>
                <p className="text-xs text-gray-500 mb-4">How form fields look in the checkout form.</p>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { value: 'bordered', label: 'Bordered', cls: 'border border-gray-300 dark:border-gray-600 rounded-lg' },
                        { value: 'borderless', label: 'Borderless', cls: 'border-0 border-b-2 border-gray-300 dark:border-gray-600 rounded-none' },
                        { value: 'underlined', label: 'Underlined', cls: 'border-0 border-b border-gray-300 dark:border-gray-600 rounded-none' },
                        { value: 'filled', label: 'Filled', cls: 'border-0 bg-gray-100 dark:bg-gray-800 rounded-lg' },
                    ].map(({ value, label, cls }) => {
                        const isActive = (appearance.input_style || 'bordered') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.input_style', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className="h-12 flex items-center justify-center mb-2">
                                    <div className={`w-full h-6 px-2 bg-white dark:bg-gray-900 ${cls}`} />
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Button Style + Size ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Button</h3>
                <p className="text-xs text-gray-500 mb-4">Style and size of checkout action buttons.</p>
                <div className="space-y-5">
                    {/* Button Style */}
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-2 block">Style</label>
                        <div className="grid grid-cols-3 sm:grid-cols-5 gap-3">
                            {[
                                { value: 'solid', label: 'Solid', cls: 'bg-blue-600 text-white' },
                                { value: 'outline', label: 'Outline', cls: 'border-2 border-blue-600 text-blue-600' },
                                { value: 'ghost', label: 'Ghost', cls: 'bg-blue-50 dark:bg-blue-900/30 text-blue-600' },
                                { value: 'soft', label: 'Soft', cls: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700' },
                                { value: 'rounded', label: 'Pill', cls: 'bg-blue-600 text-white rounded-full' },
                            ].map(({ value, label, cls }) => {
                                const isActive = (appearance.button_style || 'solid') === value;
                                return (
                                    <button key={value} type="button" onClick={() => updateCheckout('appearance.button_style', value)}
                                        className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                        <div className="h-8 flex items-center justify-center mb-2">
                                            <span className={`px-4 py-1.5 text-xs font-medium rounded-lg ${cls}`}>Button</span>
                                        </div>
                                        <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    {/* Button Size */}
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-2 block">Size</label>
                        <div className="grid grid-cols-3 gap-3">
                            {[
                                { value: 'sm', label: 'Small', cls: 'px-3 py-1 text-xs' },
                                { value: 'md', label: 'Medium', cls: 'px-5 py-2 text-sm' },
                                { value: 'lg', label: 'Large', cls: 'px-7 py-2.5 text-base' },
                            ].map(({ value, label, cls }) => {
                                const isActive = (appearance.button_size || 'md') === value;
                                return (
                                    <button key={value} type="button" onClick={() => updateCheckout('appearance.button_size', value)}
                                        className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                        <div className="h-8 flex items-center justify-center mb-2">
                                            <span className={`bg-blue-600 text-white font-medium rounded-lg ${cls}`}>Button</span>
                                        </div>
                                        <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Border Radius ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Border Radius</h3>
                <p className="text-xs text-gray-500 mb-4">Corner roundness for cards, inputs, and buttons.</p>
                <div className="grid grid-cols-4 gap-3">
                    {[
                        { value: 'none', label: 'None', radius: 'rounded-none' },
                        { value: 'small', label: 'Small', radius: 'rounded' },
                        { value: 'medium', label: 'Medium', radius: 'rounded-lg' },
                        { value: 'large', label: 'Large', radius: 'rounded-2xl' },
                    ].map(({ value, label, radius }) => {
                        const isActive = (appearance.border_radius || 'medium') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.border_radius', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className="h-10 flex items-center justify-center mb-2">
                                    <div className={`w-10 h-10 bg-blue-100 dark:bg-blue-900/40 border-2 border-blue-400 dark:border-blue-500 ${radius}`} />
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Spacing ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Spacing</h3>
                <p className="text-xs text-gray-500 mb-4">Vertical space between checkout sections.</p>
                <div className="grid grid-cols-3 gap-3">
                    {[
                        { value: 'compact', label: 'Compact', gap: 'gap-2', blocks: 3 },
                        { value: 'normal', label: 'Normal', gap: 'gap-4', blocks: 3 },
                        { value: 'relaxed', label: 'Relaxed', gap: 'gap-6', blocks: 3 },
                    ].map(({ value, label, gap, blocks }) => {
                        const isActive = (appearance.section_spacing || 'normal') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.section_spacing', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className={`flex flex-col ${gap} mb-2`}>
                                    {Array.from({ length: blocks }).map((_, i) => (
                                        <div key={i} className="h-2 bg-blue-200 dark:bg-blue-800 rounded" />
                                    ))}
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Section Style ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Section Style</h3>
                <p className="text-xs text-gray-500 mb-4">How checkout sections are visually grouped.</p>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {[
                        { value: 'card', label: 'Card', cls: 'border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 shadow-sm' },
                        { value: 'minimal', label: 'Minimal', cls: 'border-0 bg-transparent' },
                        { value: 'outlined', label: 'Outlined', cls: 'border-2 border-gray-200 dark:border-gray-700 rounded-lg bg-transparent' },
                    ].map(({ value, label, cls }) => {
                        const isActive = (appearance.section_style || 'card') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.section_style', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className={`h-14 p-2 mb-2 ${cls}`}>
                                    <div className="w-8 h-1 bg-gray-200 dark:bg-gray-700 rounded mb-1" />
                                    <div className="w-12 h-1 bg-gray-100 dark:bg-gray-800 rounded" />
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Order Summary Style ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:border-gray-100 mb-1">Order Summary</h3>
                <p className="text-xs text-gray-500 mb-4">How the order summary appears during checkout.</p>
                <div className="grid grid-cols-2 gap-3">
                    {[
                        { value: 'card', label: 'Sticky Card', desc: 'Fixed sidebar on desktop, collapsible on mobile' },
                        { value: 'inline', label: 'Inline', desc: 'Full-width section within the form flow' },
                    ].map(({ value, label, desc }) => {
                        const isActive = (visual.order_summary_style || 'card') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('visual.order_summary_style', value)}
                                className={`p-4 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className="flex items-center gap-2 mb-1">
                                    <span className={`text-sm font-semibold ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100'}`}>{label}</span>
                                    {isActive && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                                </div>
                                <p className="text-[11px] text-gray-500 leading-tight">{desc}</p>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Product Image Size ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Product Images</h3>
                <p className="text-xs text-gray-500 mb-4">Size of product thumbnails in the order summary.</p>
                <div className="grid grid-cols-3 gap-3">
                    {[
                        { value: 'small', label: 'Small', size: 'w-8 h-8' },
                        { value: 'medium', label: 'Medium', size: 'w-12 h-12' },
                        { value: 'large', label: 'Large', size: 'w-16 h-16' },
                    ].map(({ value, label, size }) => {
                        const isActive = (appearance.product_image_size || 'medium') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.product_image_size', value)}
                                className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className="h-14 flex items-center justify-center mb-2">
                                    <div className={`${size} rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center`}>
                                        <svg className="w-1/2 h-1/2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                </div>
                                <span className={`text-xs font-medium ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Theme Color ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Accent Color</h3>
                <p className="text-xs text-gray-500 mb-4">Primary color for checkout buttons and highlights.</p>
                <div className="flex flex-wrap gap-3">
                    {[
                        { value: '#2563eb', label: 'Blue' },
                        { value: '#16a34a', label: 'Green' },
                        { value: '#9333ea', label: 'Purple' },
                        { value: '#ea580c', label: 'Orange' },
                        { value: '#dc2626', label: 'Red' },
                        { value: '#0d9488', label: 'Teal' },
                        { value: '#4f46e5', label: 'Indigo' },
                        { value: '#6b21a8', label: 'Violet' },
                    ].map(({ value, label }) => {
                        const isActive = (appearance.accent_color || '#2563eb') === value;
                        return (
                            <button key={value} type="button" onClick={() => updateCheckout('appearance.accent_color', value)}
                                aria-label={label}
                                className={`w-10 h-10 rounded-full border-2 transition-all flex items-center justify-center ${isActive ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent hover:scale-105'}`}
                                style={{ backgroundColor: value }}>
                                {isActive && <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>}
                            </button>
                        );
                    })}
                </div>
                <div className="mt-3 flex items-center gap-3">
                    <label className="text-xs text-gray-500">Custom</label>
                    <input type="color" value={appearance.accent_color || '#2563eb'} onChange={(e) => updateCheckout('appearance.accent_color', e.target.value)}
                        className="w-8 h-8 rounded-lg border-0 cursor-pointer" />
                    <span className="text-xs text-gray-400 font-mono">{appearance.accent_color || '#2563eb'}</span>
                </div>
            </div>

            {/* ── Misc ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Options</h3>
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Compact Mode</p>
                            <p className="text-xs text-gray-500">Reduce padding and font sizes for a tighter layout</p>
                        </div>
                        <Toggle checked={appearance.compact_mode || false} onChange={(v) => updateCheckout('appearance.compact_mode', v)} />
                    </div>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Show Store Logo</p>
                            <p className="text-xs text-gray-500">Display the store logo in the checkout header</p>
                        </div>
                        <Toggle checked={visual.show_store_logo !== false} onChange={(v) => updateCheckout('visual.show_store_logo', v)} />
                    </div>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Show Branding</p>
                            <p className="text-xs text-gray-500">Display platform branding in the checkout footer</p>
                        </div>
                        <Toggle checked={checkout?.show_branding || false} onChange={(v) => updateCheckout('show_branding', v)} />
                    </div>
                </div>
            </div>

            {/* ── Preview Summary ── */}
            <AppearanceSummary appearance={appearance} visual={visual} />
        </div>
    );
}

function PresetPreview({ preset, isActive }) {
    const previews = {
        modern: (
            <div className="space-y-1">
                <div className="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded" />
                <div className="h-1.5 w-3/4 bg-gray-200 dark:bg-gray-700 rounded" />
                <div className="h-2 w-full bg-blue-400 rounded" />
            </div>
        ),
        minimal: (
            <div className="space-y-1">
                <div className="h-1 w-full bg-gray-300 dark:bg-gray-600" />
                <div className="h-1 w-3/4 bg-gray-300 dark:bg-gray-600" />
                <div className="h-2 w-full border border-blue-400 rounded-none" />
            </div>
        ),
        elegant: (
            <div className="space-y-1.5">
                <div className="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full" />
                <div className="h-1.5 w-3/4 bg-gray-200 dark:bg-gray-700 rounded-full" />
                <div className="h-2.5 w-full bg-blue-400 rounded-full" />
            </div>
        ),
        compact: (
            <div className="space-y-0.5">
                <div className="h-1 w-full bg-gray-200 dark:bg-gray-700 rounded-sm" />
                <div className="h-1 w-3/4 bg-gray-200 dark:bg-gray-700 rounded-sm" />
                <div className="h-1.5 w-full bg-blue-400 rounded-sm" />
            </div>
        ),
        friendly: (
            <div className="space-y-1">
                <div className="h-1.5 w-full bg-blue-100 dark:bg-blue-900/40 rounded-full" />
                <div className="h-1.5 w-3/4 bg-blue-100 dark:bg-blue-900/40 rounded-full" />
                <div className="h-2 w-full bg-blue-400 rounded-full" />
            </div>
        ),
    };
    return <div className="px-2 py-1">{previews[preset]}</div>;
}

function AppearanceSummary({ appearance, visual }) {
    const summary = [
        { label: 'Preset', value: visual.preset || 'modern' },
        { label: 'Card', value: appearance.card_style || 'bordered' },
        { label: 'Input', value: appearance.input_style || 'bordered' },
        { label: 'Button', value: `${appearance.button_style || 'solid'} / ${appearance.button_size || 'md'}` },
        { label: 'Radius', value: appearance.border_radius || 'medium' },
        { label: 'Spacing', value: appearance.section_spacing || 'normal' },
        { label: 'Sections', value: appearance.section_style || 'card' },
        { label: 'Summary', value: visual.order_summary_style || 'card' },
        { label: 'Images', value: appearance.product_image_size || 'medium' },
    ];

    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Current Design</h3>
            <div className="flex flex-wrap gap-2">
                {summary.map(({ label, value }) => (
                    <span key={label} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[11px]">
                        <span className="text-gray-400">{label}:</span>
                        <span className="font-medium text-gray-700 dark:text-gray-300 capitalize">{value}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}

function LayoutEditor({ checkout, updateCheckout }) {
    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Layout</h2>
                <p className="text-sm text-gray-500 mt-1">Control the order summary position and display options.</p>
            </div>
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-5">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Order Summary</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <SelectField label="Position" value={checkout?.layout?.order_summary_position || 'right'} options={['right', 'left', 'bottom']} onChange={(v) => updateCheckout('layout.order_summary_position', v)} />
                        <Toggle label="Show on Mobile" checked={checkout?.layout?.show_order_summary_on_mobile !== false} onChange={(v) => updateCheckout('layout.show_order_summary_on_mobile', v)} />
                    </div>
                </div>
                <div className="border-t border-gray-200 dark:border-gray-700 pt-5">
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Header</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Toggle label="Show Store Logo" checked={checkout?.visual?.show_store_logo !== false} onChange={(v) => updateCheckout('visual.show_store_logo', v)} />
                        <Toggle label="Show Branding" checked={checkout?.show_branding || false} onChange={(v) => updateCheckout('show_branding', v)} />
                    </div>
                </div>
            </div>
        </div>
    );
}

function ResponsiveEditor({ checkout, updateCheckout }) {
    const [activeDevice, setActiveDevice] = useState('desktop');
    const sections = checkout?.sections || {};
    const layout = checkout?.layout || {};
    const appearance = checkout?.appearance || {};

    const sortedSections = Object.entries(sections).sort((a, b) => (a[1].order || 0) - (b[1].order || 0));

    const devices = [
        { key: 'desktop', label: 'Desktop', icon: Monitor, width: '100%', desc: 'Full-width layout' },
        { key: 'tablet', label: 'Tablet', icon: Tablet, width: '768px', desc: '768px viewport' },
        { key: 'mobile', label: 'Mobile', icon: Smartphone, width: '375px', desc: '375px viewport' },
    ];

    const sectionMeta = {
        address: { label: 'Delivery Address', icon: MapPin },
        delivery: { label: 'Delivery Options', icon: Truck },
        packaging: { label: 'Packaging', icon: Package },
        payment: { label: 'Payment Method', icon: CreditCard },
        coupon: { label: 'Coupon', icon: Receipt },
        summary: { label: 'Order Summary', icon: FileText },
        notes: { label: 'Order Notes', icon: MessageSquare },
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Responsive</h2>
                <p className="text-sm text-gray-500 mt-1">Control how checkout behaves across different devices.</p>
            </div>

            {/* ── Device Preview Selector ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Preview Device</h3>
                <p className="text-xs text-gray-500 mb-4">Select a device to see how settings affect that viewport.</p>
                <div className="grid grid-cols-3 gap-3">
                    {devices.map(({ key, label, icon: Icon, width, desc }) => {
                        const isActive = activeDevice === key;
                        return (
                            <button key={key} type="button" onClick={() => setActiveDevice(key)}
                                className={`p-4 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                <div className="flex items-center gap-3 mb-2">
                                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${isActive ? 'bg-blue-100 dark:bg-blue-800/40' : 'bg-gray-100 dark:bg-gray-800'}`}>
                                        <Icon className={`w-5 h-5 ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500'}`} />
                                    </div>
                                    {isActive && <span className="w-2 h-2 rounded-full bg-blue-500 ml-auto" />}
                                </div>
                                <span className={`text-sm font-semibold block ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100'}`}>{label}</span>
                                <span className="text-[11px] text-gray-400 block mt-0.5">{width} &middot; {desc}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Section Device Visibility ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Section Visibility</h3>
                        <p className="text-xs text-gray-500 mt-0.5">Show or hide sections per device. Changes apply to {activeDevice} view.</p>
                    </div>
                    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs text-gray-500">
                        {activeDevice === 'desktop' && <Monitor className="w-3.5 h-3.5" />}
                        {activeDevice === 'tablet' && <Tablet className="w-3.5 h-3.5" />}
                        {activeDevice === 'mobile' && <Smartphone className="w-3.5 h-3.5" />}
                        <span className="capitalize font-medium">{activeDevice}</span>
                    </div>
                </div>

                <div className="space-y-2">
                    {sortedSections.map(([key, section]) => {
                        const meta = sectionMeta[key] || { label: key, icon: FileText };
                        const Icon = meta.icon;
                        const isVisible = section.visible !== false;
                        const deviceKey = activeDevice === 'desktop' ? 'desktop_visible' : 'mobile_visible';
                        const deviceVisible = section[deviceKey] !== false;

                        return (
                            <div key={key} className={`flex items-center gap-3 p-3 rounded-xl border transition-all ${isVisible ? 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50'}`}>
                                <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${isVisible ? 'bg-gray-100 dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900'}`}>
                                    <Icon className={`w-4 h-4 ${isVisible ? 'text-gray-600 dark:text-gray-400' : 'text-gray-300 dark:text-gray-600'}`} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <span className={`text-sm font-medium ${isVisible ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400'}`}>{section.title || meta.label}</span>
                                    {!isVisible && <span className="ml-2 text-[10px] text-gray-400 uppercase">Disabled</span>}
                                </div>
                                {isVisible ? (
                                    <button type="button" role="switch" aria-checked={deviceVisible}
                                        aria-label={`Show ${meta.label} on ${activeDevice}`}
                                        onClick={() => updateCheckout(`sections.${key}.${deviceKey}`, !deviceVisible)}
                                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${deviceVisible ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
                                        <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${deviceVisible ? 'translate-x-5' : 'translate-x-0'}`} />
                                    </button>
                                ) : (
                                    <div className="w-11 h-6 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <span className="text-[9px] text-gray-400 uppercase">Off</span>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* ── Layout by Device ── */}
            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Layout Settings</h3>
                <p className="text-xs text-gray-500 mb-4">Configure layout behavior for the selected device.</p>

                <div className="space-y-5">
                    {/* Order Summary Position (desktop only) */}
                    {activeDevice === 'desktop' && (
                        <div>
                            <label className="text-xs font-medium text-gray-500 mb-2 block">Order Summary Position</label>
                            <div className="grid grid-cols-2 gap-3">
                                {[
                                    { value: 'right', label: 'Right Side', desc: 'Summary appears on the right' },
                                    { value: 'left', label: 'Left Side', desc: 'Summary appears on the left' },
                                ].map(({ value, label, desc }) => {
                                    const isActive = (layout.order_summary_position || 'right') === value;
                                    return (
                                        <button key={value} type="button" onClick={() => updateCheckout('layout.order_summary_position', value)}
                                            className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className={`text-sm font-semibold ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100'}`}>{label}</span>
                                                {isActive && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                                            </div>
                                            <p className="text-[11px] text-gray-500">{desc}</p>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Mobile Summary */}
                    {activeDevice === 'mobile' && (
                        <div>
                            <label className="text-xs font-medium text-gray-500 mb-2 block">Order Summary on Mobile</label>
                            <div className="grid grid-cols-2 gap-3">
                                {[
                                    { value: true, label: 'Show', desc: 'Expandable summary section below form' },
                                    { value: false, label: 'Hide', desc: 'Summary hidden, only in order review' },
                                ].map(({ value, label, desc }) => {
                                    const isActive = layout.show_order_summary_on_mobile !== false === value;
                                    return (
                                        <button key={String(value)} type="button" onClick={() => updateCheckout('layout.show_order_summary_on_mobile', value)}
                                            className={`p-3 rounded-xl border-2 text-left transition-all ${isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className={`text-sm font-semibold ${isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-gray-100'}`}>{label}</span>
                                                {isActive && <span className="w-2 h-2 rounded-full bg-blue-500" />}
                                            </div>
                                            <p className="text-[11px] text-gray-500">{desc}</p>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Compact Mode */}
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Compact Mode</p>
                            <p className="text-xs text-gray-500">Reduce padding and spacing for smaller screens</p>
                        </div>
                        <button type="button" role="switch" aria-checked={appearance.compact_mode || false}
                            onClick={() => updateCheckout('appearance.compact_mode', !appearance.compact_mode)}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${appearance.compact_mode ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
                            <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${appearance.compact_mode ? 'translate-x-5' : 'translate-x-0'}`} />
                        </button>
                    </div>
                </div>
            </div>

            {/* ── Breakpoint Reference ── */}
            <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Breakpoint Reference</h3>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    {[
                        { label: 'Small', width: '320px', icon: Smartphone },
                        { label: 'Mobile', width: '375px', icon: Smartphone },
                        { label: 'Large Mobile', width: '430px', icon: Smartphone },
                        { label: 'Tablet', width: '768px', icon: Tablet },
                        { label: 'Desktop', width: '1024px', icon: Laptop },
                        { label: 'Large', width: '1280px', icon: Maximize },
                    ].map(({ label, width, icon: Icon }) => (
                        <div key={width} className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <Icon className="w-3.5 h-3.5 text-gray-400" />
                            <div>
                                <span className="text-[10px] font-medium text-gray-700 dark:text-gray-300 block">{label}</span>
                                <span className="text-[9px] text-gray-400 font-mono">{width}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ────────────────────────────────────────────
   Management Tab Panels (Delivery, Packaging, COD)
   ──────────────────────────────────────────── */

function DeliveryTab({ deliveryServices, cities }) {
    const sorted = [...deliveryServices].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

    function handleToggle(id) {
        router.post(adminUrl(`/admin/delivery-services/${id}/toggle`), {}, { preserveScroll: true });
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Delivery Services</h2>
                    <p className="text-sm text-gray-500 mt-1">Manage delivery methods, fees, and city-specific pricing.</p>
                </div>
                <Link href={adminUrl('/admin/delivery-services/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Service
                </Link>
            </div>

            {!sorted.length ? (
                <div className="text-center py-16 text-gray-500 dark:text-gray-400 text-sm rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    No delivery services configured yet.
                    <Link href={adminUrl('/admin/delivery-services/create')} className="block mt-2 text-blue-600 hover:underline">Create your first delivery service</Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {sorted.map((service) => (
                        <div key={service.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{service.name}</span>
                                        <span className="text-xs text-gray-400 font-mono">{service.code}</span>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide ${service.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {service.is_active ? 'Active' : 'Inactive'}
                                        </span>
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
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Packaging Options</h2>
                    <p className="text-sm text-gray-500 mt-1">Manage packaging types and fees offered during checkout.</p>
                </div>
                <Link href={adminUrl('/admin/packaging-options/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Packaging
                </Link>
            </div>

            {!sorted.length ? (
                <div className="text-center py-16 text-gray-500 dark:text-gray-400 text-sm rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    No packaging options configured yet.
                    <Link href={adminUrl('/admin/packaging-options/create')} className="block mt-2 text-blue-600 hover:underline">Create your first packaging option</Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {sorted.map((option) => (
                        <div key={option.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{option.name}</span>
                                        <span className="text-xs text-gray-400 font-mono">{option.code}</span>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide ${option.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {option.is_active ? 'Active' : 'Inactive'}
                                        </span>
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
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">COD Rules</h2>
                    <p className="text-sm text-gray-500 mt-1">Configure Cash on Delivery eligibility, fees, and city restrictions.</p>
                </div>
                <Link href={adminUrl('/admin/cod-rules/create')} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                    Add Rule
                </Link>
            </div>

            {!codRules?.length ? (
                <div className="text-center py-16 text-gray-500 dark:text-gray-400 text-sm rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    No COD rules configured yet.
                    <Link href={adminUrl('/admin/cod-rules/create')} className="block mt-2 text-blue-600 hover:underline">Create your first COD rule</Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {codRules.map((rule) => (
                        <div key={rule.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{rule.name}</span>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wide ${rule.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {rule.is_active ? 'Active' : 'Inactive'}
                                        </span>
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

/* ────────────────────────────────────────────
   Shared Form Components
   ──────────────────────────────────────────── */

function Field({ label, value, onChange, type = 'text', placeholder = '' }) {
    return (
        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {label}
            <input type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
                className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" />
        </label>
    );
}

function TextareaField({ label, value, onChange, placeholder = '' }) {
    return (
        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
            {label}
            <textarea value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} rows={3}
                className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal resize-none" />
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

function Toggle({ checked, onChange }) {
    return (
        <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)}
            className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${checked ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
            <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${checked ? 'translate-x-4' : 'translate-x-0'}`} />
        </button>
    );
}
