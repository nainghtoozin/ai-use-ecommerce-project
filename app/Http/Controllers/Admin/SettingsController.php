<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'telegram_link' => 'nullable|string|max:255',
            'viber_link' => 'nullable|string|max:255',
            'facebook_link' => 'nullable|string|max:255',
            'whatsapp_link' => 'nullable|string|max:255',
        ]);

        $keys = ['telegram_link', 'viber_link', 'facebook_link', 'whatsapp_link'];

        foreach ($keys as $key) {
            $value = $request->input($key);
            if (!empty($value)) {
                $value = $this->normalizeLink($key, $value);
            }
            Setting::set($key, $value);
        }

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Settings updated successfully.');
    }

    private function normalizeLink(string $key, string $value): string
    {
        $value = trim($value);

        return match ($key) {
            'telegram_link' => $this->formatTelegram($value),
            'whatsapp_link' => $this->formatWhatsApp($value),
            'facebook_link' => $this->formatFacebook($value),
            'viber_link' => $this->formatViber($value),
            default => $value,
        };
    }

    private function formatTelegram(string $value): string
    {
        if (str_starts_with($value, '@')) {
            return 'https://t.me/' . ltrim($value, '@');
        }
        if (!str_starts_with($value, 'http')) {
            return 'https://t.me/' . $value;
        }
        return $value;
    }

    private function formatWhatsApp(string $value): string
    {
        if (preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/[\s\-\(\)]/', '', $value))) {
            $number = preg_replace('/[^\d]/', '', $value);
            return 'https://wa.me/' . $number;
        }
        if (!str_starts_with($value, 'http')) {
            return 'https://wa.me/' . preg_replace('/[^\d]/', '', $value);
        }
        return $value;
    }

    private function formatFacebook(string $value): string
    {
        if (!str_starts_with($value, 'http')) {
            return 'https://facebook.com/' . ltrim($value, '/');
        }
        return $value;
    }

    private function formatViber(string $value): string
    {
        if (str_starts_with($value, '+')) {
            return 'viber://chat?number=' . urlencode($value);
        }
        if (!str_starts_with($value, 'http') && !str_starts_with($value, 'viber://')) {
            return 'viber://chat?number=' . $value;
        }
        return $value;
    }
}
