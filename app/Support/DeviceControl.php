<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\Rule;

final class DeviceControl
{
    /** @return array<string, list<mixed>> */
    public static function rules(?string $node, ?string $mode): array
    {
        $actuators = $node === 'node1' ? ['fan1', 'fan2'] : ['pump'];

        return [
            'node' => ['required', 'string', Rule::in(['node1', 'node2'])],
            'actuator' => ['required', 'string', Rule::in($actuators)],
            'mode' => ['nullable', 'string', Rule::in(['auto', 'manual'])],
            'state' => [Rule::requiredIf($mode !== 'auto'), 'nullable', 'boolean'],
        ];
    }

    /**
     * Simpan perubahan mode pompa bila ada lalu kembalikan konfigurasi terkini untuk publish MQTT.
     *
     * @return array{mode: string, state: ?bool, sync_config: bool, settings: array<string, string>}
     */
    public static function prepare(
        User $actor,
        string $node,
        string $actuator,
        ?bool $state,
        ?string $requestedMode,
    ): array {
        $mode = $requestedMode ?? 'manual';
        $stored = array_replace(SettingRules::DEFAULTS, Setting::query()->lockForUpdate()->pluck('value', 'key')->all());
        $syncConfig = false;

        if ($node === 'node2' && $actuator === 'pump' && $stored['pump_mode'] !== $mode) {
            Setting::updateOrCreate(['key' => 'pump_mode'], ['value' => $mode]);
            ActivityLog::record('settings.updated', $actor, null, ['pump_mode']);
            $stored['pump_mode'] = $mode;
            $syncConfig = true;
        }

        return [
            'mode' => $mode,
            'state' => $mode === 'auto' ? null : $state,
            'sync_config' => $syncConfig,
            'settings' => $stored,
        ];
    }
}
