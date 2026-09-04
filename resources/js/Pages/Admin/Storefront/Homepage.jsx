import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Upload, X } from 'lucide-react';

const labels = { hero: 'Hero', promotion: 'Promotions', featured_categories: 'Featured Categories', featured_brands: 'Featured Brands', featured_products: 'Featured Products', product_showcase: 'Product Showcase', store_highlights: 'Store Highlights', brand_story: 'Brand Story', cta: 'Call to Action' };

const SAVE_STATUS = { IDLE: 'idle', UNSAVED: 'unsaved', SAVING: 'saving', SAVED: 'saved', FAILED: 'failed' };
const DEBOUNCE_MS = 1000;

export default function StorefrontHomepage({ sections: initialSections = [], categories = [], brands = [], products = [], media = [], heroVariants = ['modern-split', 'full-background', 'centered-minimal', 'image-carousel', 'text-only'], revision = null }) {
    const { tenant, flash } = usePage().props;
    const [sections, setSections] = useState(initialSections);
    const [saving, setSaving] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [saveStatus, setSaveStatus] = useState(SAVE_STATUS.IDLE);
    const hasUnpublished = revision?.has_unpublished_changes;
    const publishedRevision = revision?.published?.revision_number;
    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/preview` : null;

    const statusColor = { idle: 'text-emerald-600', unsaved: 'text-amber-600', saving: 'text-blue-600', saved: 'text-emerald-600', failed: 'text-red-600' };
    const statusLabel = { idle: 'All changes saved', unsaved: 'Unsaved changes', saving: 'Saving…', saved: '✓ Draft saved', failed: 'Couldn\'t save changes' };

    const dirtyRef = useRef(false);
    const timerRef = useRef(null);
    const sectionsRef = useRef(sections);
    const savingRef = useRef(false);
    const pendingAfterSaveRef = useRef(false);
    const doSaveRef = useRef(null);
    const flushActionRef = useRef(null);
    sectionsRef.current = sections;

    const doSave = useCallback(() => {
        if (savingRef.current) {
            pendingAfterSaveRef.current = true;
            return;
        }
        savingRef.current = true;
        setSaving(true);
        setSaveSuccess(null);
        setSaveError(null);
        setSaveStatus(SAVE_STATUS.SAVING);
        const payload = {
            sections: sectionsRef.current.map((section, position) => ({ ...section, position })),
        };
        router.put(adminUrl('/admin/storefront/homepage'), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savingRef.current = false;
                dirtyRef.current = false;
                setSaving(false);
                setSaveSuccess('Draft saved successfully.');
                setSaveStatus(SAVE_STATUS.SAVED);
                if (pendingAfterSaveRef.current) {
                    pendingAfterSaveRef.current = false;
                    dirtyRef.current = true;
                    if (doSaveRef.current) doSaveRef.current();
                }
            },
            onError: () => {
                savingRef.current = false;
                setSaving(false);
                setSaveError('Could not save draft.');
                setSaveStatus(SAVE_STATUS.FAILED);
            },
            onFinish: () => {
                savingRef.current = false;
                setSaving(false);
            },
        });
    }, []);
    doSaveRef.current = doSave;

    const markDirty = useCallback(() => {
        dirtyRef.current = true;
        setSaveStatus(SAVE_STATUS.UNSAVED);
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            if (dirtyRef.current && doSaveRef.current) doSaveRef.current();
        }, DEBOUNCE_MS);
    }, []);

    const update = useCallback((id, changes) => {
        setSections((current) => current.map((section) => section.id === id ? { ...section, ...changes } : section));
        markDirty();
    }, [markDirty]);

    const updateConfig = useCallback((id, changes) => {
        setSections((current) => current.map((section) => section.id === id ? { ...section, configuration: { ...(section.configuration || {}), ...changes } } : section));
        markDirty();
    }, [markDirty]);

    const move = useCallback((index, direction) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= sections.length) return;
        setSections((current) => { const next = [...current]; [next[index], next[nextIndex]] = [next[nextIndex], next[index]]; return next; });
        markDirty();
    }, [sections.length, markDirty]);

    useEffect(() => {
        return () => { if (timerRef.current) clearTimeout(timerRef.current); };
    }, []);

    const flushBeforeAction = useCallback((action) => {
        if (timerRef.current) clearTimeout(timerRef.current);
        if (dirtyRef.current || savingRef.current) {
            pendingAfterSaveRef.current = false;
            savingRef.current = false;
            dirtyRef.current = true;
            if (doSaveRef.current) doSaveRef.current();
            setTimeout(action, 300);
        } else {
            action();
        }
    }, []);

    const handleRetry = useCallback(() => {
        dirtyRef.current = true;
        if (doSaveRef.current) doSaveRef.current();
    }, []);

    const handlePreview = useCallback(() => {
        const url = previewUrl;
        const doFlush = flushActionRef.current;
        if (doFlush) {
            doFlush(() => { window.open(url, '_blank'); });
        } else {
            window.open(url, '_blank');
        }
    }, [previewUrl]);

    const handlePublishClick = useCallback(() => {
        const doFlush = flushActionRef.current;
        if (doFlush) {
            doFlush(() => { setShowPublishConfirm(true); });
        } else {
            setShowPublishConfirm(true);
        }
    }, []);
    flushActionRef.current = flushBeforeAction;

    const confirmPublish = useCallback(() => {
        setPublishing(true);
        router.post(adminUrl('/admin/storefront/publish'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                setShowPublishConfirm(false);
                setPublishing(false);
                setSaveSuccess('Published! Your changes are now live on the storefront.');
            },
            onError: () => setPublishing(false),
            onFinish: () => setPublishing(false),
        });
    }, []);

    return (
        <AdminLayout>
            <Head title="Homepage Sections" />
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
                    <div>
                        <p className="text-sm font-medium text-blue-600">Storefront</p>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Homepage Sections</h1>
                        <p className="text-sm text-gray-500 mt-1">Configure discovery and marketing sections. Empty sections stay hidden from customers.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {hasUnpublished && <span className="px-3 py-2 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">Unpublished draft</span>}
                        {publishedRevision && <span className="px-3 py-2 rounded-lg bg-green-50 text-green-700 text-xs font-medium">Live revision #{publishedRevision}</span>}
                        <Link href={adminUrl('/admin/storefront/revisions')} className="text-sm text-blue-600">History</Link>
                    </div>
                </div>

                {saveSuccess && <div role="status" className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{saveSuccess}</div>}
                {saveError && <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{saveError}</div>}

                {hasUnpublished && <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><strong>Unpublished changes.</strong> Your draft changes are not yet visible to customers.</div>}

                {/* Save status + actions */}
                <div className="flex flex-wrap items-center justify-between gap-2 mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
                    <div className="flex items-center gap-2 text-xs">
                        <span className={`font-medium ${statusColor[saveStatus]}`}>
                            {saveStatus === SAVE_STATUS.FAILED ? (
                                <button type="button" onClick={handleRetry} className="underline hover:no-underline">{statusLabel[saveStatus]} — Retry</button>
                            ) : statusLabel[saveStatus]}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        {previewUrl && hasUnpublished && (
                            <button type="button" onClick={handlePreview} className="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Preview Draft</button>
                        )}
                        {hasUnpublished && (
                            <button type="button" onClick={handlePublishClick} disabled={publishing} className="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700 disabled:opacity-50">{publishing ? 'Publishing…' : 'Publish'}</button>
                        )}
                    </div>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-4">
                    {sections.map((section, index) => (
                        <section key={section.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6">
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1"><h2 className="font-semibold text-gray-900 dark:text-gray-100">{labels[section.type] || section.type}</h2><p className="text-xs text-gray-500 mt-1">{description(section.type)}</p></div>
                                <Toggle label="Enabled" checked={section.enabled} onChange={(value) => update(section.id, { enabled: value })} />
                                <div className="flex gap-1"><button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Up</button><button type="button" onClick={() => move(index, 1)} disabled={index === sections.length - 1} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Down</button></div>
                            </div>
                            <div className="flex flex-wrap items-end gap-4 mt-4">
                                <Toggle label="Desktop" checked={section.desktop_visible} onChange={(value) => update(section.id, { desktop_visible: value })} />
                                <Toggle label="Mobile" checked={section.mobile_visible} onChange={(value) => update(section.id, { mobile_visible: value })} />
                                <VariantField value={section.variant || 'modern-split'} options={variantOptions(section.type, heroVariants)} onChange={(value) => update(section.id, { variant: value })} />
                            </div>
                            <SectionConfig section={section} categories={categories} brands={brands} products={products} media={media} updateConfig={updateConfig} />
                        </section>
                    ))}
                </form>
            </div>

            {showPublishConfirm && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl"><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Publish homepage changes?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Your draft changes will become visible to customers on the storefront. The previous published revision remains recoverable.</p><div className="flex justify-end gap-2 mt-6"><button type="button" onClick={() => setShowPublishConfirm(false)} className="px-4 py-2 rounded-lg border text-sm">Cancel</button><button type="button" onClick={confirmPublish} className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Publish Changes</button></div></div></div>}
        </AdminLayout>
    );
}

function HeroImages({ config, updateConfig, section, media }) {
    const [uploading, setUploading] = useState(false);
    const [localImages, setLocalImages] = useState([]);
    const inputRef = useRef(null);

    const savedIds = config.media_ids || [];
    const resolvedFromMedia = savedIds.map((id) => {
        const item = media.find((m) => String(m.id) === String(id));
        return item ? { id: item.id, url: item.url, alt_text: item.alt_text } : null;
    }).filter(Boolean);

    const allImages = [...resolvedFromMedia];
    localImages.forEach((li) => { if (!allImages.some((i) => i.id === li.id)) allImages.push(li); });

    const handleUpload = (e) => {
        const files = Array.from(e.target.files || []);
        if (!files.length || allImages.length >= 5) return;
        const remaining = 5 - allImages.length;
        const toUpload = files.slice(0, remaining);
        setUploading(true);
        let uploaded = 0;
        toUpload.forEach((file) => {
            const fd = new FormData();
            fd.append('file', file);
            fetch(adminUrl('/admin/storefront/media/hero/upload'), { method: 'POST', body: fd, credentials: 'include', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept': 'application/json' } })
            .then((r) => { if (!r.ok) throw new Error('Upload failed: ' + r.status); return r.json(); })
            .then((data) => {
                if (data && data.id) {
                    const img = { id: data.id, url: data.url, alt_text: data.alt_text };
                    setLocalImages((prev) => prev.some((i) => i.id === img.id) ? prev : [...prev, img]);
                    const allIds = [...new Set([...savedIds, ...localImages.map((i) => i.id), data.id].map(Number))];
                    updateConfig(section.id, { media_ids: allIds });
                }
            })
            .catch(() => {})
            .finally(() => { uploaded++; if (uploaded >= toUpload.length) { setUploading(false); if (inputRef.current) inputRef.current.value = ''; } });
        });
    };

    const remove = (id) => {
        setLocalImages((prev) => prev.filter((i) => i.id !== id));
        updateConfig(section.id, { media_ids: savedIds.filter((i) => i !== id) });
    };

    const count = allImages.length;
    return (
        <div>
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero images (max 5)</p>
            <div className="flex flex-wrap gap-2 mb-2">{allImages.map((img) => (<div key={img.id} className="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-950"><img src={img.url} alt={img.alt_text || ''} className="w-full h-full object-cover" /><button type="button" onClick={() => remove(img.id)} className="absolute top-0.5 right-0.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600"><X className="w-3 h-3" /></button></div>))}</div>
            {count < 5 && (<label className="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors"><Upload className="w-4 h-4" />{uploading ? 'Uploading...' : 'Upload Image'}<input ref={inputRef} type="file" accept="image/jpeg,image/png,image/jpg,image/webp" multiple onChange={handleUpload} className="hidden" disabled={uploading} /></label>)}
            <p className="text-xs text-gray-400 mt-1">{count} / 5 images</p>
        </div>
    );
}

function SectionConfig({ section, categories, brands, products, media, updateConfig }) {
    const config = section.configuration || {};
    if (section.type === 'hero') return <div className="mt-5 pt-4 border-t space-y-5"><div className="grid grid-cols-1 sm:grid-cols-2 gap-4"><Field label="Heading" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Button text" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /></div><div className="grid grid-cols-1 sm:grid-cols-2 gap-4"><Field label="Subtitle" value={config.subtitle || ''} onChange={(value) => updateConfig(section.id, { subtitle: value })} /><Field label="Button link" value={config.button_link || ''} placeholder="/products" onChange={(value) => updateConfig(section.id, { button_link: value })} /></div><div className="border-t border-gray-100 dark:border-gray-800 pt-5"><HeroImages config={config} updateConfig={updateConfig} section={section} media={media} /></div></div>;
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