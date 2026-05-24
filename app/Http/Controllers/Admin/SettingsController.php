<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteSettingsRequest;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function edit(): \Inertia\Response
    {
        $settings = WebsiteInfo::getSettings();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $settings->toArray(),
        ]);
    }

    public function update(UpdateWebsiteSettingsRequest $request)
    {
        $info = WebsiteInfo::firstOrCreate(['id' => 1]);

        $imageFields = ['logo', 'favicon', 'og_image', 'hero_image', 'footer_logo', 'about_image'];

        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                if ($info->$field) {
                    $this->imageService->delete($info->$field);
                }
                $info->$field = $this->imageService->upload($request->file($field), 'website-settings');
            }
        }

        if ($request->has('hero_images_payload')) {
            $payload = json_decode($request->input('hero_images_payload'), true);
            $newFiles = $request->file('hero_images', []);
            $existingImages = $info->hero_images ?? [];
            $orderedImages = [];
            $newIndex = 0;

            foreach ($payload as $item) {
                if (($item['type'] ?? '') === 'new') {
                    if (isset($newFiles[$newIndex])) {
                        $path = $this->imageService->upload($newFiles[$newIndex], 'website-settings');
                        $orderedImages[] = $this->normalizePath($path);
                        $newIndex++;
                    }
                } elseif (($item['type'] ?? '') === 'existing') {
                    $orderedImages[] = $this->normalizePath($item['path'] ?? '');
                }
            }

            foreach ($existingImages as $existingPath) {
                $normalized = $this->normalizePath($existingPath);
                if (!in_array($normalized, $orderedImages, true)) {
                    $this->imageService->delete($existingPath);
                }
            }

            $info->hero_images = !empty($orderedImages) ? $orderedImages : null;
        } elseif ($request->hasFile('hero_images')) {
            $existingImages = $info->hero_images ?? [];
            $keepExisting = $request->input('hero_images_existing', []);
            $keepNormalized = array_map(fn($p) => $this->normalizePath($p), $keepExisting);

            foreach ($existingImages as $existingPath) {
                $normalized = $this->normalizePath($existingPath);
                if (!in_array($normalized, $keepNormalized, true)) {
                    $this->imageService->delete($existingPath);
                }
            }

            $newPaths = [];
            foreach ($request->file('hero_images') as $file) {
                $path = $this->imageService->upload($file, 'website-settings');
                $newPaths[] = $this->normalizePath($path);
            }

            $info->hero_images = array_values(array_merge($keepNormalized, $newPaths));
        } elseif ($request->has('hero_images_existing')) {
            $keepExisting = $request->input('hero_images_existing', []);
            $existingImages = $info->hero_images ?? [];
            $keepNormalized = array_map(fn($p) => $this->normalizePath($p), $keepExisting);

            foreach ($existingImages as $existingPath) {
                $normalized = $this->normalizePath($existingPath);
                if (!in_array($normalized, $keepNormalized, true)) {
                    $this->imageService->delete($existingPath);
                }
            }

            $info->hero_images = array_values($keepNormalized);
        }

        $validated = $request->validated();
        unset($validated['logo'], $validated['favicon'], $validated['og_image'], $validated['hero_image'], $validated['footer_logo'], $validated['about_image']);
        unset($validated['hero_images'], $validated['hero_images_existing'], $validated['hero_images_payload']);

        $contactInfo = [
            'primary_phone' => $validated['phone'] ?? '',
            'secondary_phone' => $validated['secondary_phone'] ?? '',
            'support_email' => $validated['support_email'] ?? '',
            'sales_email' => $validated['sales_email'] ?? '',
            'contact_email' => $validated['contact_email'] ?? '',
            'whatsapp_number' => $validated['whatsapp_number'] ?? '',
            'telegram_username' => $validated['telegram_username'] ?? '',
        ];

        $addressInfo = [
            'address_line_1' => $validated['address_line_1'] ?? ($validated['address'] ?? ''),
            'address_line_2' => $validated['address_line_2'] ?? '',
            'city' => $validated['city'] ?? '',
            'state_region' => $validated['state'] ?? '',
            'postal_code' => $validated['postal_code'] ?? '',
            'country' => $validated['country'] ?? '',
            'google_maps_link' => $validated['google_maps_link'] ?? ($validated['google_maps_embed_url'] ?? ''),
        ];

        $footerSettings = [
            'description' => $validated['footer_description'] ?? '',
            'extra_text' => $validated['footer_extra_text'] ?? '',
            'show_contact_button' => true,
            'show_social_icons' => true,
            'compact_mode' => true,
        ];

        $newFields = ['secondary_phone', 'sales_email', 'telegram_username', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'google_maps_link', 'footer_extra_text'];
        foreach ($newFields as $field) {
            unset($validated[$field]);
        }

        $info->fill($validated);
        $info->contact_info = $contactInfo;
        $info->address_info = $addressInfo;
        $info->footer_settings = $footerSettings;
        $info->save();

        WebsiteInfo::clearCache();
        Cache::forget('categories');

        Log::info('Website settings updated', [
            'user_id' => auth()->id(),
            'updated_fields' => array_keys($validated),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }

    private function normalizePath(string $path): string
    {
        if (empty($path)) {
            return $path;
        }

        if (preg_match('#^https?://[^/]+/storage/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($path, '/storage/')) {
            return substr($path, 9);
        }

        return $path;
    }
}