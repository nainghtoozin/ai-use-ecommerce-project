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

    public function preview()
    {
        $settings = $this->platformSettingService->get();

        return response()->view('errors.platform-maintenance', ['settings' => $settings], 200);
    }

    public function update(Request $request)
    {
        $settings = $this->platformSettingService->get();

        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'support_page_url' => 'nullable|url|max:2048',
            'support_phone' => 'nullable|string|max:50',
            'support_center_enabled' => 'boolean',
            'telegram_url' => 'nullable|url|max:2048',
            'telegram_username' => 'nullable|string|max:100',
            'viber_url' => 'nullable|url|max:2048',
            'support_title' => 'nullable|string|max:255',
            'support_description' => 'nullable|string|max:1000',
            'live_chat_enabled' => 'boolean',
            'business_hours' => 'nullable|string|max:255',
            'support_schedule' => 'nullable|string',
            'platform_timezone' => 'nullable|string|max:100',
            'maintenance_title' => 'nullable|string|max:255',
            'maintenance_message' => 'nullable|string|max:2000',
            'maintenance_return_time' => 'nullable|date',
            'support_button_text' => 'nullable|string|max:100',
            'maintenance_mode' => 'boolean',
            'registration_enabled' => 'boolean',
            'trial_enabled' => 'boolean',
            'trial_days' => 'nullable|integer|min:1|max:365',
            'allow_trial_renewal' => 'boolean',
            'max_trial_renewals' => 'nullable|integer|min:0|max:255',
            'billing_renewal_reminder_days' => 'nullable|integer|min:1|max:30',
            'platform_currency_code' => 'required|string|max:10',
            'platform_currency_symbol' => 'required|string|max:10',
            'platform_currency_position' => 'required|in:before,after',
            'platform_decimal_places' => 'required|integer|min:0|max:4',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
        ]);

        $data = [
            'site_name' => $validated['site_name'],
            'maintenance_title' => $validated['maintenance_title'] ?? null,
            'maintenance_message' => $validated['maintenance_message'] ?? null,
            'maintenance_return_time' => $validated['maintenance_return_time'] ?? null,
            'support_button_text' => $validated['support_button_text'] ?? null,
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'registration_enabled' => $request->boolean('registration_enabled'),
            'trial_enabled' => $request->boolean('trial_enabled'),
            'trial_days' => $request->integer('trial_days'),
            'allow_trial_renewal' => $request->boolean('allow_trial_renewal'),
            'max_trial_renewals' => $request->integer('max_trial_renewals'),
            'billing_renewal_reminder_days' => $request->filled('billing_renewal_reminder_days')
                ? $request->integer('billing_renewal_reminder_days')
                : 7,
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
