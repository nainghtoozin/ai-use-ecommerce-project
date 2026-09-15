import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { Upload, X, ChevronUp, ChevronDown, Eye, Search } from 'lucide-react';
import { DraftBadge, EditorCard, FIELD_CHECKBOX, FIELD_INPUT, FIELD_SELECT, IconButton, LiveBadge, Notice, OutlineButton, PageHeader, PublishConfirmModal, SAVE_STATUS, SaveStatusText, SuccessButton, Switch } from '@/Components/Admin/StorefrontUI';

const labels = { hero: 'Hero', promotion: 'Promotions', featured_categories: 'Featured Categories', featured_brands: 'Featured Brands', featured_products: 'Featured Products', product_showcase: 'Product Showcase', store_highlights: 'Store Highlights', brand_story: 'Brand Story', cta: 'Call to Action' };

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
    const [activeSectionId, setActiveSectionId] = useState(initialSections[0]?.id ?? null);
    const hasUnpublished = revision?.has_unpublished_changes;
    const publishedRevision = revision?.published?.revision_number;
    const previewUrl = tenant?.slug ? `/store/${tenant.slug}/preview` : null;

    const formRef = useRef(null);
    const navRef = useRef(null);
    const navButtonRefs = useRef({});

    const scrollToSection = useCallback((id) => {
        setActiveSectionId(id);
        const el = formRef.current?.querySelector(`[data-section-id="${id}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, []);

    useEffect(() => {
        const cards = formRef.current ? Array.from(formRef.current.querySelectorAll('[data-section-id]')) : [];
        if (!cards.length) return;
        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((entry) => entry.isIntersecting);
                if (!visible.length) return;
                const topmost = visible.sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
                const id = topmost.target.getAttribute('data-section-id');
                if (id) setActiveSectionId((current) => current === id ? current : id);
            },
            { rootMargin: '-30% 0px -60% 0px' }
        );
        cards.forEach((card) => observer.observe(card));
        return () => observer.disconnect();
    }, [sections.length]);

    useEffect(() => {
        const container = navRef.current;
        const button = navButtonRefs.current[activeSectionId];
        if (!container || !button) return;
        const outLeft = button.offsetLeft < container.scrollLeft;
        const outRight = button.offsetLeft + button.offsetWidth > container.scrollLeft + container.clientWidth;
        if (outLeft || outRight) {
            container.scrollTo({ left: button.offsetLeft - container.clientWidth / 2 + button.offsetWidth / 2, behavior: 'smooth' });
        }
    }, [activeSectionId]);

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
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <PageHeader
                    eyebrow="Storefront"
                    title="Homepage Sections"
                    subtitle="Configure discovery and marketing sections. Empty sections stay hidden from customers."
                    compact
                    tight
                    actions={<Link href={adminUrl('/admin/storefront/revisions')} className="text-sm text-blue-600">History</Link>}
                />

                {saveSuccess && <Notice tone="success" dense className="mb-3">{saveSuccess}</Notice>}
                {saveError && <Notice tone="error" dense className="mb-3">{saveError}</Notice>}

                {hasUnpublished && <Notice tone="warning" dense className="mb-3"><strong>Unpublished changes</strong> — draft not yet visible to customers.</Notice>}

                {/* Compact status + action bar */}
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm px-4 py-2.5 mb-4">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <SaveStatusText status={saveStatus} onRetry={handleRetry} />
                        {hasUnpublished && <DraftBadge>Unpublished draft</DraftBadge>}
                        {publishedRevision && <LiveBadge>Live revision #{publishedRevision}</LiveBadge>}
                    </div>
                    <div className="flex items-center gap-2">
                        {previewUrl && hasUnpublished && (
                            <OutlineButton size="sm" onClick={handlePreview}><Eye className="w-3.5 h-3.5" />Preview Draft</OutlineButton>
                        )}
                        {hasUnpublished && (
                            <SuccessButton size="sm" onClick={handlePublishClick} disabled={publishing}>{publishing ? 'Publishing…' : 'Publish'}</SuccessButton>
                        )}
                    </div>
                </div>

                {/* Section jump navigation */}
                {sections.length > 1 && (
                    <nav aria-label="Homepage sections" className="sticky top-14 lg:top-[72px] z-30 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 py-2 mb-4 bg-[#F1F5F9]/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-800">
                        <div ref={navRef} className="flex gap-1.5 overflow-x-auto">
                            {sections.map((section, index) => {
                                const isActive = String(activeSectionId) === String(section.id);
                                return (
                                    <button
                                        key={section.id}
                                        type="button"
                                        ref={(el) => { if (el) navButtonRefs.current[section.id] = el; }}
                                        onClick={() => scrollToSection(section.id)}
                                        title={section.enabled ? (labels[section.type] || section.type) : `${labels[section.type] || section.type} (disabled)`}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors shrink-0 ${isActive ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'} ${section.enabled ? '' : 'opacity-50'}`}
                                    >
                                        <span className={isActive ? 'text-white/70' : 'text-gray-400 dark:text-gray-500'}>{index + 1}</span>
                                        {labels[section.type] || section.type}
                                    </button>
                                );
                            })}
                        </div>
                    </nav>
                )}

                <form ref={formRef} onSubmit={(e) => e.preventDefault()} className="space-y-4">
                    {sections.map((section, index) => (
                        <EditorCard
                            key={section.id}
                            dense
                            id={`homepage-section-${section.id}`}
                            data-section-id={section.id}
                            className="scroll-mt-32 lg:scroll-mt-36"
                        >
                            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1"><h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{labels[section.type] || section.type}</h2><p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{description(section.type)}</p></div>
                                <Switch label="Enabled" checked={section.enabled} onChange={(value) => update(section.id, { enabled: value })} />
                                <div className="flex gap-1.5"><IconButton label="Move up" onClick={() => move(index, -1)} disabled={index === 0}><ChevronUp className="w-4 h-4" /></IconButton><IconButton label="Move down" onClick={() => move(index, 1)} disabled={index === sections.length - 1}><ChevronDown className="w-4 h-4" /></IconButton></div>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3">
                                <Switch label="Desktop" checked={section.desktop_visible} onChange={(value) => update(section.id, { desktop_visible: value })} />
                                <Switch label="Mobile" checked={section.mobile_visible} onChange={(value) => update(section.id, { mobile_visible: value })} />
                                <VariantField value={section.variant || 'modern-split'} options={variantOptions(section.type, heroVariants)} onChange={(value) => update(section.id, { variant: value })} />
                            </div>
                            <SectionConfig section={section} categories={categories} brands={brands} products={products} media={media} updateConfig={updateConfig} />
                        </EditorCard>
                    ))}
                </form>
            </div>

            {showPublishConfirm && <PublishConfirmModal title="Publish homepage changes?" description="Your draft changes will become visible to customers on the storefront. The previous published revision remains recoverable." processing={publishing} onCancel={() => setShowPublishConfirm(false)} onConfirm={confirmPublish} />}
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
    if (section.type === 'hero') return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-5"><div className="grid grid-cols-1 sm:grid-cols-2 gap-5"><Field label="Heading" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Button text" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /></div><div className="grid grid-cols-1 sm:grid-cols-2 gap-5"><Field label="Subtitle" value={config.subtitle || ''} onChange={(value) => updateConfig(section.id, { subtitle: value })} /><Field label="Button link" value={config.button_link || ''} placeholder="/products" onChange={(value) => updateConfig(section.id, { button_link: value })} /></div><div className="border-t border-gray-100 dark:border-gray-800 pt-5"><HeroImages config={config} updateConfig={updateConfig} section={section} media={media} /></div></div>;
    if (section.type === 'promotion') return <p className="mt-5 pt-4 border-t text-sm text-gray-500">Create and schedule promotions from <Link href={adminUrl('/admin/storefront/promotions')} className="text-blue-600">Promotions & Campaigns</Link>.</p>;
    if (section.type === 'featured_categories') return <div className="mt-5 pt-4 border-t"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2 mb-3">Only categories marked as <strong>Featured</strong> in the Category list will appear on the storefront. Select categories below to prioritize ordering when many are featured.</p><SelectionList title="Categories" items={categories} selected={config.category_ids || []} onChange={(ids) => updateConfig(section.id, { category_ids: ids, limit: config.limit || 6 })} searchable searchPlaceholder="Search categories…" /></div>;
    if (section.type === 'featured_brands') return <div className="mt-5 pt-4 border-t"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2 mb-3">Only brands marked as <strong>Featured</strong> in the Brand list will appear on the storefront. Select brands below to prioritize ordering when many are featured.</p><SelectionList title="Brands" items={brands} selected={config.brand_ids || []} onChange={(ids) => updateConfig(section.id, { brand_ids: ids, limit: config.limit || 6 })} searchable searchPlaceholder="Search brands…" /></div>;
    if (section.type === 'featured_products' || section.type === 'product_showcase') return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-4"><p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2">Only products marked as <strong>Featured</strong> in the Product edit form will appear on the storefront. Select products below to prioritize ordering when many are featured.</p><div className="grid grid-cols-1 sm:grid-cols-2 gap-5"><Field label="Section title" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Description" value={config.description || ''} onChange={(value) => updateConfig(section.id, { description: value })} /></div><SelectionList title="Products" items={products} selected={config.product_ids || []} onChange={(ids) => updateConfig(section.id, { product_ids: ids, limit: config.limit || 8 })} searchable searchPlaceholder="Search products…" /></div>;
    if (section.type === 'store_highlights') return <Highlights items={config.items || []} onChange={(items) => updateConfig(section.id, { items })} />;
    if (section.type === 'brand_story') return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-2 gap-5"><p className="sm:col-span-2 text-sm text-gray-500 dark:text-gray-400">Brand Story content comes from Website Settings → About Us. This section controls presentation, visibility, optional media, and the CTA.</p><Field label="Button label" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /><Field label="Button link" value={config.button_link || ''} onChange={(value) => updateConfig(section.id, { button_link: value })} /><MediaSelect config={config} media={media} onChange={(media_id) => updateConfig(section.id, { media_id })} /></div>;
    if (section.type === 'cta') return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-2 gap-5"><Field label="Title" value={config.title || ''} onChange={(value) => updateConfig(section.id, { title: value })} /><Field label="Button label" value={config.button_text || ''} onChange={(value) => updateConfig(section.id, { button_text: value })} /><Field label="Description" value={config.description || ''} onChange={(value) => updateConfig(section.id, { description: value })} /><Field label="Button link" value={config.button_link || ''} onChange={(value) => updateConfig(section.id, { button_link: value })} /><MediaSelect config={config} media={media} onChange={(media_id) => updateConfig(section.id, { media_id })} /></div>;
    return null;
}

