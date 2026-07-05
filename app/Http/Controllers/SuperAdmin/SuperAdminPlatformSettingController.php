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
            'trial_enabled' => 'boolean',
            'trial_days' => 'nullable|integer|min:1|max:365',
            'allow_trial_renewal' => 'boolean',
            'max_trial_renewals' => 'nullable|integer|min:0|max:255',
            'platform_currency_code' => 'required|string|max:10',
            'platform_currency_symbol' => 'required|string|max:10',
            'platform_currency_position' => 'required|in:before,after',
            'platform_decimal_places' => 'required|integer|min:0|max:4',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
        ]);

        $data = [
            'site_name' => $validated['site_name'],
            'support_email' => $validated['support_email'],
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'registration_enabled' => $request->boolean('registration_enabled'),
            'trial_enabled' => $request->boolean('trial_enabled'),
            'trial_days' => $request->integer('trial_days'),
            'allow_trial_renewal' => $request->boolean('allow_trial_renewal'),
            'max_trial_renewals' => $request->integer('max_trial_renewals'),
            'platform_currency_code' => $validated['platform_currency_code'],
            'platform_currency_symbol' => $validated['platform_currency_symbol'],
            'platform_currency_position' => $validated['platform_currency_position'],
            'platform_decimal_places' => (int) $validated['platform_decimal_places'],
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
