<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminNotificationSettingsController extends Controller
{
    public function edit()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/NotificationSettings', [
            'settings' => [
                'notifications_enabled' => $settings['notifications_enabled'] ?? 'true',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'notifications_enabled' => 'required',
        ]);

        Setting::set('notifications_enabled', $this->toBooleanString($request->input('notifications_enabled')));

        ActivityLogger::log(
            'Notification settings updated',
            'settings_updated',
            properties: [
                'notifications_enabled' => $this->toBooleanString($request->input('notifications_enabled')),
            ]
        );

        return admin_redirect('admin.settings.notifications')
            ->with('success', 'Notification settings updated successfully.');
    }

    private function toBooleanString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
}
