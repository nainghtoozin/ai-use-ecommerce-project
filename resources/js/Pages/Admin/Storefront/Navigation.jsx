import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function StorefrontNavigation({ navigation }) {
    const [settings, setSettings] = useState({
        show_store_name: navigation?.settings?.show_store_name !== false,
        show_search: navigation?.settings?.show_search !== false,
    });
    const [items, setItems] = useState(navigation?.items || []);
    const [saving, setSaving] = useState(false);

    const updateItem = (id, field, value) => setItems((current) => current.map((item) => item.id === id ? { ...item, [field]: value } : item));
    const move = (index, direction) => {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= items.length) return;
        setItems((current) => { const next = [...current]; [next[index], next[nextIndex]] = [next[nextIndex], next[index]]; return next; });
    };
    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        router.put(adminUrl('/admin/storefront/navigation'), { ...settings, items: items.map((item, position) => ({ ...item, position })) }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <AdminLayout>
            <Head title="Header & Navigation" />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex items-start justify-between gap-4 mb-6"><div><p className="text-sm font-medium text-blue-600">Storefront</p><h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Header & Navigation</h1><p className="text-sm text-gray-500 mt-1">Choose the supported links customers see in your store header.</p></div><Link href={adminUrl('/admin/storefront')} className="text-sm text-blue-600 hover:text-blue-700">Back to Storefront</Link></div>
                <form onSubmit={save} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6">
                    <div className="flex flex-wrap gap-5 pb-5 border-b border-gray-200 dark:border-gray-800"><Toggle label="Show store name" checked={settings.show_store_name} onChange={(value) => setSettings((current) => ({ ...current, show_store_name: value }))} /><Toggle label="Show search access" checked={settings.show_search} onChange={(value) => setSettings((current) => ({ ...current, show_search: value }))} /></div>
                    <div className="space-y-3 mt-5">{items.map((item, index) => <div key={item.id} className="rounded-lg border border-gray-200 dark:border-gray-700 p-3 flex flex-col sm:flex-row sm:items-center gap-3"><div className="flex gap-1"><button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Up</button><button type="button" onClick={() => move(index, 1)} disabled={index === items.length - 1} className="px-2 py-1 rounded border text-xs disabled:opacity-30">Down</button></div><div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2"><input value={item.label} onChange={(event) => updateItem(item.id, 'label', event.target.value)} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" aria-label={`${item.key} label`} /><select value={item.path} onChange={(event) => updateItem(item.id, 'path', event.target.value)} className="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">{pathOptions(item.key).map(([path, label]) => <option key={path} value={path}>{label}</option>)}</select></div><Toggle label="Visible" checked={item.enabled} onChange={(value) => updateItem(item.id, 'enabled', value)} /></div>)}</div>
                    <div className="flex justify-end mt-6 pt-5 border-t border-gray-200 dark:border-gray-800"><button disabled={saving} className="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold disabled:opacity-50">{saving ? 'Saving...' : 'Save Navigation'}</button></div>
                </form>
            </div>
        </AdminLayout>
    );
}

function Toggle({ label, checked, onChange }) { return <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />{label}</label>; }
function pathOptions(key) { const options = { home: [['/', 'Store home']], products: [['/products', 'Products']], contact: [['/contact', 'Contact']], orders: [['/customer/orders', 'Customer orders']] }; return options[key] || []; }
