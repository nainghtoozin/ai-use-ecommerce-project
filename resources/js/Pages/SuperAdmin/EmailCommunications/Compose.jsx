import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const EMAIL_TYPES = {
    maintenance: 'Maintenance Announcement',
    feature_update: 'Feature Update',
    important_notice: 'Important Notice',
    account_warning: 'Account Warning',
    subscription_notice: 'Subscription Notice',
    general: 'General Message',
};

export default function Compose({ tenants, filters, email_types, tenant_statuses, default_statuses, campaign, selectedTenantIds, selectedStatuses, existingRecipients }) {
    const pageErrors = usePage().props.errors || {};
    const [mode, setMode] = useState(campaign?.recipient_mode || 'all');
    const [emailType, setEmailType] = useState(campaign?.email_type || 'general');
    const [subject, setSubject] = useState(campaign?.subject || '');
    const [message, setMessage] = useState(campaign?.message || '');
    const [replyTo, setReplyTo] = useState(campaign?.reply_to || '');
    const [statuses, setStatuses] = useState(selectedStatuses || default_statuses || ['active', 'trialing']);
    const [selectedIds, setSelectedIds] = useState(selectedTenantIds || []);
    const [selectAllMatching, setSelectAllMatching] = useState(false);
    const [singleId, setSingleId] = useState(null);
    const [attachments, setAttachments] = useState([]);
    const [removeAttachmentIds, setRemoveAttachmentIds] = useState([]);
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [preview, setPreview] = useState(null);
    const [showPreview, setShowPreview] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');

    function toggleStatus(s) {
        setStatuses((prev) => prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s]);
    }

    function toggleSelect(id) {
        setSelectedIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    }

    function selectCurrentPage() {
        const ids = (tenants?.data || []).map((t) => t.id);
        setSelectedIds((prev) => Array.from(new Set([...prev, ...ids])));
    }

    function clearSelection() {
        setSelectedIds([]);
    }

    function handleSearch(e) {
        e.preventDefault();
        router.get('/superadmin/email-communications/compose', { search, status: statusFilter }, { preserveState: true, replace: true });
    }

    function buildPayload() {
        const payload = {
            email_type: emailType,
            subject,
            message,
            reply_to: replyTo || null,
            recipient_mode: mode,
        };
        if (campaign) {
            payload.remove_attachments = removeAttachmentIds;
        }
        if (mode === 'all') {
            payload.statuses = statuses;
        } else if (mode === 'selected') {
            if (selectAllMatching) {
                payload.select_all = true;
                payload.search = search;
                payload.status = statusFilter || null;
            } else {
                payload.tenant_ids = selectedIds;
            }
        } else {
            payload.tenant_id = singleId;
        }
        return payload;
    }

    function saveDraft() {
        const payload = buildPayload();
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            if (v === null || v === undefined) return;
            if (Array.isArray(v)) v.forEach((item) => formData.append(`${k}[]`, item));
            else if (typeof v === 'object') formData.append(k, JSON.stringify(v));
            else formData.append(k, v);
        });
        attachments.forEach((f) => formData.append('attachments[]', f));
        const url = campaign ? `/superadmin/email-communications/${campaign.id}` : '/superadmin/email-communications/drafts';
        if (campaign) {
            formData.append('_method', 'PUT');
            router.post(url, formData, { preserveState: true });
        } else {
            router.post(url, formData, { preserveState: true });
        }
    }

    async function handlePreview() {
        setError('');
        const payload = buildPayload();
        try {
            const res = await window.axios.post('/superadmin/email-communications/preview', payload);
            setPreview(res.data);
            setShowPreview(true);
        } catch (e) {
            if (e?.response?.status === 419) {
                setError('Your session expired. Please refresh the page and try again.');
            } else {
                setError(e?.response?.data?.message || 'Preview failed');
            }
        }
    }

    function handleSend() {
        if (sending) return;
        setSending(true);
        const payload = buildPayload();
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            if (v === null || v === undefined) return;
            if (Array.isArray(v)) {
                v.forEach((item) => formData.append(`${k}[]`, item));
            } else if (typeof v === 'object') {
                formData.append(k, JSON.stringify(v));
            } else {
                formData.append(k, v);
            }
        });
        attachments.forEach((f) => formData.append('attachments[]', f));
        const url = campaign ? `/superadmin/email-communications/${campaign.id}/send` : '/superadmin/email-communications';
        router.post(url, formData, {
            preserveState: true,
            onSuccess: () => {
                setShowPreview(false);
                setSending(false);
            },
            onError: (errs) => {
                setError(Object.values(errs).join(' '));
                setSending(false);
            },
        });
    }

    const tenantList = tenants?.data || [];

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Compose Email</h2>}>
            <Head title="Compose Email" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {error && <div className="p-3 bg-red-50 text-red-700 rounded-lg text-sm">{error}</div>}
                    {Object.keys(pageErrors).length > 0 && (
                        <div className="p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                            {Object.values(pageErrors).map((e, i) => <div key={i}>{e}</div>)}
                        </div>
                    )}

                    <div className="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg p-6 space-y-4">
                        {!campaign && (
                        <div>
                            <label className="block text-sm font-medium mb-1">Recipient Mode</label>
                            <div className="flex gap-2">
                                {['all', 'selected', 'single'].map((m) => (
                                    <button key={m} onClick={() => setMode(m)}
                                        className={`px-3 py-1.5 rounded-lg text-sm border ${mode === m ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300'}`}>
                                        {m === 'all' ? 'All Merchants' : m === 'selected' ? 'Selected Merchants' : 'Single Merchant'}
                                    </button>
                                ))}
                            </div>
                        </div>
                        )}

                        {!campaign && mode === 'all' && (
                            <div>
                                <label className="block text-sm font-medium mb-1">Tenant Statuses</label>
                                <div className="flex flex-wrap gap-2">
                                    {tenant_statuses.map((s) => (
                                        <label key={s} className="inline-flex items-center gap-1 text-sm">
                                            <input type="checkbox" checked={statuses.includes(s)} onChange={() => toggleStatus(s)} />
                                            {s}
                                        </label>
                                    ))}
                                </div>
                                <p className="text-xs text-gray-500 mt-1">Default: active/trialing only.</p>
                            </div>
                        )}

                        {!campaign && mode === 'selected' && (
                            <div className="space-y-3">
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search merchants..." className="border rounded-lg px-3 py-1.5 text-sm flex-1" />
                                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="border rounded-lg px-3 py-1.5 text-sm">
                                        <option value="">All statuses</option>
                                        {tenant_statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                    <button type="submit" className="px-3 py-1.5 bg-gray-100 rounded-lg text-sm">Search</button>
                                </form>
                                <div className="flex gap-2 text-sm">
                                    <button onClick={selectCurrentPage} className="px-2 py-1 bg-gray-100 rounded">Select current page</button>
                                    <button onClick={() => { setSelectAllMatching(true); setSelectedIds([]); }} className="px-2 py-1 bg-gray-100 rounded">Select all matching results</button>
                                    <button onClick={() => { setSelectAllMatching(false); clearSelection(); }} className="px-2 py-1 bg-gray-100 rounded">Clear selection</button>
                                    <span className="text-gray-500">Selected: {selectAllMatching ? 'All matching' : selectedIds.length}</span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-2"></th>
                                                <th className="p-2 text-left">Merchant</th>
                                                <th className="p-2 text-left">Slug</th>
                                                <th className="p-2 text-left">Owner Email</th>
                                                <th className="p-2 text-left">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tenantList.map((t) => (
                                                <tr key={t.id} className="border-b">
                                                    <td className="p-2">
                                                        <input type="checkbox" checked={selectedIds.includes(t.id)} onChange={() => toggleSelect(t.id)} />
                                                    </td>
                                                    <td className="p-2">{t.name}</td>
                                                    <td className="p-2">{t.slug}</td>
                                                    <td className="p-2">{t.owner_email || '-'}</td>
                                                    <td className="p-2">{t.status}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {tenants?.links && (
                                    <div className="flex gap-1 flex-wrap text-sm">
                                        {tenants.links.map((link, i) => (
                                            <button key={i} disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                className={`px-2 py-1 rounded ${link.active ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {!campaign && mode === 'single' && (
                            <div className="space-y-2">
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search merchant..." className="border rounded-lg px-3 py-1.5 text-sm flex-1" />
                                    <button type="submit" className="px-3 py-1.5 bg-gray-100 rounded-lg text-sm">Search</button>
                                </form>
                                <div className="max-h-60 overflow-y-auto border rounded-lg">
                                    {tenantList.map((t) => (
                                        <div key={t.id}
                                            onClick={() => setSingleId(t.id)}
                                            className={`p-2 cursor-pointer border-b text-sm ${singleId === t.id ? 'bg-blue-50' : ''}`}>
                                            <span className="font-medium">{t.name}</span> <span className="text-gray-500">({t.slug})</span> — {t.owner_email || 'no email'}
                                        </div>
                                    ))}
                                </div>
                                {singleId && (
                                    <p className="text-sm text-gray-600">
                                        Selected: {tenantList.find((t) => t.id === singleId)?.name} ({tenantList.find((t) => t.id === singleId)?.owner_email})
                                    </p>
                                )}
                            </div>
                        )}

                        {campaign && (
                            <div className="border rounded-lg p-4 bg-gray-50">
                                <p className="text-sm font-medium mb-2">Recipients (Locked)</p>
                                <p className="text-xs text-gray-500 mb-2">{(existingRecipients || []).length} recipient(s) preserved</p>
                                <ul className="text-sm space-y-1 max-h-48 overflow-y-auto">
                                    {(existingRecipients || []).map((r) => (
                                        <li key={r.id}>{r.name} — {r.email}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium mb-1">Email Type</label>
                            <select value={emailType} onChange={(e) => setEmailType(e.target.value)} className="border rounded-lg px-3 py-1.5 text-sm w-full">
                                {Object.entries(email_types || EMAIL_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Subject</label>
                            <input type="text" value={subject} onChange={(e) => setSubject(e.target.value)} className="border rounded-lg px-3 py-1.5 text-sm w-full" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Message</label>
                            <textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={6} className="border rounded-lg px-3 py-1.5 text-sm w-full" />
                        </div>

                        {campaign?.attachments?.length > 0 && (
                            <div>
                                <label className="block text-sm font-medium mb-1">Existing Attachments</label>
                                <ul className="text-xs space-y-1 mb-2">
                                    {campaign.attachments.map((a) => (
                                        <li key={a.id} className="flex items-center gap-2">
                                            <input type="checkbox" checked={removeAttachmentIds.includes(a.id)} onChange={() => setRemoveAttachmentIds((prev) => prev.includes(a.id) ? prev.filter((x) => x !== a.id) : [...prev, a.id])} />
                                            <span>{a.original_filename} ({Math.round(a.file_size / 1024)} KB) — remove</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium mb-1">Attachments (optional)</label>
                            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg"
                                onChange={(e) => setAttachments(Array.from(e.target.files || []))} className="border rounded-lg px-3 py-1.5 text-sm w-full" />
                            {attachments.length > 0 && (
                                <ul className="mt-2 text-xs space-y-1">
                                    {attachments.map((f, i) => (
                                        <li key={i}>{f.name} ({Math.round(f.size / 1024)} KB)</li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Reply-To (optional)</label>
                            <input type="email" value={replyTo} onChange={(e) => setReplyTo(e.target.value)} className="border rounded-lg px-3 py-1.5 text-sm w-full" />
                        </div>

                        <div className="flex gap-3">
                            <button onClick={saveDraft} className="px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200">
                                {campaign ? 'Update Draft' : 'Save Draft'}
                            </button>
                            <button onClick={handlePreview} className="px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium hover:bg-gray-200">
                                Preview & Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {showPreview && preview && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-6">
                        <h3 className="text-lg font-semibold mb-2">Preview & Confirmation</h3>
                        <p className="text-sm text-gray-600 mb-2">Recipients: {preview.recipient_count} · Excluded: {preview.excluded_count}</p>
                        <p className="text-sm mb-1"><strong>Subject:</strong> {subject}</p>
                        <div className="border rounded p-3 text-sm mb-3 max-h-40 overflow-y-auto">
                            {message}
                        </div>
                        {preview.excluded_count > 0 && (
                            <div className="mb-3 text-xs text-red-600">
                                Excluded: {preview.excluded.map((e) => `${e.tenant_name} (${e.reason})`).join(', ')}
                            </div>
                        )}
                        <div className="max-h-48 overflow-y-auto border rounded p-2 text-xs mb-3">
                            {preview.recipients.map((r) => (
                                <div key={r.tenant_id}>{r.tenant_name} — {r.recipient_email}</div>
                            ))}
                        </div>
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setShowPreview(false)} className="px-3 py-1.5 bg-gray-100 rounded text-sm">Cancel</button>
                            <button onClick={handleSend} disabled={sending} className="px-3 py-1.5 bg-blue-600 text-white rounded text-sm disabled:opacity-50">
                                {sending ? 'Sending...' : 'Confirm Send'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
