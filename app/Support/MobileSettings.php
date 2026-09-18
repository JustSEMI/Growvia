<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\SettingsController;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class MobileSettings
{
    public const SECTIONS = [
        'general' => ['update_interval'],
        'climate' => ['th_suhu_min', 'th_suhu_max', 'th_hum_min', 'th_hum_max', 'th_light_min', 'th_co2_min', 'th_co2_max'],
        'soil' => ['soil_on', 'soil_off', 'tank_min', 'pump_max_run', 'pump_mode'],
        'notifications' => ['notif_pompa', 'notif_cahaya', 'notif_tandon', 'notif_offline'],
    ];

    /** @return array<string, string> */
    public static function values(bool $lock = false): array
    {
        $keys = array_merge(...array_values(self::SECTIONS));
        $query = Setting::query()->whereIn('key', $keys);
        if ($lock) {
            $query->lockForUpdate();
        }

        return array_replace(array_intersect_key(SettingsController::DEFAULTS, array_flip($keys)), $query->pluck('value', 'key')->all());
    }

    /** @param array<string, mixed> $values
     * @param  array<string, string>  $stored
     * @return array<string, string>
     */
    public static function validate(string $section, array $values, array $stored): array
    {
        $keys = self::SECTIONS[$section];
        MobileInput::only($values, $keys);
        $rules = [
            'update_interval' => ['required', 'integer', 'min:5', 'max:300'],
            'th_suhu_min' => ['required', 'numeric', 'lt:th_suhu_max'],
            'th_suhu_max' => ['required', 'numeric'],
            'th_hum_min' => ['required', 'numeric', 'min:0', 'max:100', 'lt:th_hum_max'],
            'th_hum_max' => ['required', 'numeric', 'min:0', 'max:100'],
            'th_light_min' => ['required', 'numeric', 'min:0'],
            'th_co2_min' => ['required', 'numeric', 'min:0', 'lt:th_co2_max'],
            'th_co2_max' => ['required', 'numeric', 'min:0'],
            'soil_on' => ['required', 'numeric', 'min:0', 'max:100', 'lt:soil_off'],
            'soil_off' => ['required', 'numeric', 'min:0', 'max:100'],
            'tank_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'pump_max_run' => ['required', 'integer', 'min:1', 'max:120'],
            'pump_mode' => ['required', Rule::in(['auto', 'manual'])],
        ];
        foreach (self::SECTIONS['notifications'] as $key) {
            $rules[$key] = ['required', 'boolean'];
        }
        $validated = Validator::make(array_replace($stored, $values), array_intersect_key($rules, array_flip($keys)))->validate();
        foreach ($validated as $key => $value) {
            $validated[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $validated;
    }
}
