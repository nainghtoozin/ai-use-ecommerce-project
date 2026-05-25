<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'pusher'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),

            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap1'),
                'useTLS' => true,
                'client_options' => [
                    \GuzzleHttp\RequestOptions::TIMEOUT => 10,
                    \GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 5,
                ],
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
