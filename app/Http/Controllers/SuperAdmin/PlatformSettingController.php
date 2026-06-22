<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlatformSettingController extends Controller
{
    public function __construct(
        protected PlatformSettingService $platformSettingService
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
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_logo' => 'nullable|string|max:255',
            'favicon' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'maintenance_mode' => 'boolean',
            'registration_enabled' => 'boolean',
        ]);

        $this->platformSettingService->update($validated);

        return redirect()->back()->with('success', 'Platform settings updated successfully.');
    }
}
