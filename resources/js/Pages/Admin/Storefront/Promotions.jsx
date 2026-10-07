import { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import MediaLibraryPicker from '@/Components/MediaLibraryPicker';
import { Megaphone, Upload } from 'lucide-react';

const emptyForm = { title: '', description: '', storefront_media_id: '', promotion_id: '', cta_label: 'Shop Now', link: '/products', is_active: true, starts_at: '', ends_at: '', desktop_visible: true, mobile_visible: true };

export default function StorefrontPromotions({ promotions, media = [], availablePromotions = [] }) {
    const [form, setForm] = useState(emptyForm);
    const [editingId, setEditingId] = useState(null);
    const [library, setLibrary] = useState(media);
    const update = (key, value) => setForm((current) => ({ ...current, [key]: value }));
    const reorder = (index, direction, list) => { const nextIndex = index + direction; if (nextIndex < 0 || nextIndex >= list.length) return; const ids = list.map((item) => item.id); [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]]; router.post(adminUrl('/admin/storefront/promotions/reorder'), { ids }, { preserveScroll: true }); };
    const submit = (event) => {
        event.preventDefault();
        const url = editingId ? adminUrl(`/admin/storefront/promotions/${editingId}`) : adminUrl('/admin/storefront/promotions');
        const data = { ...form };
        if (editingId) router.post(url, data, { preserveScroll: true, onSuccess: () => { setEditingId(null); setForm(emptyForm); } });
        else router.post(url, data, { preserveScroll: true, onSuccess: () => setForm(emptyForm) });
    };
    const edit = (promotion) => { setEditingId(promotion.id); setForm({ title: promotion.title || '', description: promotion.description || '', storefront_media_id: promotion.storefront_media_id || '', promotion_id: promotion.promotion_id || '', cta_label: promotion.cta_label || 'Shop Now', link: promotion.link || '/products', is_active: !!promotion.is_active, starts_at: toInputDate(promotion.starts_at), ends_at: toInputDate(promotion.ends_at), desktop_visible: promotion.desktop_visible !== false, mobile_visible: promotion.mobile_visible !== false }); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    const remove = (promotion) => { if (window.confirm('Remove this promotion?')) router.delete(adminUrl(`/admin/storefront/promotions/${promotion.id}`), { preserveScroll: true }); };

    return <AdminLayout><Head title="Storefront Promotions" /><div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8"><div className="flex items-start justify-between gap-4 mb-6"><div><p className="text-sm font-medium text-blue-600">Storefront</p><h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Promotions & Campaigns</h1><p className="text-sm text-gray-500 mt-1">Show scheduled offers without changing your discount engine.</p></div><Link href={adminUrl('/admin/storefront')} className="text-sm text-blue-600">Back to Storefront</Link></div>
        <form onSubmit={submit} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 mb-6 space-y-4"><div className="flex items-center justify-between"><h2 className="font-semibold text-gray-900 dark:text-gray-100">{editingId ? 'Edit promotion' : 'Create promotion'}</h2>{editingId && <button type="button" onClick={() => { setEditingId(null); setForm(emptyForm); }} className="text-sm text-gray-500">Cancel</button>}</div><div className="grid grid-cols-1 md:grid-cols-2 gap-4"><Field label="Title" value={form.title} onChange={(value) => update('title', value)} required /><Field label="CTA label" value={form.cta_label} onChange={(value) => update('cta_label', value)} /><Field label="Destination" value={form.link} onChange={(value) => update('link', value)} placeholder="/products" /><BannerMediaField label="Media" media={library} value={form.storefront_media_id} onChange={(value) => update('storefront_media_id', value)} onLibraryAdd={(item) => { setLibrary((prev) => prev.some((m) => String(m.id) === String(item.id)) ? prev : [...prev, item]); update('storefront_media_id', item.id); }} /><label className="text-sm font-medium text-gray-700 dark:text-gray-300">Link to Discount Promotion<select value={form.promotion_id} onChange={(event) => update('promotion_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal"><option value="">No linked promotion</option>{availablePromotions.map((promo) => <option key={promo.id} value={promo.id}>{promo.name} ({promo.type === 'percentage' ? `${promo.value}% off` : promo.type === 'fixed' ? `${promo.value} off` : 'Free shipping'})</option>)}</select></label><label className="md:col-span-2 text-sm font-medium text-gray-700 dark:text-gray-300">Description<textarea value={form.description} onChange={(event) => update('description', event.target.value)} rows={3} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label><Field label="Starts at" type="datetime-local" value={form.starts_at} onChange={(value) => update('starts_at', value)} /><Field label="Ends at" type="datetime-local" value={form.ends_at} onChange={(value) => update('ends_at', value)} /></div><div className="flex flex-wrap gap-5"><Toggle label="Enabled" checked={form.is_active} onChange={(value) => update('is_active', value)} /><Toggle label="Desktop" checked={form.desktop_visible} onChange={(value) => update('desktop_visible', value)} /><Toggle label="Mobile" checked={form.mobile_visible} onChange={(value) => update('mobile_visible', value)} /></div><button className="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold">{editingId ? 'Save promotion' : 'Create promotion'}</button></form>
        <div className="space-y-3">{promotions.data?.map((promotion, index) => <div key={promotion.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 flex flex-col md:flex-row gap-4"><div className="w-full md:w-48 aspect-video rounded-lg overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 shrink-0">{promotion.image_url ? <img src={promotion.image_url} alt={promotion.title} className="w-full h-full object-cover" /> : <div className="h-full flex flex-col items-center justify-center gap-1 text-gray-400 dark:text-gray-500"><Megaphone className="w-5 h-5" /><span className="text-[11px]">No image</span></div>}</div><div className="flex-1 min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-gray-900 dark:text-gray-100">{promotion.title}</h3><PromoStatus promotion={promotion} /></div><p className="text-sm text-gray-500 mt-1 line-clamp-2">{promotion.description || 'No description'}</p>{promotion.promotion && <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1"><span className="inline-block px-2 py-0.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-[11px] font-bold rounded-full">{discountBadge(promotion.promotion)}</span><span className="text-[11px] text-gray-500 dark:text-gray-400">{promotion.promotion.name}{promotion.promotion.code ? <> · Code: <span className="font-mono font-semibold text-gray-700 dark:text-gray-300">{promotion.promotion.code}</span></> : null}</span></p>}<p className="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{formatSchedule(promotion.starts_at, promotion.ends_at)}</p></div><div className="flex md:flex-col gap-2 justify-end"><button type="button" onClick={() => reorder(index, -1, promotions.data)} disabled={index === 0} className="px-3 py-1.5 border rounded text-xs disabled:opacity-30">Up</button><button type="button" onClick={() => reorder(index, 1, promotions.data)} disabled={index === promotions.data.length - 1} className="px-3 py-1.5 border rounded text-xs disabled:opacity-30">Down</button><button type="button" onClick={() => edit(promotion)} className="px-3 py-1.5 border rounded text-xs">Edit</button><button type="button" onClick={() => router.post(adminUrl(`/admin/storefront/promotions/${promotion.id}/toggle`), {}, { preserveScroll: true })} className="px-3 py-1.5 border rounded text-xs">{promotion.is_active ? 'Disable' : 'Enable'}</button><button type="button" onClick={() => remove(promotion)} className="px-3 py-1.5 bg-red-50 text-red-700 rounded text-xs">Delete</button></div></div>)}</div>
    </div></AdminLayout>;
}

function Field({ label, value, onChange, type = 'text', placeholder = '', required = false }) { return <label className="text-sm font-medium text-gray-700 dark:text-gray-300">{label}<input required={required} type={type} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>; }

function BannerMediaField({ label, media = [], value, onChange, onLibraryAdd }) {
    const [libraryOpen, setLibraryOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const inputRef = useRef(null);
    const selected = (media || []).find((item) => String(item.id) === String(value));

    const handleUpload = (event) => {
        const file = event.target.files?.[0];
        if (inputRef.current) inputRef.current.value = '';
        if (!file) return;
        setUploading(true);
        const fd = new FormData();
        fd.append('file', file);
        fetch(adminUrl('/admin/storefront/media/promotion/upload'), { method: 'POST', body: fd, credentials: 'include', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept': 'application/json' } })
            .then((response) => { if (!response.ok) throw new Error('Upload failed'); return response.json(); })
            .then((data) => { if (data && data.id && onLibraryAdd) onLibraryAdd(data); })
            .catch(() => {})
            .finally(() => setUploading(false));
    };

    return (
        <div className="text-sm font-medium text-gray-700 dark:text-gray-300">
            <span>{label}</span>
            <div className="mt-1 flex items-center gap-3 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 p-2">
                <div className="w-16 h-12 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-700 flex-shrink-0 flex items-center justify-center">
                    {selected?.url ? (
                        <img src={selected.url} alt={selected.alt_text || selected.original_name || ''} className="w-full h-full object-cover" />
                    ) : (
                        <Megaphone className="w-4 h-4 text-gray-400" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-normal text-gray-700 dark:text-gray-300 truncate" title={selected?.original_name || ''}>
                        {selected ? (selected.original_name || selected.alt_text || `Media #${selected.id}`) : 'Text-only promotion'}
                    </p>
                    {selected && <p className="text-[11px] font-normal text-green-600">Selected</p>}
                </div>
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
                <button type="button" onClick={() => inputRef.current?.click()} disabled={uploading} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors disabled:opacity-50">
                    <Upload className="w-3.5 h-3.5" />{uploading ? 'Uploading...' : 'Upload New'}
                </button>
                <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/jpg,image/webp" onChange={handleUpload} className="hidden" />
                <button type="button" onClick={() => setLibraryOpen(true)} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-colors">
                    <i className="bi bi-images"></i> Choose from Library
                </button>
                {selected && (
                    <button type="button" onClick={() => onChange('')} className="inline-flex items-center px-3 py-1.5 text-xs text-red-600 hover:text-red-800">
                        Text-only
                    </button>
                )}
            </div>
            <MediaLibraryPicker
                open={libraryOpen}
                onClose={() => setLibraryOpen(false)}
                media={media}
                selectedId={value || null}
                onSelect={(item) => onChange(item.id)}
            />
        </div>
    );
}
function Toggle({ label, checked, onChange }) { return <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-gray-300 text-blue-600" />{label}</label>; }
function toInputDate(value) { return value ? value.slice(0, 16) : ''; }
function formatPromoDate(value) {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function formatPromoTime(value) {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime()) || (d.getHours() === 0 && d.getMinutes() === 0)) return null;
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}
function formatSchedule(startsAt, endsAt) {
    const s = formatPromoDate(startsAt);
    const e = formatPromoDate(endsAt);
    if (s && e) {
        const times = [formatPromoTime(startsAt), formatPromoTime(endsAt)].filter(Boolean).join(' – ');
        return (s === e ? s : `${s} — ${e}`) + (times ? ` · ${times}` : '');
    }
    if (s) return `Starts ${s}` + (formatPromoTime(startsAt) ? ` · ${formatPromoTime(startsAt)}` : '');
    if (e) return `Ends ${e}` + (formatPromoTime(endsAt) ? ` · ${formatPromoTime(endsAt)}` : '');
    return 'Always visible';
}
function promotionStatus(promotion) {
    if (!promotion.is_active) return { label: 'Disabled', className: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' };
    if (promotion.is_currently_visible) return { label: 'Visible now', className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
    if (promotion.starts_at && new Date(promotion.starts_at).getTime() > Date.now()) return { label: 'Scheduled', className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
    return { label: 'Expired', className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' };
}
function PromoStatus({ promotion }) {
    const status = promotionStatus(promotion);
    return <span className={`text-xs px-2 py-0.5 rounded-full ${status.className}`}>{status.label}</span>;
}
function discountBadge(promo) {
    if (promo.type === 'percentage') return `${promo.value}% OFF`;
    if (promo.type === 'fixed') return `${Number(promo.value).toLocaleString()} OFF`;
    return 'Free Shipping';
}
