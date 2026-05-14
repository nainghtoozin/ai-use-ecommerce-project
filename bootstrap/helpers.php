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
