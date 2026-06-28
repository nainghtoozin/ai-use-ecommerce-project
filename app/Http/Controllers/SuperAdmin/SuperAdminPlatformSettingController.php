<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\ImageService;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminPlatformSettingController extends Controller
{
    public function __construct(
        protected PlatformSettingService $platformSettingService,
        protected ImageService $imageService,
    ) {}

    public function index()
    {
        $settings = $this->platformSettingService->get();

        return Inertia::render('SuperAdmin/PlatformSettings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $settings = $this->platformSettingService->get();

        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'maintenance_mode' => 'boolean',
            'registration_enabled' => 'boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
        ]);

        $data = [
            'site_name' => $validated['site_name'],
            'support_email' => $validated['support_email'],
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'registration_enabled' => $request->boolean('registration_enabled'),
        ];

        if ($request->hasFile('logo')) {
            if ($settings->site_logo) {
                $this->imageService->delete($settings->site_logo);
            }
            $data['site_logo'] = $this->imageService->upload($request->file('logo'), 'platform-settings');
        }

        if ($request->hasFile('favicon')) {
            if ($settings->favicon) {
                $this->imageService->delete($settings->favicon);
            }
            $data['favicon'] = $this->imageService->upload($request->file('favicon'), 'platform-settings');
        }

        $this->platformSettingService->update($data);

        return redirect()->back()->with('success', 'Platform settings updated successfully.');
    }
}
