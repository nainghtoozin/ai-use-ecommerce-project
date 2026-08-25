import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

const TABS = [
    ['overview', 'Overview'],
    ['identity', 'Identity'],
    ['appearance', 'Theme & Appearance'],
    ['homepage', 'Homepage'],
    ['labels', 'Content & Labels'],
];

const LABEL_GROUPS = [
    {
        title: 'Product Actions',
        description: 'Labels shown on product cards and detail pages.',
        labels: [
            ['add_to_cart', 'Add to Cart'],
            ['buy_now', 'Buy Now'],
            ['buy', 'Buy'],
            ['shop_now', 'Shop Now'],
            ['view_product', 'View Product'],
            ['out_of_stock', 'Out of Stock'],
            ['select_options', 'Select options'],
            ['product_description', 'Product Description'],
            ['bundle_details', 'Bundle Details'],
        ],
    },
    {
        title: 'Cart & Checkout',
        description: 'Labels shown during the shopping and checkout flow.',
        labels: [
            ['view_cart', 'View Cart'],
            ['checkout', 'Checkout'],
            ['place_order', 'Place Order'],
            ['continue_shopping', 'Continue Shopping'],
            ['clear_cart', 'Clear Cart'],
        ],
    },
    {
        title: 'Storefront',
        description: 'Labels shown on storefront pages.',
        labels: [
            ['search_products', 'Search products...'],
            ['all_categories', 'All Categories'],
            ['categories', 'Categories'],
            ['no_products_found', 'No products found'],
            ['view_all_products', 'View all products'],
        ],
    },
];

const colorFields = [
    ['primary', 'Primary color'],
    ['surface', 'Surface color'],
    ['background', 'Background color'],
    ['text', 'Text color'],
    ['muted', 'Muted text color'],
    ['border', 'Border color'],
];

function Toggle({ checked, onChange, label }) {
    return (
        <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            {label}
        </label>
    );
}

