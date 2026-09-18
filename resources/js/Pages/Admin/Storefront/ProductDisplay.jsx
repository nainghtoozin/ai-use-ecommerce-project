import { useState, useRef, useCallback, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Tag, DollarSign, Boxes, MousePointerClick, Eye } from 'lucide-react';
import { DraftBadge, EditorCard, FieldError, FIELD_INPUT, LiveBadge, Notice, OutlineButton, PublishConfirmModal, SAVE_STATUS, SaveStatusText, SectionHeader, SuccessButton } from '@/Components/Admin/StorefrontUI';

const STOCK_MODES = [
    { value: 'status', label: 'Stock Status', help: 'Shows In Stock / Low Stock / Out of Stock.' },
    { value: 'quantity', label: 'Quantity', help: 'Shows available quantity.' },
    { value: 'status_quantity', label: 'Status + Quantity', help: 'Shows both status and quantity.' },
    { value: 'hidden', label: 'Hide Stock', help: 'Does not show stock information to customers.' },
];

const INFO_ROWS = [
    ['product_info.show_category', 'Category', 'Show the product category on cards and detail pages.'],
    ['product_info.show_brand', 'Brand', 'Show the product brand on cards and detail pages.'],
    ['product_info.show_product_type', 'Product Type', 'Show whether the product is single, variable, or combo.'],
    ['product_info.show_sku', 'SKU', 'Show the product SKU on the detail page.'],
];

const PRICING_ROWS = [
    ['pricing.show_original_price', 'Original Price', 'Show the crossed-out price when a discount applies.'],
    ['pricing.show_savings', 'Savings', 'Show the "Save K…" amount when a discount applies.'],
    ['pricing.show_discount_percentage', 'Discount Percentage', 'Show the "−10%" badge when a discount applies.'],
];

const ACTION_ROWS = [
    ['actions.show_add_to_cart', 'Add to Cart', 'Display preference only. Cart rules are unchanged.'],
    ['actions.show_view_product', 'View Product', 'Display preference only. Product pages stay reachable.'],
    ['actions.show_wishlist', 'Wishlist', 'Display preference only. Wishlist rules are unchanged.'],
];

function getPath(obj, path) {
    return path.split('.').reduce((acc, key) => acc?.[key], obj);
}

