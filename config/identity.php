<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control the gradual migration from the legacy User model
    | to the new Account + Membership identity architecture.
    |
    | All flags default to FALSE to preserve backward compatibility.
    | Each flag is flipped to TRUE after its corresponding migration phase
    | is deployed and verified.
    |
    */

    'use_accounts' => env('IDENTITY_USE_ACCOUNTS', false),

    'use_gate_before' => env('IDENTITY_USE_GATE_BEFORE', false),

    'migrate_notifications' => env('IDENTITY_MIGRATE_NOTIFICATIONS', false),

    'migrate_billing' => env('IDENTITY_MIGRATE_BILLING', false),

    'migrate_payments' => env('IDENTITY_MIGRATE_PAYMENTS', false),

    'migrate_orders' => env('IDENTITY_MIGRATE_ORDERS', false),

];
