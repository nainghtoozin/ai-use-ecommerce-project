import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import { AlertTriangle, ArrowLeft, Clock, Database, Download, FileArchive, Images, KeyRound, Lock, ShieldCheck, Upload } from 'lucide-react';

export default function MerchantBackupIndex({
    storeName = '',
    signingConfigured = false,
    keyId = '',
    schemaVersion = 2,
    perHourLimit = 10,
    verifyPerHourLimit = 5,
    recoverPerHourLimit = 3,
    maxUploadMb = 50,
    uploadTtlHours = 1,
    hasPreview = false,
    totalCategories = 0,
    includedGroups = [],
    excludedAreas = [],
    mediaBundled = false,
    mediaRecoverable = false,
    encrypted = false,
    recoveryAvailable = false,
}) {
    const { data, setData, post, processing, progress, errors, reset } = useForm({ archive: null });

    const submitUpload = (event) => {
        event.preventDefault();
        post(adminUrl('/admin/backups/verify'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('archive'),
        });
    };

    return (
        <AdminLayout>
            <Head title="Merchant Backup" />

            <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-5xl mx-auto">
                <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                        <ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Merchant Backup</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            A signed copy of your own store data{storeName ? ` for ${storeName}` : ''}. Never includes another store.
                        </p>
                    </div>
                </div>

                {!signingConfigured && (
                    <div className="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-900/20 p-4 flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
                        <div className="text-sm text-red-800 dark:text-red-200">
                            <p className="font-semibold">Backups are unavailable</p>
                            <p className="mt-1">
                                Archive signing is not configured securely. An operator must set
                                {' '}<span className="font-mono">MERCHANT_BACKUP_SIGNING_KEY</span> to at least 32 characters before backups can be
                                downloaded. The download endpoint fails closed until then.
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6">
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                                <Database className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Signed store backup</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    Every store dataset as CSV plus a versioned, signed manifest and an asset checksum report.
                                </p>
                                <div className="flex flex-wrap gap-2 mt-3 text-xs">
                                    <span className="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-gray-600 dark:text-gray-300">
                                        <Database className="w-3.5 h-3.5" />
                                        {totalCategories} datasets
                                    </span>
                                    <span className="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-gray-600 dark:text-gray-300">
                                        <KeyRound className="w-3.5 h-3.5" />
                                        Manifest v{schemaVersion}
                                    </span>
                                    <span className="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-gray-600 dark:text-gray-300">
                                        <ShieldCheck className="w-3.5 h-3.5" />
                                        HMAC-SHA256 {keyId ? `(${keyId})` : ''}
                                    </span>
                                    <span className="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-gray-600 dark:text-gray-300">
                                        <Clock className="w-3.5 h-3.5" />
                                        {perHourLimit} per hour
                                    </span>
                                </div>
                            </div>
                        </div>

                        {signingConfigured ? (
                            <a
                                href={adminUrl('/admin/backups/download')}
                                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shrink-0"
                            >
                                <Download className="w-4 h-4" />
                                Download backup
                            </a>
                        ) : (
                            <span className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-400 bg-gray-100 dark:bg-gray-800 cursor-not-allowed shrink-0">
                                <Lock className="w-4 h-4" />
                                Download disabled
                            </span>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6 space-y-4">
                    <div className="flex items-start gap-3">
                        <div className="p-2 rounded-lg bg-sky-50 dark:bg-sky-900/30">
                            <FileArchive className="w-5 h-5 text-sky-600 dark:text-sky-400" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Verify a backup</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                Upload a signed backup you previously downloaded. Verification is read-only and never restores or changes data.
                            </p>
                        </div>
                    </div>

                    {hasPreview && (
                        <div className="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-900/20 p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p className="text-xs text-emerald-800 dark:text-emerald-200">
                                A verified archive is ready to review.
                            </p>
                            <a
                                href={adminUrl('/admin/backups/preview')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                Open recovery preview
                                <ArrowLeft className="w-3.5 h-3.5 rotate-180" />
                            </a>
                        </div>
                    )}

                    {!signingConfigured ? (
                        <p className="text-xs text-red-600 dark:text-red-400">
                            Verification is disabled until archive signing is configured.
                        </p>
                    ) : (
                        <form onSubmit={submitUpload} className="space-y-3">
                            <input
                                type="file"
                                accept=".zip,application/zip"
                                onChange={(event) => setData('archive', event.target.files?.[0] ?? null)}
                                disabled={processing}
                                className="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-700 dark:file:text-gray-200"
                            />

                            {errors.archive && (
                                <p className="text-xs font-medium text-red-600 dark:text-red-400">{errors.archive}</p>
                            )}

                            {progress && (
                                <div className="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                    <div className="h-full bg-sky-500 transition-all" style={{ width: `${progress.percentage}%` }} />
                                </div>
                            )}

                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    ZIP only, up to {maxUploadMb} MB. {verifyPerHourLimit} verifications per hour. Verified uploads are retained privately for up to {uploadTtlHours} hour{uploadTtlHours === 1 ? '' : 's'} so recovery can re-verify them, then deleted.
                                </p>
                                <button
                                    type="submit"
                                    disabled={processing || !data.archive}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-not-allowed transition-colors shrink-0"
                                >
                                    <Upload className="w-4 h-4" />
                                    {processing ? 'Verifying...' : 'Upload & verify'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>

                <div className="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
                    <Images className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <div className="text-sm text-amber-800 dark:text-amber-200 space-y-1">
                        <p className="font-semibold">Images and files are not included</p>
                        <p>
                            Product photos, logos, banners and other media binaries {mediaBundled ? 'are bundled' : 'are not bundled'}.
                            Only media metadata and checksums are exported, so {mediaRecoverable ? 'media can be restored' : 'images and files cannot be recovered from this archive'}.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex items-start gap-3">
                        <Lock className="w-5 h-5 text-gray-400 mt-0.5 shrink-0" />
                        <div className="text-sm text-gray-600 dark:text-gray-300">
                            <p className="font-semibold text-gray-900 dark:text-gray-100">{encrypted ? 'Archive is encrypted' : 'Archive is not encrypted'}</p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                The archive is signed to prove its authenticity, but its contents are readable. Store it privately and delete copies you no longer need.
                            </p>
                        </div>
                    </div>
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 text-gray-400 mt-0.5 shrink-0" />
                        <div className="text-sm text-gray-600 dark:text-gray-300">
                            <p className="font-semibold text-gray-900 dark:text-gray-100">{recoveryAvailable ? 'Recovery is ready' : 'Recovery is not available yet'}</p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {recoveryAvailable
                                    ? `A verified archive is retained. Open the preview to create a new inactive store. The source store is never modified. ${recoverPerHourLimit} recoveries per hour.`
                                    : 'Verify an archive first. Recovery only creates a new, inactive store from a verified archive and can never overwrite the live store.'}
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">What is inside</h2>
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex flex-wrap gap-2">
                        {includedGroups.map((group) => (
                            <span
                                key={group.name}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200"
                            >
                                {group.name}
                                <span className="text-gray-400 dark:text-gray-500">({group.categories})</span>
                            </span>
                        ))}
                    </div>
                </div>

                <div>
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">What is intentionally excluded</h2>
                    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        {excludedAreas.map((area) => (
                            <div key={area.area} className="p-4">
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{area.area}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{area.examples}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{area.reason}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 p-4">
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Need individual datasets or spreadsheet formats instead? Use the existing data export page.
                    </p>
                    <a
                        href={adminUrl('/admin/data-export')}
                        className="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        Export my data
                    </a>
                </div>
            </div>
        </AdminLayout>
    );
}