export default function ProductDisplay({ storefront, revision }) {
    const { tenant } = usePage().props;
    const [config, setConfig] = useState(storefront?.product_display || {});
    const [saveStatus, setSaveStatus] = useState(SAVE_STATUS.IDLE);
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);

    const dirtyRef = useRef(false);
    const configRef = useRef(config);
    const savingRef = useRef(false);
    configRef.current = config;

    const hasUnpublished = revision?.has_unpublished_changes;
    const publishedRevision = revision?.published?.revision_number;
    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/preview` : null;

    const doSave = useCallback(() => {
        if (savingRef.current) return;
        savingRef.current = true;
        setSaveStatus(SAVE_STATUS.SAVING);
        setSaveSuccess(null);
        setSaveError(null);
        setFieldErrors({});
        router.put(adminUrl('/admin/storefront/product-display'), { ...configRef.current }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savingRef.current = false;
                dirtyRef.current = false;
                setSaveStatus(SAVE_STATUS.SAVED);
                setSaveSuccess('Product display settings saved to draft.');
                window.setTimeout(() => setSaveSuccess(null), 4000);
            },
            onError: (errors) => {
                savingRef.current = false;
                setSaveStatus(SAVE_STATUS.FAILED);
                setFieldErrors(errors || {});
                setSaveError('Could not save changes. Please fix the highlighted fields.');
            },
            onFinish: () => { savingRef.current = false; },
        });
    }, []);

    const updateConfig = useCallback((path, value) => {
        dirtyRef.current = true;
        setSaveStatus(SAVE_STATUS.UNSAVED);
        setConfig(prev => {
            const next = { ...prev };
            const keys = path.split('.');
            let ref = next;
            for (let i = 0; i < keys.length - 1; i++) {
                ref[keys[i]] = { ...(ref[keys[i]] || {}) };
                ref = ref[keys[i]];
            }
            ref[keys[keys.length - 1]] = value;
            return next;
        });
    }, []);

    useEffect(() => { setConfig(storefront?.product_display || {}); }, [storefront?.product_display]);

    const handleSaveDraft = useCallback(() => { doSave(); }, [doSave]);

    const handlePreview = useCallback(() => {
        if (dirtyRef.current || savingRef.current) {
            doSave();
            window.setTimeout(() => { if (previewUrl) window.open(previewUrl, '_blank'); }, 600);
        } else if (previewUrl) window.open(previewUrl, '_blank');
    }, [previewUrl, doSave]);

    const handlePublishClick = useCallback(() => {
        if (dirtyRef.current) { doSave(); window.setTimeout(() => setShowPublishConfirm(true), 600); }
        else setShowPublishConfirm(true);
    }, [doSave]);

    const confirmPublish = useCallback(() => {
        router.post(adminUrl('/admin/storefront/publish'), {}, {
            preserveScroll: true,
            onSuccess: () => { setShowPublishConfirm(false); setSaveSuccess('Published! Product display changes are now live.'); },
        });
    }, []);

    const stock = config.stock || {};
    const thresholdError = fieldErrors['stock.low_stock_threshold'];
    const modeError = fieldErrors['stock.display_mode'];

    return (
        <AdminLayout>
            <Head title="Product Display" />
            <div className="max-w-full mx-auto">
                <div className="sticky top-0 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-b border-gray-200 dark:border-gray-800 shadow-sm">
                    <div className="px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-14">
                            <div className="flex items-center gap-3 min-w-0">
                                <Link href={adminUrl('/admin/storefront')} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-sm shrink-0">
                                    Storefront
                                </Link>
                                <span className="text-gray-300 dark:text-gray-600">/</span>
                                <h1 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">Product Display</h1>
                                {hasUnpublished && <DraftBadge className="hidden sm:inline-flex" />}
                                {publishedRevision && <LiveBadge className="hidden sm:inline-flex">Live #{publishedRevision}</LiveBadge>}
                            </div>
                            <div className="flex items-center gap-2">
                                <SaveStatusText status={saveStatus} onRetry={handleSaveDraft} />
                                <OutlineButton size="sm" onClick={handleSaveDraft} className="shrink-0">Save Draft</OutlineButton>
                                {previewUrl && <OutlineButton size="sm" onClick={handlePreview} className="hidden sm:inline-flex shrink-0"><Eye className="w-3.5 h-3.5" />Preview</OutlineButton>}
                                {hasUnpublished && <SuccessButton size="sm" onClick={handlePublishClick} className="shrink-0">Publish</SuccessButton>}
                            </div>
                        </div>
                    </div>
                </div>

                {saveSuccess && <Notice tone="success" className="mx-4 sm:mx-6 lg:mx-8 mt-4">{saveSuccess}</Notice>}
                {saveError && <Notice tone="error" className="mx-4 sm:mx-6 lg:mx-8 mt-4">{saveError}</Notice>}

                <main className="flex-1 min-w-0">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 space-y-5">

                        <EditorCard>
                            <SectionHeader title="Product Information" description="Choose which product details customers see. Product name is always shown." />
                            <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                {INFO_ROWS.map(([path, label, help]) => (
                                    <ToggleRow key={path} label={label} help={help} checked={getPath(config, path) !== false} onChange={(v) => updateConfig(path, v)} />
                                ))}
                            </div>
                        </EditorCard>

                        <EditorCard>
                            <SectionHeader title="Pricing Display" description="Control which price elements customers see." />
                            <div className="flex items-center justify-between gap-3 py-2.5">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">Current Price</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Required for purchasing. Cannot be disabled.</p>
                                </div>
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 shrink-0">Locked on</span>
                            </div>
                            <div className="divide-y divide-gray-100 dark:divide-gray-800 border-t border-gray-100 dark:border-gray-800">
                                {PRICING_ROWS.map(([path, label, help]) => (
                                    <ToggleRow key={path} label={label} help={help} checked={getPath(config, path) !== false} onChange={(v) => updateConfig(path, v)} />
                                ))}
                            </div>
                        </EditorCard>

                        <EditorCard>
                            <SectionHeader title="Stock Display" description="Control how availability is shown to customers." />
                            <div className="space-y-1.5" role="radiogroup" aria-label="Stock display mode">
                                {STOCK_MODES.map((mode) => {
                                    const selected = (stock.display_mode || 'status') === mode.value;
                                    return (
                                        <label key={mode.value} className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors ${selected ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}>
                                            <input
                                                type="radio"
                                                name="stock-display-mode"
                                                value={mode.value}
                                                checked={selected}
                                                onChange={() => updateConfig('stock.display_mode', mode.value)}
                                                className="mt-0.5 accent-blue-600"
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-sm font-medium text-gray-900 dark:text-gray-100">{mode.label}</span>
                                                <span className="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">{mode.help}</span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {modeError && <FieldError error={modeError} />}
                            <div className="mt-4">
                                <label htmlFor="low-stock-threshold" className="block text-sm font-medium text-gray-900 dark:text-gray-100">Low Stock Threshold</label>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Items at or below this quantity show as "Low Stock".</p>
                                <input
                                    id="low-stock-threshold"
                                    type="number"
                                    min={0}
                                    max={1000}
                                    value={stock.low_stock_threshold ?? 10}
                                    onChange={(e) => updateConfig('stock.low_stock_threshold', e.target.value === '' ? '' : Number(e.target.value))}
                                    className={`${FIELD_INPUT} max-w-32 mt-1.5`}
                                />
                                {thresholdError && <FieldError error={thresholdError} />}
                            </div>
                            <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <ToggleRow label="Show “Out of Stock”" help="Show an Out of Stock badge for unavailable items." checked={stock.show_out_of_stock !== false} onChange={(v) => updateConfig('stock.show_out_of_stock', v)} />
                            </div>
                        </EditorCard>

                        <EditorCard>
                            <SectionHeader title="Customer Actions" description="Display preferences only. The underlying cart, product, and wishlist rules are unchanged." />
                            <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                {ACTION_ROWS.map(([path, label, help]) => (
                                    <ToggleRow key={path} label={label} help={help} checked={getPath(config, path) !== false} onChange={(v) => updateConfig(path, v)} />
                                ))}
                            </div>
                        </EditorCard>

                    </div>
                </main>
            </div>

            {showPublishConfirm && (
                <PublishConfirmModal
                    title="Publish product display changes?"
                    description="Your draft changes will become visible to customers on product cards and detail pages."
                    onCancel={() => setShowPublishConfirm(false)}
                    onConfirm={confirmPublish}
                />
            )}
        </AdminLayout>
    );
}

function ToggleRow({ label, help, checked, onChange }) {
    return (
        <div className="flex items-center justify-between gap-3 py-2.5">
            <div className="min-w-0">
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                    {label === 'Category' && <Tag className="w-3.5 h-3.5 text-gray-400" />}
                    {label === 'Original Price' && <DollarSign className="w-3.5 h-3.5 text-gray-400" />}
                    {(label === 'Stock Status' || label === 'Show “Out of Stock”') && <Boxes className="w-3.5 h-3.5 text-gray-400" />}
                    {(label === 'Add to Cart' || label === 'View Product' || label === 'Wishlist') && <MousePointerClick className="w-3.5 h-3.5 text-gray-400" />}
                    {label}
                </p>
                {help && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{help}</p>}
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${checked ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}
            >
                <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${checked ? 'translate-x-4' : 'translate-x-0'}`} />
            </button>
        </div>
    );
}
