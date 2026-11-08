<?php

return [
    'batch_size' => (int) env('MERCHANT_EMAIL_BATCH_SIZE', 10),
    'batch_delay_seconds' => (int) env('MERCHANT_EMAIL_BATCH_DELAY', 5),
    'max_attempts' => (int) env('MERCHANT_EMAIL_MAX_ATTEMPTS', 3),
    'job_timeout' => (int) env('MERCHANT_EMAIL_JOB_TIMEOUT', 90),
    'processing_lease_minutes' => (int) env('MERCHANT_EMAIL_PROCESSING_LEASE', 5),
    'rate_limit_per_minute' => (int) env('MERCHANT_EMAIL_RATE_LIMIT', 30),
    'queue' => env('MERCHANT_EMAIL_QUEUE', 'default'),
];
