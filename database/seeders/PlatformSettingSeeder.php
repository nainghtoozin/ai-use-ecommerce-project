<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::updateOrCreate(['id' => 1], [
            'site_name' => 'My Application',
            'site_logo' => null,
            'favicon' => null,
            'support_email' => null,
            'maintenance_mode' => false,
            'registration_enabled' => true,
            'trial_enabled' => true,
            'trial_days' => 14,
        ]);

        PlatformSetting::clearCache();
    }
}
