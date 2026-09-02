import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

const labels = { hero: 'Hero', promotion: 'Promotions', featured_categories: 'Featured Categories', featured_brands: 'Featured Brands', featured_products: 'Featured Products', product_showcase: 'Product Showcase', store_highlights: 'Store Highlights', brand_story: 'Brand Story', cta: 'Call to Action' };

export default function StorefrontHomepage({ sections: initialSections = [], categories = [], brands = [], products = [], media = [], heroVariants = ['default', 'split', 'centered', 'text-only', 'minimal'], revision = null }) {
    const { tenant } = usePage().props;
    const [sections, setSections] = useState(initialSections);
    const [saving, setSaving] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const update = (id, changes) => setSections((current) => current.map((section) => section.id === id ? { ...section, ...changes } : section));
    const updateConfig = (id, changes) => setSections((current) => current.map((section) => section.id === id ? { ...section, configuration: { ...(section.configuration || {}), ...changes } } : section));
    const move = (index, direction) => { const nextIndex = index + direction; if (nextIndex < 0 || nextIndex >= sections.length) return; setSections((current) => { const next = [...current]; [next[index], next[nextIndex]] = [next[nextIndex], next[index]]; return next; }); };

    const hasUnpublished = revision?.has_unpublished_changes;
    const publishedRevision = revision?.published?.revision_number;

    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        setSaveSuccess(null);
        setSaveError(null);
        router.put(adminUrl('/admin/storefront/homepage'), {
            sections: sections.map((section, position) => ({ ...section, position })),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSaveSuccess('Draft saved successfully. Your storefront has not been updated yet. Preview or publish your changes.');
                setSaving(false);
            },
            onError: (errors) => {
                setSaveError('Could not save draft. Please review the form for errors.');
                setSaving(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    const confirmPublish = () => {
        setPublishing(true);
        router.post(adminUrl('/admin/storefront/publish'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                setShowPublishConfirm(false);
                setPublishing(false);
                setSaveSuccess('Published! Your changes are now live on the storefront.');
            },
            onError: () => {
                setPublishing(false);
            },
            onFinish: () => setPublishing(false),
        });
    };

    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/preview` : null;

    return (
        <AdminLayout>
            <Head title="Homepage Sections" />
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div>
                        <p className="text-sm font-medium text-blue-600">Storefront</p>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Homepage Sections</h1>
                        <p className="text-sm text-gray-500 mt-1">Configure discovery and marketing sections. Empty sections stay hidden from customers.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {hasUnpublished && (
                            <span className="px-3 py-2 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">
                                Unpublished draft
                            </span>
                        )}
                        {publishedRevision && (
                            <span className="px-3 py-2 rounded-lg bg-green-50 text-green-700 text-xs font-medium">
                                Live revision #{publishedRevision}
                            </span>
                        )}
                        {previewUrl && hasUnpublished && (
                            <a href={previewUrl} target="_blank" rel="noreferrer"
                               className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                Preview Draft
                            </a>
                        )}
                        <Link href={adminUrl('/admin/storefront/revisions')} className="text-sm text-blue-600">History</Link>
                    </div>
                </div>

                {saveSuccess && (
                    <div role="status" className="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 flex items-start gap-3">
                        <svg className="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <span>{saveSuccess}</span>
                    </div>
                )}
                {saveError && (
                    <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{saveError}</div>
                )}

                {hasUnpublished && (
                    <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="text-sm text-amber-800">
                            <strong>Unpublished changes.</strong> Your draft changes are not yet visible to customers.
                        </div>
                        <div className="flex gap-2">
                            {previewUrl && (
                                <a href={previewUrl} target="_blank" rel="noreferrer"
                                   className="px-4 py-2 rounded-lg border border-amber-300 bg-white text-sm font-medium text-amber-800 hover:bg-amber-100">
                                    Preview Draft
                                </a>
                            )}
                            <button type="button" onClick={() => setShowPublishConfirm(true)}
                                    className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                Publish
                            </button>
                        </div>
                    </div>
                )}

                <form onSubmit={save} className="space-y-4">
                    {sections.map((section, index) => (
                        <section key={section.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1">
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{labels[section.type] || section.type}</h2>
                                    <p className="text-xs text-gray-500 mt-1">{description(section.type)}</p>
                                </div>
                                <Toggle label="Enabled" checked={section.enabled} onChange={(value) => update(section.id, { enabled: value })} />
                                <div className="flex gap-1">
                                    <button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Up</button>
                                    <button type="button" onClick={() => move(index, 1)} disabled={index === sections.length - 1} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Down</button>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-end gap-4 mt-4">
                                <Toggle label="Desktop" checked={section.desktop_visible} onChange={(value) => update(section.id, { desktop_visible: value })} />
                                <Toggle label="Mobile" checked={section.mobile_visible} onChange={(value) => update(section.id, { mobile_visible: value })} />
                                <VariantField value={section.variant || 'default'} options={variantOptions(section.type, heroVariants)} onChange={(value) => update(section.id, { variant: value })} />
                            </div>
                            <SectionConfig section={section} categories={categories} brands={brands} products={products} media={media} updateConfig={updateConfig} />
                        </section>
                    ))}
                    <div className="flex justify-end gap-3">
                        <button type="submit" disabled={saving} className="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                            {saving ? 'Saving Draft...' : 'Save Draft'}
                        </button>
                        {hasUnpublished && (
                            <button type="button" onClick={() => setShowPublishConfirm(true)} disabled={publishing}
                                    className="px-5 py-2.5 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
                                {publishing ? 'Publishing...' : 'Publish'}
                            </button>
                        )}
                    </div>
                </form>
            </div>

            {showPublishConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Publish homepage changes?</h2>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Your draft changes will become visible to customers on the storefront. The previous published revision remains recoverable from the revision history.</p>
                        <div className="flex justify-end gap-2 mt-6">
                            <button type="button" onClick={() => setShowPublishConfirm(false)} className="px-4 py-2 rounded-lg border text-sm">Cancel</button>
                            <button type="button" onClick={confirmPublish} className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Publish Changes</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

function SectionConfig({ section, categories, brands, products, media, updateConfig }) {
    const config = section.configuration || {};
    if (section.type === 'hero') return <p className="mt-5 pt-4 border-t text-sm text-gray-500">Hero layout and media are managed in the Storefront settings. Media is managed in the Storefront Media Library.</p>;
    if (section.type === 'promotion') return <p className="mt-5 pt-4 border-t text-sm text-gray-500">Create and schedule promotions from <Link href={adminUrl('/admin/storefront/promotions')} className="text-blue-600">Promotions & Campaigns</Link>.</p>;
    if (section.type === 'featured_categories') return <div className="mt-5 pt-4 border-t"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2 mb-3">Only categories marked as <strong>Featured</strong> in the Category list will appear on the storefront. Select categories below to prioritize ordering when many are featured.</p><SelectionList title="Categories" items={categories} selected={config.category_ids || []} onChange={(ids) => updateConfig(section.id, { category_ids: ids, limit: config.limit || 6 })} /></div>;
    if (section.type === 'featured_brands') return <div className="mt-5 pt-4 border-t"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2 mb-3">Only brands marked as <strong>Featured</strong> in the Brand list will appear on the storefront. Select brands below to prioritize ordering when many are featured.</p><SelectionList title="Brands" items={brands} selected={config.brand_ids || []} onChange={(ids) => updateConfig(section.id, { brand_ids: ids, limit: config.limit || 6 })} /></div>;
    if (section.type === 'featured_products' || section.type === 'product_showcase') return <div className="mt-5 pt-4 border-t space-y-4"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2">Only products marked as <strong>Featured</strong> in the Product edit form will appear on the storefront. Select products below to prioritize ordering when many are featured.</p><Field label="Section title" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Description" value={config.description || ''} onChange={(value) => updateConfig(section.id, { description: value })} /><SelectionList title="Products" items={products} selected={config.product_ids || []} onChange={(ids) => updateConfig(section.id, { product_ids: ids, limit: config.limit || 8 })} /></div>;
    if (section.type === 'store_highlights') return <Highlights items={config.items || []} onChange={(items) => updateConfig(section.id, { items })} />;
    if (section.type === 'brand_story') return <div className="mt-5 pt-4 border-t grid grid-cols-1 sm:grid-cols-2 gap-4"><p className="sm:col-span-2 text-sm text-gray-500">Brand Story content comes from Website Settings → About Us. This section controls presentation, visibility, optional media, and the CTA.</p><Field label="Button label" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /><Field label="Button link" value={config.button_link || ''} onChange={(value) => updateConfig(section.id, { button_link: value })} /><MediaSelect config={config} media={media} onChange={(media_id) => updateConfig(section.id, { media_id })} /></div>;
    if (section.type === 'cta') return <div className="mt-5 pt-4 border-t grid grid-cols-1 sm:grid-cols-2 gap-4"><Field label="Title" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Button label" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /><Field label="Description" value={config.description || ''} onChange={(value) => updateConfig(section.id, { description: value })} /><Field label="Button link" value={config.button_link || ''} onChange={(value) => updateConfig(section.id, { button_link: value })} /><MediaSelect config={config} media={media} onChange={(media_id) => updateConfig(section.id, { media_id })} /></div>;
    return null;
}

function SelectionList({ title, items, selected, onChange }) { const values = selected.map(Number); const toggle = (id) => onChange(values.includes(Number(id)) ? values.filter((value) => value !== Number(id)) : [...values, Number(id)]); return <div className="mt-5 pt-4 border-t"><p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{title}</p><div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-56 overflow-y-auto">{items.map((item) => <label key={item.id} className="flex items-center gap-2 rounded border border-gray-200 dark:border-gray-700 p-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" checked={values.includes(Number(item.id))} onChange={() => toggle(item.id)} className="rounded border-gray-300 text-blue-600" />{item.name}</label>)}</div></div>; }
function Highlights({ items, onChange }) { const update = (index, key, value) => onChange(items.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item)); const add = () => onChange([...items, { icon: 'star', title: '', description: '' }]); return <div className="mt-5 pt-4 border-t space-y-3">{items.map((item, index) => <div key={index} className="grid grid-cols-1 sm:grid-cols-3 gap-2"><select value={item.icon || 'star'} onChange={(event) => update(index, 'icon', event.target.value)} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"><option value="star">Star</option><option value="truck">Delivery</option><option value="shield">Secure</option><option value="headset">Support</option><option value="heart">Care</option></select><input value={item.title || ''} onChange={(event) => update(index, 'title', event.target.value)} placeholder="Title" className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" /><input value={item.description || ''} onChange={(event) => update(index, 'description', event.target.value)} placeholder="Description" className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" /></div>)}<button type="button" onClick={add} disabled={items.length >= 6} className="text-sm text-blue-600 disabled:opacity-40">+ Add highlight</button></div>; }
function Field({ label, value, onChange }) { return <label className="text-sm font-medium text-gray-700 dark:text-gray-300">{label}<input value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>; }
function MediaSelect({ config, media, onChange }) { return <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Optional image<select value={config.media_id || ''} onChange={(event) => onChange(event.target.value || null)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal"><option value="">No image</option>{media.map((item) => <option key={item.id} value={item.id}>{item.alt_text || `Media #${item.id}`}</option>)}</select></label>; }
function Toggle({ label, checked, onChange }) { return <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-gray-300 text-blue-600" />{label}</label>; }
function VariantField({ value, options, onChange }) { return <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Layout<select value={value} onChange={(event) => onChange(event.target.value)} className="block mt-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"><option value="default">Theme default</option>{options.map((option) => <option key={option} value={option}>{option}</option>)}</select></label>; }
function variantOptions(type, heroVariants = []) { return { hero: heroVariants, featured_categories: ['default', 'grid', 'horizontal', 'compact'], featured_brands: ['default', 'grid', 'horizontal', 'compact'], featured_products: ['default', 'grid', 'compact', 'image-focused', 'horizontal'], product_showcase: ['default', 'grid', 'compact', 'image-focused', 'horizontal'], brand_story: ['default', 'split', 'text-only'], cta: ['default', 'centered', 'full-width'] }[type] || []; }
function description(type) { return { promotion: 'Display active scheduled campaigns.', featured_categories: 'Help customers discover collections.', featured_brands: 'Showcase selected brands.', featured_products: 'Show selected products near the top of the store.', product_showcase: 'Present a selected product group.', store_highlights: 'Explain why customers should shop with you.', brand_story: 'Tell your store story with an optional image.', cta: 'End the page with a clear next action.' }[type] || 'Configure this homepage section.'; }