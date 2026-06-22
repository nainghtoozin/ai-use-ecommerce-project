<?php

namespace App\Services;

use App\Models\PlatformSetting;

class PlatformSettingService
{
    public function get(): PlatformSetting
    {
        return PlatformSetting::current();
    }

    public function update(array $data): PlatformSetting
    {
        $settings = PlatformSetting::current();

        $settings->update($data);

        PlatformSetting::clearCache();

        return $settings->fresh();
    }
}