function SelectionList({ title, items, selected, onChange, searchable = false, searchPlaceholder = 'Search…' }) {
    const values = selected.map(Number);
    const [query, setQuery] = useState('');
    const q = query.trim().toLowerCase();
    const filtered = q ? items.filter((item) => (item.name || '').toLowerCase().includes(q)) : items;
    const filteredIds = filtered.map((item) => Number(item.id));
    const toggle = (id) => onChange(values.includes(Number(id)) ? values.filter((value) => value !== Number(id)) : [...values, Number(id)]);
    const allFilteredSelected = filteredIds.length > 0 && filteredIds.every((id) => values.includes(id));
    const selectAll = () => onChange([...new Set([...values, ...filteredIds])]);
    const clearAll = () => onChange([]);
    return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800">
        <div className="flex flex-wrap items-center justify-between gap-2 mb-2">
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{title}<span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-semibold">{values.length} selected</span></p>
            {searchable && <div className="flex items-center gap-3">
                <button type="button" onClick={selectAll} disabled={allFilteredSelected} className="text-xs font-medium text-blue-600 hover:text-blue-800 disabled:opacity-40 disabled:cursor-not-allowed">Select All</button>
                <button type="button" onClick={clearAll} disabled={values.length === 0} className="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-40 disabled:cursor-not-allowed">Clear All</button>
            </div>}
        </div>
        {searchable && <div className="relative mb-2">
            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={searchPlaceholder} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition-colors focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
        </div>}
        {searchable && q && <p className="text-[11px] text-gray-400 dark:text-gray-500 mb-2">{filtered.length} of {items.length} shown — selections outside this filter are kept.</p>}
        {filtered.length === 0 ? (
            <p className="text-sm text-gray-500 dark:text-gray-400 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 px-3 py-6 text-center">{q ? 'No matches for your search.' : `No ${title.toLowerCase()} available.`}</p>
        ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-56 overflow-y-auto pr-0.5">{filtered.map((item) => {
                const checked = values.includes(Number(item.id));
                return <label key={item.id} title={item.name} className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer transition-colors ${checked ? 'border-blue-300 dark:border-blue-700 bg-blue-50/60 dark:bg-blue-900/20 text-gray-900 dark:text-gray-100' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'}`}><input type="checkbox" checked={checked} onChange={() => toggle(item.id)} className={FIELD_CHECKBOX} /><span className="truncate">{item.name}</span></label>;
            })}</div>
        )}
    </div>;
}
function Highlights({ items, onChange }) { const update = (index, key, value) => onChange(items.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item)); const add = () => onChange([...items, { icon: 'star', title: '', description: '' }]); return <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-3">{items.map((item, index) => <div key={index} className="grid grid-cols-1 sm:grid-cols-3 gap-2"><select value={item.icon || 'star'} onChange={(event) => update(index, 'icon', event.target.value)} className={FIELD_SELECT}><option value="star">Star</option><option value="truck">Delivery</option><option value="shield">Secure</option><option value="headset">Support</option><option value="heart">Care</option></select><input value={item.title || ''} onChange={(event) => update(index, 'title', event.target.value)} placeholder="Title" className={FIELD_INPUT} /><input value={item.description || ''} onChange={(event) => update(index, 'description', event.target.value)} placeholder="Description" className={FIELD_INPUT} /></div>)}<button type="button" onClick={add} disabled={items.length >= 6} className="text-sm text-blue-600 disabled:opacity-40">+ Add highlight</button></div>; }
function Field({ label, value, onChange }) { return <label className="text-sm font-medium text-gray-700 dark:text-gray-300">{label}<input value={value} onChange={(event) => onChange(event.target.value)} className={FIELD_INPUT} /></label>; }
function MediaSelect({ config, media, onChange }) { return <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Optional image<select value={config.media_id || ''} onChange={(event) => onChange(event.target.value || null)} className={FIELD_SELECT}><option value="">No image</option>{media.map((item) => <option key={item.id} value={item.id}>{item.alt_text || `Media #${item.id}`}</option>)}</select></label>; }
function VariantField({ value, options, onChange }) { return <label className="text-xs font-medium text-gray-600 dark:text-gray-300">Layout<select value={value} onChange={(event) => onChange(event.target.value)} className={`block ${FIELD_SELECT}`}><option value="default">Theme default</option>{options.map((option) => <option key={option} value={option}>{option}</option>)}</select></label>; }
function variantOptions(type, heroVariants = []) { return { hero: heroVariants, featured_categories: ['default', 'grid', 'horizontal', 'compact'], featured_brands: ['default', 'grid', 'horizontal', 'compact'], featured_products: ['default', 'grid', 'compact', 'image-focused', 'horizontal'], product_showcase: ['default', 'grid', 'compact', 'image-focused', 'horizontal'], brand_story: ['default', 'split', 'text-only'], cta: ['default', 'centered', 'full-width'] }[type] || []; }
function description(type) { return { promotion: 'Display active scheduled campaigns.', featured_categories: 'Help customers discover collections.', featured_brands: 'Showcase selected brands.', featured_products: 'Show selected products near the top of the store.', product_showcase: 'Present a selected product group.', store_highlights: 'Explain why customers should shop with you.', brand_story: 'Tell your store story with an optional image.', cta: 'End the page with a clear next action.' }[type] || 'Configure this homepage section.'; }