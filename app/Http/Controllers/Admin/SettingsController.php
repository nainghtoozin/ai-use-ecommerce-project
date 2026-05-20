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

        $validated = $request->validated();
        unset($validated['logo'], $validated['favicon'], $validated['og_image'], $validated['hero_image'], $validated['footer_logo'], $validated['about_image']);

        $info->fill($validated);
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
}