import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function StorefrontMedia({ media, currentHeroMediaIds = [], search = '' }) {
    const [file, setFile] = useState(null);
    const [altText, setAltText] = useState('');
    const [query, setQuery] = useState(search);
    const [editing, setEditing] = useState({});

    const upload = (event) => {
        event.preventDefault();
        if (!file) return;
        const data = new FormData();
        data.append('file', file);
        data.append('alt_text', altText);
        router.post(adminUrl('/admin/storefront/media'), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setFile(null); setAltText(''); event.target.reset(); },
        });
    };

    const searchMedia = (event) => {
        event.preventDefault();
        router.get(adminUrl('/admin/storefront/media'), { search: query || undefined }, { preserveState: true, replace: true });
    };

    const updateAlt = (item) => router.patch(adminUrl(`/admin/storefront/media/${item.id}`), { alt_text: editing[item.id] ?? item.alt_text ?? '' }, { preserveScroll: true });
    const deleteMedia = (item) => {
        if (window.confirm('Delete this media file? This cannot be undone.')) router.delete(adminUrl(`/admin/storefront/media/${item.id}`), { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Storefront Media" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div><p className="text-sm font-medium text-blue-600">Storefront</p><h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Media Library</h1><p className="text-sm text-gray-500 mt-1">Upload reusable storefront images. Files are isolated to this store.</p></div>
                    <Link href={adminUrl('/admin/storefront')} className="text-sm text-blue-600 hover:text-blue-700">Back to Storefront</Link>
                </div>

                <form onSubmit={upload} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 mb-6 grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Image<input type="file" required accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" onChange={(event) => setFile(event.target.files?.[0] || null)} className="mt-1 block w-full text-sm" /></label>
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Alt text<input value={altText} onChange={(event) => setAltText(event.target.value)} placeholder="Describe the image" className="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-normal" /></label>
                    <button type="submit" className="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Upload</button>
                </form>

                <form onSubmit={searchMedia} className="flex gap-2 mb-4"><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search media" className="flex-1 max-w-md rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /><button type="submit" className="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm">Search</button></form>

                {media.data?.length ? <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">{media.data.map((item) => { const isHero = currentHeroMediaIds.map(String).includes(String(item.id)); return <article key={item.id} className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden"><div className="aspect-[4/3] bg-gray-100 dark:bg-gray-800"><img src={item.url} alt={item.alt_text || item.original_name || ''} className="w-full h-full object-cover" /></div><div className="p-3 space-y-2"><p className="text-xs text-gray-500 truncate" title={item.original_name}>{item.original_name || item.key}</p><input value={editing[item.id] ?? item.alt_text ?? ''} onChange={(event) => setEditing((current) => ({ ...current, [item.id]: event.target.value }))} placeholder="Alt text" className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" /><div className="flex flex-wrap gap-2"><button type="button" onClick={() => updateAlt(item)} className="px-2.5 py-1.5 rounded-md border text-xs">Save alt</button>{isHero ? <button type="button" onClick={() => router.post(adminUrl(`/admin/storefront/media/${item.id}/detach-hero`), {}, { preserveScroll: true })} className="px-2.5 py-1.5 rounded-md bg-amber-50 text-amber-700 text-xs">Remove Hero</button> : <button type="button" onClick={() => router.post(adminUrl(`/admin/storefront/media/${item.id}/assign-hero`), {}, { preserveScroll: true })} className="px-2.5 py-1.5 rounded-md bg-blue-50 text-blue-700 text-xs">Use for Hero</button>}<button type="button" onClick={() => router.post(adminUrl(`/admin/storefront/media/${item.id}/assign-logo`), {}, { preserveScroll: true })} className="px-2.5 py-1.5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs">Use as Logo</button><button type="button" onClick={() => deleteMedia(item)} className="px-2.5 py-1.5 rounded-md bg-red-50 text-red-700 text-xs">Delete</button></div></div></article>; })}</div> : <div className="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center text-sm text-gray-500">No media found. Upload an image to use it across your storefront.</div>}

                {media.links?.length > 3 && <div className="flex flex-wrap gap-2 mt-6">{media.links.map((link) => <Link key={link.url || link.label} href={link.url || '#'} preserveState className={`px-3 py-1.5 rounded border text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
            </div>
        </AdminLayout>
    );
}
