<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\PaymentIntent;
use App\Services\ActivityLogger;
use App\Services\Export\MerchantBackupPreviewService;
use App\Services\Export\MerchantBackupSigner;
use App\Services\Export\MerchantBackupVerificationException;
use App\Services\Export\MerchantBackupVerifier;
use App\Services\Export\MerchantDataExportService;
use App\Services\Recovery\MerchantRecoveryException;
use App\Services\Recovery\MerchantRecoveryService;
use App\Services\Recovery\MerchantStoreRestoreExistingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MerchantBackupController extends Controller
{
    private const PREVIEW_SESSION_KEY = 'merchant_backup_preview';

    private const PENDING_SESSION_KEY = 'merchant_backup_pending';

    private const RECOVERY_SESSION_KEY = 'merchant_backup_recovery_result';

    public function __construct(
        private readonly MerchantDataExportService $exporter,
        private readonly MerchantBackupVerifier $verifier,
        private readonly MerchantBackupPreviewService $previewService,
        private readonly MerchantRecoveryService $recoveryService,
        private readonly ?MerchantStoreRestoreExistingService $restoreExistingService = null
    ) {}

    public function index(Request $request)
    {
        $tenant = $this->authorizeBackup('backup.view');
        $signer = MerchantBackupSigner::fromConfig();
        $pending = $this->pendingUpload($request, $tenant);

        if (!$pending) {
            $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);
        }

        return Inertia::render('Admin/MerchantBackup/Index', [
            'storeName' => $tenant->name,
            'signingConfigured' => $signer->isConfigured(),
            'keyId' => $signer->keyId(),
            'schemaVersion' => (int) config('exports.backup.schema_version', 2),
            'perHourLimit' => (int) config('exports.backup.per_hour', 10),
            'verifyPerHourLimit' => (int) config('exports.backup.verify_per_hour', 5),
            'recoverPerHourLimit' => (int) config('exports.backup.recover_per_hour', 3),
            'maxUploadMb' => (int) round(max(1, (int) config('exports.backup.max_upload_kb', 51200)) / 1024),
            'uploadTtlHours' => (int) config('exports.backup.upload_ttl_hours', 1),
            'hasPreview' => $request->session()->has(self::PREVIEW_SESSION_KEY) && $pending !== null,
            'totalCategories' => count($this->exporter->categories()),
            'includedGroups' => $this->includedGroups(),
            'excludedAreas' => $this->exporter->backupExclusions(),
            'mediaBundled' => false,
            'mediaRecoverable' => false,
            'encrypted' => false,
            'recoveryAvailable' => $pending !== null && $signer->isConfigured(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $tenant = $this->authorizeBackup('backup.download');
        $signer = MerchantBackupSigner::fromConfig();

        if (!$signer->isConfigured()) {
            Log::warning('Merchant backup blocked: signing key is not configured', [
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            abort(503, 'Merchant backups are temporarily unavailable because archive signing is not configured.');
        }

        $archive = $this->exporter->downloadBackupArchive($tenant, (int) $request->user()?->getAuthIdentifier(), $signer);

        ActivityLogger::log(
            'Merchant backup archive downloaded',
            'merchant_backup_downloaded',
            $tenant,
            [
                'schema_version' => (int) config('exports.backup.schema_version', 2),
                'signing_key_id' => $signer->keyId(),
                'ip' => $request->ip(),
            ],
            'backup',
            ActivityLog::CATEGORY_SYSTEM
        );

        Log::info('Merchant backup archive downloaded', [
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'signing_key_id' => $signer->keyId(),
        ]);

        return $archive;
    }

    public function preview(Request $request)
    {
        $tenant = $this->authorizeBackup('backup.view');

        $preview = $request->session()->get(self::PREVIEW_SESSION_KEY);
        $pending = $this->pendingUpload($request, $tenant);

        if (!is_array($preview) || !isset($preview['archive_id']) || !$pending
            || (string) $preview['archive_id'] !== (string) $pending['archive_id']) {
            $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);

            return admin_redirect('admin.backups.index')
                ->with('info', 'Upload a backup archive to verify it and preview what can be recovered.');
        }

        $preview['recovery_enabled'] = true;
        $preview['recovery'] = [
            'enabled' => true,
            'source_slug' => $tenant->slug,
            'target_slug' => (string) ($preview['target']['slug'] ?? ''),
            'expires_at' => $pending['expires_at'],
            'upload_name' => $pending['name'],
        ];

        $request->session()->put(self::PREVIEW_SESSION_KEY, $preview);

        return Inertia::render('Admin/MerchantBackup/Preview', [
            'preview' => $preview,
            'recoveryEnabled' => true,
        ]);
    }

    public function verifyUpload(Request $request)
    {
        $tenant = $this->authorizeBackup('backup.verify');
        $signer = MerchantBackupSigner::fromConfig();

        if (!$signer->isConfigured()) {
            abort(503, 'Backup verification is temporarily unavailable because archive signing is not configured.');
        }

        try {
            $request->validate([
                'archive' => ['required', 'file', 'mimes:zip', 'max:' . $this->maxUploadKb()],
            ]);
        } catch (ValidationException $exception) {
            $this->auditRejection($request, $tenant, 'upload_validation');

            throw $exception;
        }

        $file = $request->file('archive');

        if (!$file) {
            abort(422, 'No archive was uploaded.');
        }

        $previous = $this->pendingUpload($request, $tenant);
        $upload = null;
        $archiveId = null;
        $retained = false;

        try {
            $upload = $this->verifier->storeUpload($file, $tenant->id);
            $verified = $this->verifier->verify($upload['path'], $tenant, $signer);
            $archiveId = (string) $verified['manifest']['archive_id'];
            $preview = $this->previewService->build($tenant, $upload['path'], $verified);

            if ($previous && $previous['path'] !== $upload['path']) {
                $this->verifier->forgetUpload($previous['path']);
            }

            $request->session()->put(self::PENDING_SESSION_KEY, [
                'path' => $upload['path'],
                'name' => $upload['name'],
                'bytes' => $upload['bytes'],
                'archive_id' => $archiveId,
                'verified_at' => $verified['verified_at'],
                'expires_at' => now()
                    ->addHours(max(1, (int) config('exports.backup.upload_ttl_hours', 1)))
                    ->toIso8601String(),
            ]);

            ActivityLogger::log(
                'Merchant backup verified',
                'merchant_backup_verified',
                $tenant,
                [
                    'archive_id' => $archiveId,
                    'bytes' => $verified['bytes'],
                    'total_records' => (int) $verified['manifest']['total_records'],
                    'conflicts' => (int) $preview['summary']['conflicts'],
                ],
                'backup',
                ActivityLog::CATEGORY_SYSTEM
            );

            $request->session()->put(self::PREVIEW_SESSION_KEY, $preview);
            $retained = true;

            return admin_redirect('admin.backups.preview')
                ->with('success', 'Backup verified. Review the read-only recovery preview below.');
        } catch (MerchantBackupVerificationException $exception) {
            $this->auditRejection($request, $tenant, $exception->reason(), $archiveId);

            Log::warning('Merchant backup rejected', [
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'reason' => $exception->reason(),
            ]);

            return back()
                ->withErrors(['archive' => $exception->getMessage()])
                ->with('error', $exception->getMessage());
        } finally {
            if (!$retained && $upload) {
                $removed = $this->verifier->forgetUpload($upload['path']);

                if ($removed) {
                    ActivityLogger::log(
                        'Merchant backup upload cleaned up',
                        'merchant_backup_upload_cleaned',
                        $tenant,
                        ['archive_id' => $archiveId],
                        'backup',
                        ActivityLog::CATEGORY_SYSTEM
                    );
                }
            }
        }
    }

    public function recover(Request $request)
    {
        $tenant = $this->authorizeBackup('backup.recover', true);

        $request->validate([
            'confirmation' => ['required', 'string', 'max:255'],
            'target_slug' => ['required', 'string', 'max:80'],
            'accept' => ['accepted'],
        ]);

        $pending = $this->pendingUpload($request, $tenant);

        if (!$pending) {
            $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);

            return admin_redirect('admin.backups.index')
                ->withErrors(['recovery' => 'The verified upload has expired. Verify the archive again before recovering.']);
        }

        $actor = auth()->user();

        if (!$actor) {
            abort(403, 'Unauthorized');
        }

        try {
            $result = $this->recoveryService->recover(
                $tenant,
                $pending['path'],
                (string) $pending['archive_id'],
                (string) $request->input('target_slug'),
                (string) $request->input('confirmation'),
                $actor
            );
        } catch (MerchantRecoveryException $exception) {
            if (in_array($exception->reason(), [
                'reverification_failed',
                'archive_mismatch',
                'archive_missing',
                'archive_expired',
                'corrupt_archive',
            ], true)) {
                $this->verifier->forgetUpload($pending['path']);
                $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);
            }

            return back()
                ->withErrors(['recovery' => $exception->getMessage()])
                ->with('error', $exception->getMessage());
        }

        $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);
        $request->session()->put(self::RECOVERY_SESSION_KEY, [
            'result' => $result,
            'scope' => [
                'actor_type' => get_class($actor),
                'actor_id' => (string) $actor->getAuthIdentifier(),
                'tenant_id' => (int) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
            ],
        ]);

        return admin_redirect('admin.backups.recovered')
            ->with('success', 'Recovery completed into a new inactive store.');
    }

    public function restoreExisting(Request $request)
    {
        return $this->handleRestoreExisting($request);
    }

    public function restoreToExisting(Request $request)
    {
        return $this->handleRestoreExisting($request);
    }

    public function restoreToExistingStore(Request $request)
    {
        return $this->handleRestoreExisting($request);
    }

    private function handleRestoreExisting(Request $request)
    {
        $tenant = $this->authorizeBackup('backup.recover', true);

        $request->validate([
            'confirmation' => ['required', 'string', 'max:255'],
            'accept' => ['accepted'],
        ]);

        $pending = $this->pendingUpload($request, $tenant);

        if (!$pending) {
            $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);

            return admin_redirect('admin.backups.index')
                ->withErrors(['recovery' => 'The verified upload has expired. Verify the archive again before restoring.']);
        }

        $actor = auth()->user();

        if (!$actor) {
            abort(403, 'Unauthorized');
        }

        $service = $this->restoreExistingService ?? app(MerchantStoreRestoreExistingService::class);

        try {
            $result = $service->restoreExisting(
                $tenant,
                $pending['path'],
                (string) $pending['archive_id'],
                (string) $request->input('confirmation'),
                $actor,
                true
            );
        } catch (MerchantRecoveryException $exception) {
            if (in_array($exception->reason(), [
                'reverification_failed',
                'archive_mismatch',
                'archive_missing',
                'archive_expired',
                'corrupt_archive',
            ], true)) {
                $this->verifier->forgetUpload($pending['path']);
                $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);
            }

            return back()
                ->withErrors(['recovery' => $exception->getMessage()])
                ->with('error', $exception->getMessage());
        }

        $request->session()->forget([self::PREVIEW_SESSION_KEY, self::PENDING_SESSION_KEY]);
        $request->session()->put(self::RECOVERY_SESSION_KEY, [
            'result' => $result,
            'scope' => [
                'actor_type' => get_class($actor),
                'actor_id' => (string) $actor->getAuthIdentifier(),
                'tenant_id' => (int) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
            ],
        ]);

        return admin_redirect('admin.backups.recovered')
            ->with('success', 'Restore completed into the existing store.');
    }
    public function recovered(Request $request)
    {
        $this->authorizeBackup('backup.view');

        $result = $this->recoveryResult($request);

        if ($result === null) {
            $request->session()->forget(self::RECOVERY_SESSION_KEY);

            return admin_redirect('admin.backups.index')
                ->with('info', 'No recovery result is available for this store and account.');
        }

        $tenant = Tenant::getCurrent();
        $subscription = $tenant?->subscription;
        $latestIntent = $tenant
            ? PaymentIntent::withoutTenantScope()->where('tenant_id', $tenant->id)->latest('id')->first()
            : null;

        $activationState = 'unknown';
        $nextAction = 'Review the store status in the admin dashboard.';

        if ($tenant && $tenant->status === 'active' && $subscription && $subscription->grantsService()) {
            $activationState = 'active';
            $nextAction = 'Your store is active. Customers can visit your storefront.';
        } elseif ($tenant && $tenant->status === 'suspended') {
            $activationState = 'suspended';
            $nextAction = 'Your store is temporarily suspended. Contact the platform administrator.';
        } elseif ($subscription && $subscription->isExpired()) {
            $activationState = 'expired';
            $nextAction = 'Your subscription has expired. Renew your plan to restore service.';
        } elseif ($tenant && $tenant->status === 'pending') {
            if ($subscription?->plan?->isFree()) {
                $activationState = 'awaiting_admin';
                $nextAction = 'Waiting for store activation by the platform administrator.';
            } elseif ($latestIntent && $latestIntent->status()->isPending()) {
                $activationState = 'payment_under_review';
                $nextAction = 'Payment under review — Waiting for SuperAdmin approval.';
            } elseif ($latestIntent) {
                $activationState = 'payment_required';
                $nextAction = 'Payment required — Submit your subscription payment for approval.';
            } else {
                $activationState = 'payment_required';
                $nextAction = 'Payment required — Submit your subscription payment for approval.';
            }
        }

        return Inertia::render('Admin/MerchantBackup/Recovered', [
            'result' => $result,
            'activation' => [
                'state' => $activationState,
                'next_action' => $nextAction,
            ],
        ]);
    }

    /**
     * The recovery result is scoped to the actor who ran the recovery and to
     * the store it was run from. Session data survives tenant switching, so an
     * unscoped payload could otherwise be read by a different member of staff
     * or after switching to another store.
     *
     * @return array<string, mixed>|null
     */
    private function recoveryResult(Request $request): ?array
    {
        $payload = $request->session()->get(self::RECOVERY_SESSION_KEY);

        if (!is_array($payload) || !is_array($payload['result'] ?? null) || !is_array($payload['scope'] ?? null)) {
            return null;
        }

        $actor = auth()->user();
        $tenant = Tenant::getCurrent();

        if (!$actor || !$tenant) {
            return null;
        }

        $scope = $payload['scope'];

        $sameActor = ($scope['actor_type'] ?? null) === get_class($actor)
            && (string) ($scope['actor_id'] ?? '') === (string) $actor->getAuthIdentifier();

        $sameTenant = (int) ($scope['tenant_id'] ?? 0) === (int) $tenant->id
            && (string) ($scope['tenant_slug'] ?? '') === (string) $tenant->slug;

        return $sameActor && $sameTenant ? $payload['result'] : null;
    }

    private function auditRejection(Request $request, Tenant $tenant, string $reason, ?string $archiveId = null): void
    {
        ActivityLogger::log(
            'Merchant backup rejected',
            'merchant_backup_rejected',
            $tenant,
            [
                'reason' => $reason,
                'archive_id' => $archiveId,
            ],
            'backup',
            ActivityLog::CATEGORY_SYSTEM
        );

        Log::warning('Merchant backup upload rejected', [
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{path: string, name: string, archive_id: string, expires_at: string}|null
     */
    private function pendingUpload(Request $request, Tenant $tenant): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (!is_array($pending) || empty($pending['path']) || empty($pending['archive_id'])) {
            return null;
        }

        $path = (string) $pending['path'];
        $expiresAt = strtotime((string) ($pending['expires_at'] ?? ''));

        if ($expiresAt === false || $expiresAt < time()) {
            return null;
        }

        $base = realpath(Storage::disk('local')->path('exports/' . $tenant->id . '/uploads'));
        $real = realpath($path);

        if ($base === false || $real === false || !str_starts_with(strtolower($real), strtolower($base . DIRECTORY_SEPARATOR))) {
            return null;
        }

        return [
            'path' => $real,
            'name' => (string) ($pending['name'] ?? basename($real)),
            'archive_id' => (string) $pending['archive_id'],
            'expires_at' => (string) $pending['expires_at'],
        ];
    }

    private function authorizeBackup(string $dedicatedPermission, bool $requireOwnership = false): Tenant
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        foreach (['billing.view', $dedicatedPermission] as $permission) {
            if (!Permission::where('name', $permission)->exists() || !$user->can($permission)) {
                abort(403, 'Unauthorized');
            }
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        if ($requireOwnership && !$user->isSuperAdmin() && !$tenant->isOwnedBy($user)) {
            abort(403, 'Only the store owner can recover this store.');
        }

        return $tenant;
    }

    private function includedGroups(): array
    {
        $groups = [];

        foreach ($this->exporter->categories() as $meta) {
            $group = $meta['group'] ?? 'Other';
            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }

        return array_map(
            fn (string $name, int $count) => ['name' => $name, 'categories' => $count],
            array_keys($groups),
            array_values($groups)
        );
    }

    private function maxUploadKb(): int
    {
        return max(1, (int) config('exports.backup.max_upload_kb', 51200));
    }
}
