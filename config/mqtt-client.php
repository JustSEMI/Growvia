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

            // php-mqtt/laravel-client >= 1.8 membaca kredensial dari
            // connection_settings.auth, bukan level koneksi.
            'connection_settings' => [
                'auth' => [
                    'username' => env('MQTT_USERNAME'),
                    'password' => env('MQTT_PASSWORD'),
                ],
                'connect_timeout' => 5,
                'socket_timeout' => 5,
                'keep_alive_interval' => 30,
            ],
        ],

    ],

];
