<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    public function upload(UploadedFile $file, string $folder): string
    {
        if (app()->environment('production')) {
            return $this->uploadToCloudinary($file, $folder);
        }

        return $this->uploadToLocal($file, $folder);
    }

    private function uploadToCloudinary(UploadedFile $file, string $folder): string
    {
        $path = $file->store($folder, 'cloudinary');

        if (!$path || !str_starts_with($path, 'http')) {
            Log::error('Cloudinary upload did not return a URL', ['path' => $path]);
            throw new \RuntimeException('Cloudinary upload failed. Check your Cloudinary configuration.');
        }

        Log::info('Image uploaded to Cloudinary', ['folder' => $folder, 'path' => $path]);

        return $path;
    }

    private function uploadToLocal(UploadedFile $file, string $folder): string
    {
        $path = $file->store($folder, 'public');

        Log::info('Image uploaded to local storage', ['folder' => $folder, 'path' => $path]);

        return $path;
    }

    public function delete(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if (str_starts_with($path, 'http')) {
            return $this->deleteFromCloudinary($path);
        }

        return $this->deleteFromLocal($path);
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

    private function deleteFromLocal(string $path): bool
    {
        try {
            return Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete local image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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

        return asset('storage/' . $path);
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

        return Storage::disk('public')->exists($path);
    }

    public static function isLocalPath(?string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        return !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://');
    }
}
