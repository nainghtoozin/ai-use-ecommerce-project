import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { AlertTriangle, ArrowLeft, CheckCircle2, Clock, Database, Images, Lock, ShieldCheck, Store } from 'lucide-react';

function formatWhen(value) {
    if (!value) return 'Unknown';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function Stat({ label, value }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</p>
        </div>
    );
}

export default function MerchantBackupPreview({ preview = {}, recoveryEnabled = false }) {
    const summary = preview.summary || {};
    const categories = (preview.categories || []).filter((category) => category.records > 0);
    const conflicts = preview.conflicts || { items: [], total: 0, truncated: false };
    const excluded = preview.excluded || [];
    const dataLosses = preview.data_losses || [];
    const media = preview.media || {};
    const target = preview.target || {};
    const warnings = preview.warnings || [];
    const recovery = preview.recovery || {};
    const recoveryAvailable = recoveryEnabled && recovery.enabled;
    const form = useForm({
        confirmation: '',
        target_slug: recovery.target_slug || target.slug || '',
        accept: false,
    });

    const submitRecovery = (event) => {
        event.preventDefault();
        form.post(adminUrl('/admin/backups/recover'), { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Recovery Preview" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                            <ShieldCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Recovery preview</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Read-only preview of a verified backup. Nothing has been restored or changed.
                            </p>
                        </div>
                    </div>
                    <a
                        href={adminUrl('/admin/backups')}
                        className="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:text-blue-300 shrink-0"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        Back to Merchant Backup
                    </a>
                </div>

                <div className="rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" />
                    <div className="text-sm text-emerald-800 dark:text-emerald-200">
                        <p className="font-semibold">Archive verified</p>
                        <p className="mt-1 text-xs">
                            Signature, structure, tenant binding and every file checksum matched. Verified {formatWhen(preview.verified_at)}.
                        </p>
                        <p className="mt-1 font-mono text-[11px] break-all">Archive: {preview.archive_id}</p>
                        <p className="text-[11px]">Created {formatWhen(preview.archive_created_at)} by {preview.source_store?.name || 'unknown store'} ({preview.source_store?.slug || 'unknown slug'}).</p>
                    </div>
                </div>

                <div className="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-900/20 p-4 flex items-start gap-3">
                    <Lock className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
                    <div className="text-sm text-red-800 dark:text-red-200">
                        <p className="font-semibold">{recoveryAvailable ? 'Recovery is available' : 'Recovery is not available'}</p>
                        <p className="mt-1 text-xs">
                            {recoveryAvailable
                                ? 'This preview is read-only. Recovery runs only after you confirm the source store slug and a new target store slug below.'
                                : 'The verified upload has expired or this installation cannot start recovery. Verify the archive again.'}
                        </p>
                    </div>
                </div>

                {warnings.length > 0 && (
                    <div className="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                        <div className="text-xs text-amber-800 dark:text-amber-200 space-y-1">
                            {warnings.map((warning) => (
                                <p key={warning}>{warning}</p>
                            ))}
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <Stat label="Records" value={summary.total_records ?? 0} />
                    <Stat label="Datasets" value={summary.categories ?? 0} />
                    <Stat label="Files" value={preview.archive?.files ?? 0} />
                    <Stat label="Conflicts" value={summary.conflicts ?? 0} />
                    <Stat label="Media refs" value={summary.media_assets ?? 0} />
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex items-start gap-3">
                    <Store className="w-5 h-5 text-indigo-500 mt-0.5 shrink-0" />
                    <div className="text-sm text-gray-700 dark:text-gray-300">
                        <p className="font-semibold text-gray-900 dark:text-gray-100">Recovery target: a new inactive store</p>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Identifiers will be remapped and the new store will stay inactive for review. The live store is never overwritten.
                        </p>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Suggested store slug: <span className="font-mono">{target.slug || 'n/a'}</span>
                        </p>
                    </div>
                </div>

                {recoveryAvailable && (
                    <form
                        onSubmit={submitRecovery}
                        className="rounded-xl border border-red-200 dark:border-red-900/60 bg-white dark:bg-gray-900 p-4 space-y-4"
                    >
                        <div className="flex items-start gap-3">
                            <Lock className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
                            <div>
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Start recovery into a new inactive store</p>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    This creates a new pending store and imports the archive with remapped identifiers. The live store is never modified, merged into or deleted. Media binaries are not recovered.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                Type the current store slug to confirm
                                <input
                                    type="text"
                                    value={form.data.confirmation}
                                    onChange={(event) => form.setData('confirmation', event.target.value)}
                                    placeholder={recovery.source_slug || 'current-store-slug'}
                                    autoComplete="off"
                                    className="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500"
                                />
                                {form.errors.confirmation && <span className="mt-1 block text-red-600 dark:text-red-400">{form.errors.confirmation}</span>}
                            </label>

                            <label className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                New store slug
                                <input
                                    type="text"
                                    value={form.data.target_slug}
                                    onChange={(event) => form.setData('target_slug', event.target.value)}
                                    placeholder="restored-store"
                                    autoComplete="off"
                                    className="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500"
                                />
                                {form.errors.target_slug && <span className="mt-1 block text-red-600 dark:text-red-400">{form.errors.target_slug}</span>}
                            </label>
                        </div>

                        <label className="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={form.data.accept}
                                onChange={(event) => form.setData('accept', event.target.checked)}
                                className="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
                            />
                            <span>
                                I understand this creates a new inactive store, rewrites customer emails, renumbers stock movements and does not recover media binaries.
                            </span>
                        </label>
                        {form.errors.accept && <p className="text-xs text-red-600 dark:text-red-400">{form.errors.accept}</p>}
                        {form.errors.recovery && (
                            <p className="rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-300">{form.errors.recovery}</p>
                        )}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                        >
                            <ShieldCheck className="w-4 h-4" />
                            {form.processing ? 'Recovering...' : 'Recover into new inactive store'}
                        </button>
                    </form>
                )}

                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Recoverable records</h2>
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
                        {categories.length === 0 ? (
                            <p className="p-4 text-sm text-gray-500 dark:text-gray-400">This archive contains no recoverable records.</p>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dataset</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Group</th>
                                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Records</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {categories.map((category) => (
                                        <tr key={category.key}>
                                            <td className="px-4 py-2 text-gray-800 dark:text-gray-200">{category.label}</td>
                                            <td className="px-4 py-2 text-gray-500 dark:text-gray-400">{category.group}</td>
                                            <td className="px-4 py-2 text-right font-mono text-gray-800 dark:text-gray-200">{category.records}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Identifier conflicts</h2>
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                        {conflicts.total === 0 ? (
                            <p className="text-sm text-gray-500 dark:text-gray-400">No duplicate identifiers were found inside this archive.</p>
                        ) : (
                            <div className="space-y-2">
                                {conflicts.items.map((item) => (
                                    <p key={`${item.category}-${item.value}`} className="text-xs text-gray-600 dark:text-gray-300">
                                        <span className="font-semibold text-gray-900 dark:text-gray-100">{item.label}</span>
                                        {' '}<span className="font-mono">{item.value}</span> appears {item.count} times.
                                    </p>
                                ))}
                                {conflicts.truncated && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Only the first {conflicts.items.length} conflicts are shown.</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
                    <Images className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <div className="text-sm text-amber-800 dark:text-amber-200">
                        <p className="font-semibold">Media cannot be recovered</p>
                        <p className="mt-1 text-xs">
                            {media.note || 'Images and files are not part of the archive.'}
                            {media.summary && (
                                <> Local files verified at export: {media.summary.present || 0} present, {media.summary.missing || 0} missing, {media.summary.external || 0} external, {media.summary.invalid || 0} invalid.</>
                            )}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Known data losses</h2>
                        <ul className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-2 text-xs text-gray-600 dark:text-gray-300 list-disc list-inside">
                            {dataLosses.map((loss) => (
                                <li key={loss}>{loss}</li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Intentionally excluded</h2>
                        <ul className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-2 text-xs text-gray-600 dark:text-gray-300 list-disc list-inside">
                            {excluded.map((area) => (
                                <li key={area.area}>
                                    <span className="font-semibold text-gray-900 dark:text-gray-100">{area.area}:</span> {area.reason}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <Clock className="w-3.5 h-3.5" />
                        The uploaded archive is retained privately until recovery completes or the upload expires
                        {recovery.expires_at ? ` at ${formatWhen(recovery.expires_at)}` : ''}. It is deleted on success or verification failure.
                        {preview.excluded_settings?.total > 0 && (
                            <> {preview.excluded_settings.total} sensitive or non-allowlisted setting keys were excluded.</>
                        )}
                    </p>
                    <a
                        href={adminUrl('/admin/backups')}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800 transition-colors shrink-0"
                    >
                        <Database className="w-3.5 h-3.5" />
                        Merchant Backup
                    </a>
                </div>
            </div>
        </AdminLayout>
    );
}
