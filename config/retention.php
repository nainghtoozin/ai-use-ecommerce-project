<?php

return [
    'timezone' => env('RETENTION_TIMEZONE', env('APP_TIMEZONE', 'UTC')),

    'protected_tenant_ids' => array_values(array_filter(array_map(
        static fn ($id) => (int) trim((string) $id),
        explode(',', (string) env('RETENTION_PROTECTED_TENANT_IDS', '')),
    ))),

    'protected_tenant_slugs' => array_values(array_filter(array_map(
        static fn ($slug) => trim((string) $slug),
        explode(',', (string) env('RETENTION_PROTECTED_TENANT_SLUGS', 'default')),
    ))),

    'protected_tenant_setting_key' => 'retention_protected',
];