export default function StorefrontSettings({ storefront, themes = [], media = [], revision = null }) {
    const { tenant } = usePage().props;
    const [tab, setTab] = useState('overview');
    const [identity] = useState({});
    const [themeId, setThemeId] = useState(storefront?.theme?.id || themes[0]?.id || '');
    const [tokens, setTokens] = useState(storefront?.design || {});
    const [resetTokens, setResetTokens] = useState(false);
    const [sections, setSections] = useState(storefront?.homepage?.sections || []);
    const hero = sections.find((section) => section.type === 'hero');
    const [heroConfig, setHeroConfig] = useState(hero?.configuration || {});
    const [heroVariant, setHeroVariant] = useState(hero?.variant || 'auto');
    const [heroFile, setHeroFile] = useState(null);
    const [heroAltText, setHeroAltText] = useState('');
    const [removeHeroImage, setRemoveHeroImage] = useState(false);
    const [labels, setLabels] = useState(storefront?.content?.labels || {});
    const [saving, setSaving] = useState(false);
    const [saveErrors, setSaveErrors] = useState({});
    const [saveSuccess, setSaveSuccess] = useState(null);
    const [saveFailure, setSaveFailure] = useState(null);
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);
    const publish = () => setShowPublishConfirm(true);
    const confirmPublish = () => { setShowPublishConfirm(false); router.post(adminUrl('/admin/storefront/publish'), {}, { preserveScroll: true }); };

    const updateToken = (group, key, value) => {
        setResetTokens(false);
        setTokens((current) => ({ ...current, [group]: { ...current[group], [key]: value } }));
    };

    const chooseTheme = (theme) => {
        setThemeId(theme.id);
        if (theme.default_tokens) setTokens(theme.default_tokens);
        setResetTokens(true);
    };

    const moveSection = (index, direction) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= sections.length) return;
        setSections((current) => {
            const next = [...current];
            [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
            return next;
        });
    };

    const updateSection = (id, field, value) => {
        setSections((current) => current.map((section) => section.id === id ? { ...section, [field]: value } : section));
    };

    const selectedMediaId = heroConfig.media_ids?.[0] || '';
    const selectedMedia = selectedMediaId ? media.find((item) => String(item.id) === String(selectedMediaId)) : null;

    const submit = (event) => {
        event.preventDefault();
        setSaving(true);
        setSaveErrors({});
        setSaveSuccess(null);
        setSaveFailure(null);
        const data = new FormData();
        data.append('_method', 'PUT');
        data.append('theme_id', themeId);
        data.append('reset_tokens', resetTokens ? '1' : '0');

        Object.entries(tokens).forEach(([group, values]) => Object.entries(values || {}).forEach(([key, value]) => {
            if (typeof value !== 'object') data.append(`tokens[${group}][${key}]`, value ?? '');
        }));

        sections.forEach((section, index) => {
            data.append(`homepage_sections[${index}][id]`, section.id);
            data.append(`homepage_sections[${index}][enabled]`, section.enabled ? '1' : '0');
            data.append(`homepage_sections[${index}][desktop_visible]`, section.desktop_visible ? '1' : '0');
            data.append(`homepage_sections[${index}][mobile_visible]`, section.mobile_visible ? '1' : '0');
            data.append(`homepage_sections[${index}][position]`, index);
            data.append(`homepage_sections[${index}][variant]`, section.variant || 'default');
        });

        data.append('hero[variant]', heroVariant);
        data.append('hero[title]', heroConfig.title || '');
        data.append('hero[subtitle]', heroConfig.subtitle || '');
        data.append('hero[button_text]', heroConfig.button_text || '');
        data.append('hero[button_link]', heroConfig.button_link || '');
        if (heroConfig.media_ids?.length) heroConfig.media_ids.forEach((id) => data.append('hero[media_ids][]', id));
        data.append('hero_remove_image', removeHeroImage ? '1' : '0');
        data.append('hero_alt_text', heroAltText);
        if (heroFile) {
            if (Array.isArray(heroFile)) {
                heroFile.forEach((file) => data.append('hero_image[]', file));
            } else {
                data.append('hero_image', heroFile);
            }
        }

        Object.entries(labels).forEach(([key, value]) => data.append(`labels[${key}]`, value || ''));

        router.post(adminUrl('/admin/storefront'), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setSaveErrors({});
                setSaveFailure(null);
                setSaveSuccess('Storefront settings saved to draft.');
                window.setTimeout(() => setSaveSuccess(null), 5000);
            },
            onError: (requestErrors) => {
                setSaveErrors(requestErrors || {});
                setSaveSuccess(null);
                setSaveFailure('Storefront settings could not be saved. Please review the errors below.');
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AdminLayout>
            <Head title="Storefront" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div>
                        <p className="text-sm font-medium text-blue-600">Storefront</p>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Configure your customer experience</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Changes use your storefront configuration while keeping legacy settings as a fallback.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">{revision?.has_unpublished_changes && <span className="px-3 py-2 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">Draft changes available</span>}{tenant?.slug && <a href={`/store/${tenant.slug}/preview`} target="_blank" rel="noreferrer" className="inline-flex justify-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Preview Draft</a>}{revision?.has_unpublished_changes && <button type="button" onClick={publish} className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Publish</button>}<a href={adminUrl('/admin/storefront/revisions')} className="text-sm text-blue-600">History</a></div>
                </div>

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div className="border-b border-gray-200 dark:border-gray-800 overflow-x-auto">
                        <nav className="flex min-w-max px-3" aria-label="Storefront settings">
                            {TABS.map(([key, label]) => <button key={key} type="button" onClick={() => setTab(key)} className={`px-4 py-3 text-sm font-medium border-b-2 ${tab === key ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-700'}`}>{label}</button>)}
                        </nav>
                    </div>

                     <form onSubmit={submit} className="p-4 sm:p-6">
                         {saveSuccess && <div role="status" className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{saveSuccess}</div>}
                         {saveFailure && Object.keys(saveErrors).length === 0 && <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{saveFailure}</div>}
                         {Object.keys(saveErrors).length > 0 && <div role="alert" className="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><p className="font-semibold">Storefront settings could not be saved.</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(saveErrors).map(([field, message]) => <li key={field}><span className="font-medium">{field}</span>: {Array.isArray(message) ? message.join(', ') : message}</li>)}</ul></div>}
                        {tab === 'overview' && (
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <Summary label="Status" value={revision?.published ? `Published revision ${revision.published.revision_number}` : 'Legacy live configuration'} />
                                <Summary label="Current theme" value={`${storefront?.theme?.name || 'Commerce Default'} v${storefront?.theme?.version || '1.0.0'}`} />
                                <Summary label="Homepage sections" value={`${sections.length} configured`} />
                                <div className="sm:col-span-3 rounded-lg bg-blue-50 border border-blue-100 p-4 text-sm text-blue-800">Your storefront is ready for identity, appearance, homepage, and customer label changes.</div>
                            </div>
                        )}

                        {tab === 'identity' && <IdentityForm storefront={storefront} />}

                        {tab === 'appearance' && (
                            <div className="space-y-6">
                                <div>
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Theme preset</h2>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                        {themes.map((theme) => <label key={theme.id} className={`flex items-start gap-3 rounded-lg border p-4 cursor-pointer ${String(theme.id) === String(themeId) ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200 dark:border-gray-700'}`}><input type="radio" name="theme" value={theme.id} checked={String(theme.id) === String(themeId)} onChange={() => chooseTheme(theme)} className="mt-1 text-blue-600" /><span><span className="block font-medium text-gray-900 dark:text-gray-100">{theme.name}</span><span className="block text-xs text-gray-500 mt-1">{themeDescription(theme.slug)}</span><span className="block text-xs text-gray-400 mt-1">Version {theme.version}</span></span></label>)}
                                    </div>
                                </div>
                                <div>
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">Design tokens</h2>
                                     <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">{colorFields.map(([key, label]) => <label key={key} className="text-sm text-gray-600 dark:text-gray-300">{label}<span className="flex gap-2 mt-1"><input type="color" value={tokens.color?.[key] || '#3B82F6'} onChange={(event) => updateToken('color', key, event.target.value)} className="h-10 w-12 rounded border border-gray-300" /><input type="text" value={tokens.color?.[key] || ''} onChange={(event) => updateToken('color', key, event.target.value)} className="min-w-0 flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" /></span></label>)}</div>
                                </div>
                                 <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"><SelectField label="Button style" value={tokens.buttons?.primary_style || 'solid'} options={['solid', 'outline', 'soft', 'ghost']} onChange={(value) => updateToken('buttons', 'primary_style', value)} /><SelectField label="Card style" value={tokens.cards?.style || 'bordered'} options={['bordered', 'raised', 'flat', 'soft']} onChange={(value) => updateToken('cards', 'style', value)} /><SelectField label="Card radius" value={tokens.radius?.card || '0.75rem'} options={['0.25rem', '0.5rem', '0.75rem', '1rem', '9999px']} onChange={(value) => updateToken('radius', 'card', value)} /><SelectField label="Product cards" value={tokens.product_cards?.variant || 'standard'} options={['standard', 'compact', 'image-focused']} onChange={(value) => updateToken('product_cards', 'variant', value)} /></div><div className="grid grid-cols-1 sm:grid-cols-3 gap-4"><SelectField label="Button radius" value={tokens.radius?.button || '0.5rem'} options={['0.25rem', '0.5rem', '0.75rem', '9999px']} onChange={(value) => updateToken('radius', 'button', value)} /><SelectField label="Heading weight" value={tokens.typography?.heading_weight || '700'} options={['600', '700', '800']} onChange={(value) => updateToken('typography', 'heading_weight', value)} /><SelectField label="Line height" value={tokens.typography?.line_height || '1.5'} options={['1.4', '1.5', '1.55', '1.6']} onChange={(value) => updateToken('typography', 'line_height', value)} /></div><button type="button" onClick={() => { const theme = themes.find((item) => String(item.id) === String(themeId)); if (theme?.default_tokens) { setTokens(theme.default_tokens); setResetTokens(true); } }} className="text-sm text-blue-600 hover:text-blue-700">Reset customizations to preset defaults</button>
                            </div>
                        )}

                        {tab === 'homepage' && <HomepageForm sections={sections} updateSection={updateSection} moveSection={moveSection} hero={hero} heroConfig={heroConfig} setHeroConfig={setHeroConfig} heroVariant={heroVariant} setHeroVariant={setHeroVariant} media={media} selectedMedia={selectedMedia} heroFile={heroFile} setHeroFile={setHeroFile} heroAltText={heroAltText} setHeroAltText={setHeroAltText} removeHeroImage={removeHeroImage} setRemoveHeroImage={setRemoveHeroImage} />}

                        {tab === 'labels' && <div className="space-y-6">{LABEL_GROUPS.map((group) => <div key={group.title}><h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{group.title}</h3><p className="text-xs text-gray-500 mt-0.5 mb-3">{group.description}</p><div className="grid grid-cols-1 md:grid-cols-2 gap-3">{group.labels.map(([key, fallback]) => <label key={key} className="text-sm font-medium text-gray-700 dark:text-gray-300">{fallback}<input value={labels[key] ?? ''} placeholder={fallback} onChange={(event) => setLabels((current) => ({ ...current, [key]: event.target.value }))} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>)}</div></div>)}</div>}

                        {tab !== 'overview' && <div className="flex justify-end mt-8 pt-5 border-t border-gray-200 dark:border-gray-800"><button type="submit" disabled={saving} className="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">{saving ? 'Saving...' : 'Save Storefront'}</button></div>}
                    </form>
                </div>
            </div>
            {showPublishConfirm && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl"><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Publish storefront changes?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Your draft will become live for customers. The previous published revision remains recoverable.</p><div className="flex justify-end gap-2 mt-6"><button type="button" onClick={() => setShowPublishConfirm(false)} className="px-4 py-2 rounded-lg border text-sm">Cancel</button><button type="button" onClick={confirmPublish} className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Publish Changes</button></div></div></div>}
        </AdminLayout>
    );
}

function Summary({ label, value }) { return <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-4"><p className="text-xs uppercase tracking-wide text-gray-500">{label}</p><p className="mt-2 font-semibold text-gray-900 dark:text-gray-100">{value}</p></div>; }

function IdentityForm({ storefront }) {
    return <div className="max-w-2xl space-y-4"><div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3"><p className="text-xs uppercase tracking-wide text-gray-500">Business/store name</p><p className="mt-1 font-semibold text-gray-900 dark:text-gray-100">{storefront?.identity?.store_name || storefront?.identity?.name || 'Current store'}</p><p className="mt-1 text-xs text-gray-500">Managed from the Store / Tenant identity. Storefront uses this as its customer-facing name.</p></div><p className="text-sm text-gray-500">Tagline and description are managed in Website Settings. Homepage-specific hero copy is managed under Homepage Sections.</p><p className="text-sm text-gray-500">Logo and favicon are managed in <strong>Website Settings → Branding</strong>.</p></div>;
}

const SECTION_LABELS = { hero: 'Hero', promotion: 'Promotions', featured_categories: 'Featured Categories', featured_products: 'Featured Products', product_showcase: 'Product Showcase', store_highlights: 'Store Highlights', brand_story: 'Brand Story', cta: 'Call to Action' };
const SECTION_DESCRIPTIONS = { hero: 'Introduce your store and guide customers to products.', promotion: 'Display promotional banners to customers.', featured_categories: 'Highlight specific product categories.', featured_products: 'Showcase selected products on the homepage.', product_showcase: 'Display products with a visual focus.', store_highlights: 'Highlight key store features and benefits.', brand_story: 'Share your brand story with customers.', cta: 'Guide customers to take a specific action.' };

function HomepageForm({ sections, updateSection, moveSection, hero, heroConfig, setHeroConfig, heroVariant, setHeroVariant, media, selectedMedia, heroFile, setHeroFile, heroAltText, setHeroAltText, removeHeroImage, setRemoveHeroImage }) {
    const updateHero = (key, value) => setHeroConfig((current) => ({ ...current, [key]: value }));
    return <div className="space-y-4"><div><h2 className="font-semibold text-gray-900 dark:text-gray-100">Homepage sections</h2><p className="text-sm text-gray-500 mt-1">Disabled sections are removed from the customer layout without leaving empty space.</p></div>{sections.map((section, index) => <div key={section.id} className="rounded-xl border border-gray-200 dark:border-gray-700 p-4"><div className="flex flex-col sm:flex-row sm:items-center gap-3"><div className="flex-1"><h3 className="font-semibold text-gray-900 dark:text-gray-100">{SECTION_LABELS[section.type] || section.type}</h3><p className="text-xs text-gray-500 mt-1">{SECTION_DESCRIPTIONS[section.type] || 'Configure this section.'}</p></div><Toggle checked={section.enabled} onChange={(value) => updateSection(section.id, 'enabled', value)} label="Enabled" /><div className="flex gap-1"><button type="button" onClick={() => moveSection(index, -1)} disabled={index === 0} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Up</button><button type="button" onClick={() => moveSection(index, 1)} disabled={index === sections.length - 1} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Down</button></div></div><div className="flex flex-wrap gap-4 mt-4"><Toggle checked={section.desktop_visible} onChange={(value) => updateSection(section.id, 'desktop_visible', value)} label="Desktop" /><Toggle checked={section.mobile_visible} onChange={(value) => updateSection(section.id, 'mobile_visible', value)} label="Mobile" /></div>{section.type === 'hero' && hero?.id === section.id && <div className="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-2 gap-4"><SelectField label="Hero variant" value={heroVariant} options={['auto', 'split', 'text-only', 'minimal']} onChange={setHeroVariant} /><Field label="Title" value={heroConfig.title || ''} onChange={(value) => updateHero('title', value)} /><label className="md:col-span-2 text-sm font-medium text-gray-700 dark:text-gray-300">Subtitle<textarea value={heroConfig.subtitle || ''} onChange={(event) => updateHero('subtitle', event.target.value)} rows={3} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label><Field label="Button text" value={heroConfig.button_text || ''} onChange={(value) => updateHero('button_text', value)} /><Field label="Button link" value={heroConfig.button_link || ''} placeholder="/products" onChange={(value) => updateHero('button_link', value)} /><label className="text-sm font-medium text-gray-700 dark:text-gray-300">Hero image (up to 5)<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple onChange={(event) => { const files = Array.from(event.target.files || []).slice(0, 5); setHeroFile(files.length === 1 ? files[0] : files); }} className="mt-1 block w-full text-sm" /></label><label className="text-sm font-medium text-gray-700 dark:text-gray-300">Alt text<input value={heroAltText} onChange={(event) => setHeroAltText(event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>{heroConfig.media_ids?.length > 0 && <div className="md:col-span-2"><p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current hero images ({heroConfig.media_ids.length}/5)</p><div className="flex flex-wrap gap-2">{heroConfig.media_ids.map((mediaId) => { const item = media.find((m) => String(m.id) === String(mediaId)); return item ? <div key={item.id} className="relative group"><img src={item.url} alt={item.alt_text || 'Hero image'} className="h-20 w-32 rounded-lg object-cover" /><span className="absolute bottom-1 left-1 bg-black/60 text-white text-xs px-1.5 py-0.5 rounded">{item.alt_text || 'Image'}</span></div> : null; })}</div></div>}{selectedMedia && !heroConfig.media_ids?.length && <div className="md:col-span-2 flex items-center gap-3"><img src={selectedMedia.url} alt={selectedMedia.alt_text || ''} className="h-20 w-32 rounded-lg object-cover" /><span className="text-sm text-gray-600 dark:text-gray-300">Current configured hero image</span></div>}<label className="md:col-span-2 inline-flex items-center gap-2 text-sm text-red-600"><input type="checkbox" checked={removeHeroImage} onChange={(event) => setRemoveHeroImage(event.target.checked)} className="rounded border-red-300 text-red-600" />Remove configured hero image and use text fallback</label>{media.length === 0 && <p className="md:col-span-2 text-xs text-gray-500">No new media is configured. Existing WebsiteInfo hero images remain available until you explicitly remove a configured image.</p>}</div>}</div>)}</div>;
}

function Field({ label, value, onChange, placeholder = '' }) { return <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">{label}<input value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>; }
function SelectField({ label, value, options, onChange }) { return <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">{label}<select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal">{options.map((option) => <option key={option} value={option}>{option}</option>)}</select></label>; }
function themeDescription(slug) { return { 'commerce-default': 'Balanced commerce layout with clear actions and elevated product discovery.', 'minimal-store': 'Quiet surfaces, restrained borders, and compact product presentation.', 'elegant-fashion': 'Soft rose accents, generous spacing, rounded controls, and image-led cards.' }[slug] || 'A curated storefront visual preset.'; }
