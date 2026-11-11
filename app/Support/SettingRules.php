<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Satu sumber aturan validasi konfigurasi Growvia.
 *
 * Dipakai oleh panel admin web dan API mobile supaya aturan tidak menyimpang.
 * Panel web memakai seluruh daftar; API mobile memilih subset sesuai
 * {@see MobileSettings::SECTIONS}.
 */
final class SettingRules
{
    /** @var list<string> */
    public const NOTIFICATION_KEYS = [
        'notif_pompa',
        'notif_cahaya',
        'notif_tandon',
        'notif_offline',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'mqtt_host' => ['nullable', 'string', 'max:255'],
            'mqtt_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mqtt_user' => ['nullable', 'string', 'max:255'],
            'mqtt_topic' => ['nullable', 'string', 'max:255'],
            'update_interval' => ['required', 'integer', 'min:5', 'max:300'],
            'th_suhu_min' => ['required', 'numeric', 'lt:th_suhu_max'],
            'th_suhu_max' => ['required', 'numeric'],
            'th_hum_min' => ['required', 'numeric', 'min:0', 'max:100', 'lt:th_hum_max'],
            'th_hum_max' => ['required', 'numeric', 'min:0', 'max:100'],
            'th_light_min' => ['required', 'numeric', 'min:0'],
            'soil_on' => ['required', 'numeric', 'min:0', 'max:100', 'lt:soil_off'],
            'soil_off' => ['required', 'numeric', 'min:0', 'max:100'],
            'tank_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'pump_max_run' => ['required', 'integer', 'min:1', 'max:120'],
            'pump_mode' => ['required', Rule::in(['auto', 'manual'])],
            ...array_fill_keys(self::NOTIFICATION_KEYS, ['required', 'boolean']),
        ];
    }
}
