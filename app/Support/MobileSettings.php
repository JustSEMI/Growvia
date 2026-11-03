<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\SettingsController;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

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
        $rules = array_intersect_key(SettingRules::rules(), array_flip($keys));
        $validated = Validator::make(array_replace($stored, $values), $rules)->validate();
        foreach ($validated as $key => $value) {
            $validated[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $validated;
    }
}
