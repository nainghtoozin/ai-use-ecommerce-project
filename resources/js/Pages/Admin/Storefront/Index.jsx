import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { DraftBadge, FIELD_INPUT, FIELD_SELECT, FormGroup, Notice, PageHeader, PrimaryButton, PublishConfirmModal, SuccessButton } from '@/Components/Admin/StorefrontUI';

const TABS = [
    ['overview', 'Overview'],
    ['identity', 'Identity'],
    ['appearance', 'Theme & Appearance'],
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
        title: 'Navigation & Storefront',
        description: 'Labels shown in navigation, header, footer, and storefront pages.',
        labels: [
            ['search_products', 'Search products...'],
            ['all_categories', 'All Categories'],
            ['categories', 'Categories'],
            ['no_products_found', 'No products found'],
            ['view_all_products', 'View all products'],
            ['login', 'Login'],
            ['register', 'Register'],
            ['my_account', 'My Account'],
            ['my_orders', 'My Orders'],
            ['cart', 'Cart'],
            ['wishlist', 'Wishlist'],
            ['home', 'Home'],
            ['products', 'Products'],
            ['brands', 'Brands'],
            ['about', 'About'],
            ['faq', 'FAQ'],
            ['new_arrivals', 'New Arrivals'],
        ],
    },
    {
        title: 'Footer',
        description: 'Labels shown in the store footer.',
        labels: [
            ['quick_links', 'Quick Links'],
            ['customer_service', 'Customer Service'],
            ['contact_us', 'Contact Us'],
            ['policies', 'Policies'],
            ['contact', 'Contact'],
            ['contact_details', 'Contact Details'],
            ['read_more', 'Read More'],
            ['about_our_store', 'About Our Store'],
            ['footer_copyright', 'All rights reserved.'],
            ['powered_by', 'Powered by'],
            ['back_to_top', 'Back to top'],
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

export default function StorefrontSettings({ storefront, themes = [], media = [], revision = null, heroVariants = ['default', 'split', 'centered', 'text-only', 'minimal'] }) {
    const { tenant } = usePage().props;
    const [tab, setTab] = useState('overview');
    const [themeId, setThemeId] = useState(storefront?.theme?.id || themes[0]?.id || '');
    const [tokens, setTokens] = useState(storefront?.design || {});
    const [resetTokens, setResetTokens] = useState(false);
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
                <PageHeader
                    eyebrow="Storefront"
                    title="Configure your customer experience"
                    subtitle="Changes use your storefront configuration while keeping legacy settings as a fallback."
                    compact
                    actions={<>
                        {revision?.has_unpublished_changes && <DraftBadge>Draft changes available</DraftBadge>}
                        {tenant?.slug && <a href={`/store/${tenant.slug}/preview`} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors shadow-sm">Preview Draft</a>}
                        {revision?.has_unpublished_changes && <SuccessButton onClick={publish}>Publish</SuccessButton>}
                        <a href={adminUrl('/admin/storefront/revisions')} className="text-sm text-blue-600">History</a>
                    </>}
                />

                <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div className="border-b border-gray-200 dark:border-gray-800 overflow-x-auto">
                        <nav className="flex min-w-max px-3" aria-label="Storefront settings">
                            {TABS.map(([key, label]) => <button key={key} type="button" onClick={() => setTab(key)} className={`px-4 py-2.5 text-sm font-medium border-b-2 ${tab === key ? 'text-blue-600 border-blue-600' : 'text-gray-500 border-transparent hover:text-gray-700'}`}>{label}</button>)}
                        </nav>
                    </div>

                     <form onSubmit={submit} className="p-4 sm:p-6">
                          {saveSuccess && <Notice tone="success" className="mb-5">{saveSuccess}</Notice>}
                          {saveFailure && Object.keys(saveErrors).length === 0 && <Notice tone="error" className="mb-5">{saveFailure}</Notice>}
                          {Object.keys(saveErrors).length > 0 && <Notice tone="error" className="mb-5"><p className="font-semibold">Storefront settings could not be saved.</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(saveErrors).map(([field, message]) => <li key={field}><span className="font-medium">{field}</span>: {Array.isArray(message) ? message.join(', ') : message}</li>)}</ul></Notice>}
                        {tab === 'overview' && (
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <Summary label="Status" value={revision?.published ? `Published revision ${revision.published.revision_number}` : 'Legacy live configuration'} />
                                <Summary label="Current theme" value={`${storefront?.theme?.name || 'Commerce Default'} v${storefront?.theme?.version || '1.0.0'}`} />
                                <Summary label="Homepage" value="Configured in Store → Homepage" />
                                <div className="sm:col-span-3"><Notice tone="info">Your storefront is ready for identity, appearance, and customer label changes.</Notice></div>
                            </div>
                        )}

                        {tab === 'identity' && <IdentityForm storefront={storefront} />}

                        {tab === 'appearance' && (
                            <div className="space-y-6">
                                <div>
                                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Theme preset</h2>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                        {themes.map((theme) => <label key={theme.id} className={`flex items-start gap-3 rounded-xl border-2 p-3 cursor-pointer transition-all ${String(theme.id) === String(themeId) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 shadow-sm' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'}`}><input type="radio" name="theme" value={theme.id} checked={String(theme.id) === String(themeId)} onChange={() => chooseTheme(theme)} className="mt-1 text-blue-600" /><span><span className="block text-sm font-semibold text-gray-900 dark:text-gray-100">{theme.name}</span><span className="block text-xs text-gray-500 dark:text-gray-400 mt-1">{themeDescription(theme.slug)}</span><span className="block text-xs text-gray-400 mt-1">Version {theme.version}</span></span></label>)}
                                    </div>
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Design tokens</h2>
                                     <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">{colorFields.map(([key, label]) => <label key={key} className="text-sm text-gray-600 dark:text-gray-300">{label}<span className="flex gap-2 mt-1"><input type="color" value={tokens.color?.[key] || '#3B82F6'} onChange={(event) => updateToken('color', key, event.target.value)} className="h-9 w-12 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 cursor-pointer" /><input type="text" value={tokens.color?.[key] || ''} onChange={(event) => updateToken('color', key, event.target.value)} className="min-w-0 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" /></span></label>)}</div>
                                </div>
                                 <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"><SelectField label="Button style" value={tokens.buttons?.primary_style || 'solid'} options={['solid', 'outline', 'soft', 'ghost']} onChange={(value) => updateToken('buttons', 'primary_style', value)} /><SelectField label="Card style" value={tokens.cards?.style || 'bordered'} options={['bordered', 'raised', 'flat', 'soft']} onChange={(value) => updateToken('cards', 'style', value)} /><SelectField label="Card radius" value={tokens.radius?.card || '0.75rem'} options={['0.25rem', '0.5rem', '0.75rem', '1rem', '9999px']} onChange={(value) => updateToken('radius', 'card', value)} /><SelectField label="Product cards" value={tokens.product_cards?.variant || 'standard'} options={['standard', 'compact', 'image-focused']} onChange={(value) => updateToken('product_cards', 'variant', value)} /></div><div className="grid grid-cols-1 sm:grid-cols-3 gap-4"><SelectField label="Button radius" value={tokens.radius?.button || '0.5rem'} options={['0.25rem', '0.5rem', '0.75rem', '9999px']} onChange={(value) => updateToken('radius', 'button', value)} /><SelectField label="Heading weight" value={tokens.typography?.heading_weight || '700'} options={['600', '700', '800']} onChange={(value) => updateToken('typography', 'heading_weight', value)} /><SelectField label="Line height" value={tokens.typography?.line_height || '1.5'} options={['1.4', '1.5', '1.55', '1.6']} onChange={(value) => updateToken('typography', 'line_height', value)} /></div><button type="button" onClick={() => { const theme = themes.find((item) => String(item.id) === String(themeId)); if (theme?.default_tokens) { setTokens(theme.default_tokens); setResetTokens(true); } }} className="text-sm text-blue-600 hover:text-blue-700">Reset customizations to preset defaults</button>
                            </div>
                        )}

                        {tab === 'labels' && <div className="space-y-6">{LABEL_GROUPS.map((group) => <FormGroup key={group.title} title={group.title} description={group.description}><div className="grid grid-cols-1 md:grid-cols-2 gap-3">{group.labels.map(([key, fallback]) => <label key={key} className="text-sm font-medium text-gray-700 dark:text-gray-300">{fallback}<input value={labels[key] ?? ''} placeholder={fallback} onChange={(event) => setLabels((current) => ({ ...current, [key]: event.target.value }))} className={FIELD_INPUT} /></label>)}</div></FormGroup>)}</div>}

                        {tab !== 'overview' && <div className="flex justify-end mt-8 pt-5 border-t border-gray-200 dark:border-gray-800"><PrimaryButton disabled={saving}>{saving ? 'Saving...' : 'Save Storefront'}</PrimaryButton></div>}
                    </form>
                </div>
            </div>
            {showPublishConfirm && <PublishConfirmModal title="Publish storefront changes?" description="Your draft will become live for customers. The previous published revision remains recoverable." onCancel={() => setShowPublishConfirm(false)} onConfirm={confirmPublish} />}
        </AdminLayout>
    );
}

function Summary({ label, value }) { return <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-4"><p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p><p className="mt-1.5 text-sm font-semibold text-gray-900 dark:text-gray-100">{value}</p></div>; }

function IdentityForm({ storefront }) {
    return <div className="max-w-2xl space-y-4"><div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3"><p className="text-xs uppercase tracking-wide text-gray-500">Business/store name</p><p className="mt-1 font-semibold text-gray-900 dark:text-gray-100">{storefront?.identity?.store_name || storefront?.identity?.name || 'Current store'}</p><p className="mt-1 text-xs text-gray-500">Managed from the Store / Tenant identity. Storefront uses this as its customer-facing name.</p></div><p className="text-sm text-gray-500">Tagline and description are managed in Website Settings. Homepage-specific hero copy is managed under Homepage Sections.</p><p className="text-sm text-gray-500">Logo and favicon are managed in <strong>Website Settings → Branding</strong>.</p></div>;
}

function SelectField({ label, value, options, onChange }) { return <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">{label}<select value={value} onChange={(event) => onChange(event.target.value)} className={FIELD_SELECT}>{options.map((option) => <option key={option} value={option}>{option}</option>)}</select></label>; }
function themeDescription(slug) { return { 'commerce-default': 'Balanced commerce layout with clear actions and elevated product discovery.', 'minimal-store': 'Quiet surfaces, restrained borders, and compact product presentation.', 'elegant-fashion': 'Soft rose accents, generous spacing, rounded controls, and image-led cards.' }[slug] || 'A curated storefront visual preset.'; }