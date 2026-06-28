<?php

return [

    'default' => env('PAYMENT_GATEWAY', 'manual_transfer'),

    'default_currency' => env('PAYMENT_CURRENCY', 'MMK'),

    'currencies' => ['MMK', 'USD', 'THB'],

    'gateways' => [

        'stripe' => [
            'name' => 'stripe',
            'display_name' => 'Stripe',
            'enabled' => env('STRIPE_ENABLED', false),
            'keys' => [
                'public' => env('STRIPE_KEY', ''),
                'secret' => env('STRIPE_SECRET', ''),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
            ],
            'provider' => \App\Services\Payment\Providers\StripeProvider::class,
            'supported_currencies' => ['USD'],
        ],

        'paypal' => [
            'name' => 'paypal',
            'display_name' => 'PayPal',
            'enabled' => env('PAYPAL_ENABLED', false),
            'keys' => [
                'client_id' => env('PAYPAL_CLIENT_ID', ''),
                'secret' => env('PAYPAL_SECRET', ''),
                'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
            ],
            'provider' => \App\Services\Payment\Providers\PayPalProvider::class,
            'supported_currencies' => ['USD'],
        ],

        'paddle' => [
            'name' => 'paddle',
            'display_name' => 'Paddle',
            'enabled' => env('PADDLE_ENABLED', false),
            'keys' => [
                'vendor_id' => env('PADDLE_VENDOR_ID', ''),
                'vendor_auth_code' => env('PADDLE_VENDOR_AUTH_CODE', ''),
                'public_key' => env('PADDLE_PUBLIC_KEY', ''),
            ],
            'provider' => \App\Services\Payment\Providers\PaddleProvider::class,
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
        ],

        'manual_transfer' => [
            'name' => 'manual_transfer',
            'display_name' => 'Manual Bank Transfer',
            'enabled' => true,
            'keys' => [],
            'provider' => \App\Services\Payment\Providers\ManualTransferProvider::class,
            'supported_currencies' => ['MMK', 'USD', 'THB'],
        ],

        'kpay' => [
            'name' => 'kpay',
            'display_name' => 'KBZ Pay',
            'enabled' => env('KPAY_ENABLED', false),
            'keys' => [
                'merchant_code' => env('KPAY_MERCHANT_CODE', ''),
                'api_key' => env('KPAY_API_KEY', ''),
            ],
            'provider' => \App\Services\Payment\Providers\KBZPayProvider::class,
            'supported_currencies' => ['MMK'],
        ],

        'wavepay' => [
            'name' => 'wavepay',
            'display_name' => 'WavePay',
            'enabled' => env('WAVEPAY_ENABLED', false),
            'keys' => [
                'merchant_code' => env('WAVEPAY_MERCHANT_CODE', ''),
                'api_key' => env('WAVEPAY_API_KEY', ''),
            ],
            'provider' => \App\Services\Payment\Providers\WavePayProvider::class,
            'supported_currencies' => ['MMK'],
        ],

        'ayapay' => [
            'name' => 'ayapay',
            'display_name' => 'AYA Pay',
            'enabled' => env('AYAPAY_ENABLED', false),
            'keys' => [
                'merchant_code' => env('AYAPAY_MERCHANT_CODE', ''),
                'api_key' => env('AYAPAY_API_KEY', ''),
            ],
            'provider' => \App\Services\Payment\Providers\AYAPayProvider::class,
            'supported_currencies' => ['MMK'],
        ],

    ],

];
