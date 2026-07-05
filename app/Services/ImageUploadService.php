<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    public function __construct(
        private readonly ImageOptimizationService $optimizer,
    ) {}

    public function upload(UploadedFile $file, string $folder): string
    {
        $disk = $this->resolveDisk();

        if ($this->isCloudinaryDisk($disk)) {
            $path = $this->storeToCloudinary($file, $folder, $disk);
        } else {
            $filename = $this->generateFilename($file);
            $path = $this->storeToDisk($file, $folder, $filename, $disk);

            if ($this->isLocalDisk($disk)) {
                $this->optimize($path, $folder, $disk);
            }
        }

        return $path;
    }

    private function resolveDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function isCloudinaryDisk(string $disk): bool
    {
        return $disk === 'cloudinary';
    }

    private function isLocalDisk(string $disk): bool
    {
        $driver = config("filesystems.disks.{$disk}.driver");

        return $driver === 'local';
    }

    private function generateFilename(UploadedFile $file): string
    {
        return time() . '_' . uniqid() . '.' . $file->guessExtension();
    }

    private function storeToDisk(UploadedFile $file, string $folder, string $filename, string $disk): string
    {
        $path = $file->storeAs($folder, $filename, $disk);

        Log::info('Image uploaded', ['disk' => $disk, 'folder' => $folder, 'path' => $path]);

        return $path;
    }

    private function storeToCloudinary(UploadedFile $file, string $folder, string $disk): string
    {
        $path = $file->store($folder, $disk);

        if (!$path || !str_starts_with($path, 'http')) {
            Log::error('Cloudinary upload did not return a URL', ['path' => $path]);
            throw new \RuntimeException('Cloudinary upload failed. Check your Cloudinary configuration.');
        }

        Log::info('Image uploaded to Cloudinary', ['folder' => $folder, 'path' => $path]);

        return $path;
    }

    private function optimize(string $relativePath, string $folder, string $disk): void
    {
        try {
            $root = config("filesystems.disks.{$disk}.root", storage_path('app/public'));
            $fullPath = $root . '/' . $relativePath;
            $this->optimizer->optimize($fullPath, $folder);
        } catch (\Throwable $e) {
            Log::warning('Image optimization skipped', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
