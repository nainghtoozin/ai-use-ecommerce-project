<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    public function __construct(
        private readonly ImageUploadService $uploadService,
    ) {}

    public function upload(UploadedFile $file, string $folder): string
    {
        $this->assertStorageLimit($file);

        $path = $this->uploadService->upload($file, $folder);

        $this->trackStorage($file->getSize());

        return $path;
    }

    public function delete(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        $fileSize = $this->getFileSize($path);

        $deleted = false;

        if (str_starts_with($path, 'http')) {
            $deleted = $this->getDefaultDisk() === 'cloudinary'
                && $this->deleteFromCloudinary($path);
        } else {
            $deleted = $this->deleteFromDefaultDisk($path);
        }

        if ($deleted && $fileSize > 0) {
            $this->releaseStorage($fileSize);
        }

        return $deleted;
    }

    private function deleteFromCloudinary(string $url): bool
    {
        try {
            $publicId = $this->extractPublicId($url);
            if ($publicId) {
                Storage::disk('cloudinary')->delete($publicId);
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to delete Cloudinary image', [
                'path' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function deleteFromDefaultDisk(string $path): bool
    {
        try {
            return Storage::disk($this->getDefaultDisk())->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete image from default disk', [
                'disk' => $this->getDefaultDisk(),
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function assertStorageLimit(UploadedFile $file): void
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return;
        }

        SubscriptionLimitService::for($tenant)->assertCanUpload($file->getSize());
    }

    private function trackStorage(int $bytes): void
    {
        $tenant = $this->resolveTenant();
        if (!$tenant || $bytes <= 0) {
            return;
        }

        $tenant->increment('used_storage_bytes', $bytes);
    }

    private function releaseStorage(int $bytes): void
    {
        $tenant = $this->resolveTenant();
        if (!$tenant || $bytes <= 0) {
            return;
        }

        $tenant->decrement('used_storage_bytes', $bytes);
    }

    private function getFileSize(string $path): int
    {
        if (str_starts_with($path, 'http')) {
            return 0;
        }

        try {
            return Storage::disk($this->getDefaultDisk())->size($path) ?: 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getDefaultDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function resolveTenant(): ?Tenant
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user instanceof \App\Models\Account) {
                return Tenant::getCurrent();
            }
            return $user->tenant;
        }

        return Tenant::getCurrent();
    }

    private function extractPublicId(string $url): ?string
    {
        $parts = parse_url($url);
        if (!isset($parts['path'])) {
            return null;
        }

        $path = ltrim($parts['path'], '/');
        $segments = explode('/', $path);

        $uploadIndex = array_search('upload', $segments);
        if ($uploadIndex === false) {
            return null;
        }

        $relevant = array_slice($segments, $uploadIndex + 2);
        $publicId = implode('/', $relevant);

        $publicId = preg_replace('/\.(png|jpg|jpeg|webp|gif|svg)(\?.*)?$/i', '', $publicId);

        return $publicId ?: null;
    }

    public static function url(?string $path, string $placeholder = ''): string
    {
        if (empty($path)) {
            return $placeholder ?: self::placeholderUrl();
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = config('filesystems.default', 'public');
        $driver = config("filesystems.disks.{$disk}.driver");

        if ($driver === 'local') {
            return asset('storage/' . $path);
        }

        return Storage::disk($disk)->url($path);
    }

    public static function placeholderUrl(): string
    {
        return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f3f4f6"/><text x="100" y="100" text-anchor="middle" dominant-baseline="central" font-family="Arial" font-size="14" fill="#9ca3af">No Image</text></svg>');
    }

    public static function exists(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if (str_starts_with($path, 'http')) {
            return true;
        }

        return Storage::disk(config('filesystems.default', 'public'))->exists($path);
    }

    public static function isLocalPath(?string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        return !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://');
    }
}
