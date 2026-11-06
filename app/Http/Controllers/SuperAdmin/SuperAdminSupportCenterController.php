<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminSupportCenterController extends Controller
{
    public function __construct(protected PlatformSettingService $platformSettingService) {}

    public function index()
    {
        return Inertia::render('SuperAdmin/SupportCenter/Index', [
            'settings' => $this->platformSettingService->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'support_center_enabled' => 'boolean',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'support_page_url' => 'nullable|url|max:2048',
            'support_button_text' => 'nullable|string|max:100',
            'support_title' => 'nullable|string|max:255',
            'support_description' => 'nullable|string|max:1000',
            'telegram_url' => 'nullable|url|max:2048',
            'telegram_username' => 'nullable|string|max:100',
            'viber_url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if ($value === null || $value === '') {
                    return;
                }
                $value = trim($value);
                if (str_starts_with($value, 'viber://')) {
                    if (!preg_match('#^viber://(chat|add)\?number=.+#i', $value)) {
                        $fail('The Viber URL must use viber://chat?number=... or viber://add?number=...');
                    }
                    return;
                }
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('The Viber URL must be a valid URL.');
                    return;
                }
                $scheme = parse_url($value, PHP_URL_SCHEME);
                if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $fail('The Viber URL must use http, https, or viber://.');
                }
            }],
            'whatsapp_url' => 'nullable|url|max:2048',
            'facebook_url' => 'nullable|url|max:2048',
            'messenger_url' => 'nullable|url|max:2048',
            'business_hours' => 'nullable|string|max:255',
            'support_schedule' => 'nullable|string',
            'platform_timezone' => 'nullable|string|max:100',
            'live_chat_enabled' => 'boolean',
        ]);

        $data = [
            'support_center_enabled' => $request->boolean('support_center_enabled'),
            'support_email' => $validated['support_email'] ?? null,
            'support_phone' => $validated['support_phone'] ?? null,
            'support_page_url' => $validated['support_page_url'] ?? null,
            'support_button_text' => $validated['support_button_text'] ?? null,
            'support_title' => $validated['support_title'] ?? null,
            'support_description' => $validated['support_description'] ?? null,
            'telegram_url' => $validated['telegram_url'] ?? null,
            'telegram_username' => $validated['telegram_username'] ?? null,
            'viber_url' => $validated['viber_url'] ?? null,
            'whatsapp_url' => $validated['whatsapp_url'] ?? null,
            'facebook_url' => $validated['facebook_url'] ?? null,
            'messenger_url' => $validated['messenger_url'] ?? null,
            'business_hours' => $validated['business_hours'] ?? null,
            'support_schedule' => $validated['support_schedule'] ?? null,
            'platform_timezone' => $validated['platform_timezone'] ?? null,
            'live_chat_enabled' => $request->boolean('live_chat_enabled'),
        ];

        $this->platformSettingService->update($data);

        return redirect()->back()->with('success', 'Support Center settings updated successfully.');
    }
}
