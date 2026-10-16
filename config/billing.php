<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Expiry Cleanup
    |--------------------------------------------------------------------------
    |
    | When a paid subscription expires without an approved renewal or an active
    | paid subscription, the tenant enters a grace period measured from the
    | authoritative subscription expiry timestamp. After the grace period the
    | tenant is restricted (not deleted) and queued for Super Admin review.
    |
    */

    'tenant_cleanup' => [
        // Grace period length (days) from subscription expiry before restricting.
        // The post-restriction data-retention window is governed by the
        // SuperAdmin platform setting `tenant_data_retention_days` (default 15).
        'grace_days' => (int) env('TENANT_CLEANUP_GRACE_DAYS', 15),

        // Reminder thresholds (days remaining before grace end).
        'reminder_days' => [7, 3, 1],

        // Extension applied when a payment is still under review at the deadline.
        'pending_review_extension_days' => (int) env('TENANT_CLEANUP_PENDING_EXTENSION_DAYS', 7),

        // Destructive permanent purge is intentionally disabled until the full
        // tenant-data inventory and retention safeguards have been verified.
        'purge_enabled' => (bool) env('TENANT_CLEANUP_PURGE_ENABLED', false),

        // Maximum automatic purge attempts before a tenant is parked for manual
        // review, preventing an endless retry loop on persistently failing assets.
        'max_purge_attempts' => (int) env('TENANT_CLEANUP_MAX_PURGE_ATTEMPTS', 5),
    ],

];
