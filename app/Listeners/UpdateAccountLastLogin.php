<?php

namespace App\Listeners;

use App\Models\Account;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class UpdateAccountLastLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof Account) {
            $ip = request()->ip();
            $userAgent = request()->userAgent();

            // Parse basic browser/platform info from user agent
            $browser = $this->parseBrowser($userAgent);
            $platform = $this->parsePlatform($userAgent);
            $device = $this->parseDevice($userAgent);

            // Update account login info
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ]);

            // Log activity with full details
            ActivityLogger::log(
                "User logged in from {$browser} on {$platform}",
                'login',
                $user,
                [
                    'ip' => $ip,
                    'browser' => $browser,
                    'platform' => $platform,
                    'device' => $device,
                    'user_agent' => $userAgent,
                ],
                'auth'
            );
        }
    }

    protected function parseBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Edg')) return 'Edge';
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Opera') || str_contains($userAgent, 'OPR')) return 'Opera';
        return 'Unknown';
    }

    protected function parsePlatform(string $userAgent): string
    {
        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS')) return 'macOS';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) return 'iOS';
        return 'Unknown';
    }

    protected function parseDevice(string $userAgent): string
    {
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android')) return 'Mobile';
        if (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) return 'Tablet';
        return 'Desktop';
    }
}
