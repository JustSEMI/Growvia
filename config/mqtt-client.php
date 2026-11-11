<?php

declare(strict_types=1);

// Konfigurasi php-mqtt/laravel-client, dipakai artisan mqtt:consume.
// Kredensial broker diisi lewat .env (MQTT_HOST, MQTT_PORT, dst).

return [

    'default' => env('MQTT_CONNECTION', 'default'),

    'connections' => [

        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'clientId' => env('MQTT_CLIENT_ID', 'growvia-backend'),
            'username' => env('MQTT_USERNAME'),
            'password' => env('MQTT_PASSWORD'),

            'connection_settings' => [
                'connect_timeout' => 5,
                'socket_timeout' => 5,
                'keep_alive_interval' => 30,
                'resend_failed_message' => false,
            ],
        ],

    ],

];
