<?php

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return optional(\App\Models\Setting::where('key', $key)->first())->value ?? $default;
    }
}

if (!function_exists('image_url')) {
    function image_url(?string $path, string $placeholder = ''): string
    {
        return \App\Services\ImageService::url($path, $placeholder);
    }
}

if (!function_exists('feature_enabled')) {
    function feature_enabled(string $featureKey): bool
    {
        return \App\Services\FeatureGate::enabled($featureKey);
    }
}

if (!function_exists('feature_disabled')) {
    function feature_disabled(string $featureKey): bool
    {
        return !feature_enabled($featureKey);
    }
}

if (!function_exists('feature_for_user')) {
    function feature_for_user(?\App\Models\User $user = null): \App\Services\FeatureGate
    {
        return \App\Services\FeatureGate::forUser($user);
    }
}

if (!function_exists('upgrade_hint')) {
    function upgrade_hint(string $featureKey): ?string
    {
        return \App\Services\FeatureGate::forUser()->getUpgradeHint($featureKey);
    }
}
