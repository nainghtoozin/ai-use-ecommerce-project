<?php

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return optional(\App\Models\Setting::where('key', $key)->first())->value ?? $default;
    }
}
