<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\Rule;

final class DeviceControl
{
    /**
     * @param  list<string>|null  $allowedNodes
     * @param  list<string>|null  $allowedActuators
     * @return array<string, list<mixed>>
     */
    public static function rules(
        ?string $node,
        ?string $mode,
        ?array $allowedNodes = null,
        ?array $allowedActuators = null,
    ): array {
        $nodes = $allowedNodes ?? ['node1', 'node2'];
        $actuators = $allowedActuators ?? ($node === 'node1' ? ['fan1'] : ['pump']);

        return [
            'node' => ['required', 'string', Rule::in($nodes)],
            'actuator' => ['required', 'string', Rule::in($actuators)],
            'mode' => ['nullable', 'string', Rule::in(['auto', 'manual'])],
            'state' => [Rule::requiredIf($mode !== 'auto'), 'nullable', 'boolean'],
        ];
    }

    /**
     * Hitung payload kontrol dan config terkini tanpa memutasi database sebelum publish MQTT berhasil.
     *
     * @return array{mode: string, state: ?bool, sync_config: bool, settings: array<string, string>}
     */
    public static function plan(
        string $node,
        string $actuator,
        ?bool $state,
        ?string $requestedMode,
    ): array {
        $mode = $requestedMode ?? 'manual';
        $stored = array_replace(SettingRules::DEFAULTS, Setting::query()->pluck('value', 'key')->all());
        $syncConfig = $node === 'node2' && $actuator === 'pump' && $stored['pump_mode'] !== $mode;
        if ($syncConfig) {
            $stored['pump_mode'] = $mode;
        }

        return [
            'mode' => $mode,
            'state' => $mode === 'auto' ? null : $state,
            'sync_config' => $syncConfig,
            'settings' => $stored,
        ];
    }

    /**
     * Simpan perubahan mode dan status perintah manual terakhir setelah broker MQTT menerima perintah.
     */
    public static function commit(
        User $actor,
        string $node,
        string $actuator,
        ?bool $state,
        string $mode,
    ): void {
        if ($node === 'node2' && $actuator === 'pump') {
            $storedMode = (string) (Setting::query()->where('key', 'pump_mode')->lockForUpdate()->value('value')
                ?? SettingRules::DEFAULTS['pump_mode']);

            if ($storedMode !== $mode) {
                Setting::updateOrCreate(['key' => 'pump_mode'], ['value' => $mode]);
                ActivityLog::record('settings.updated', $actor, null, ['pump_mode']);
            }
        }

        $manualKey = self::manualStateKey($node, $actuator);
        if ($manualKey === null) {
            return;
        }

        if ($mode === 'manual' && $state !== null) {
            Setting::updateOrCreate(['key' => $manualKey], ['value' => $state ? '1' : '0']);
        } elseif ($mode === 'auto') {
            Setting::query()->where('key', $manualKey)->delete();
        }
    }

    private static function manualStateKey(string $node, string $actuator): ?string
    {
        return match ("{$node}:{$actuator}") {
            'node2:pump' => 'pump_manual_state',
            'node1:fan1' => 'fan1_manual_state',
            default => null,
        };
    }
}
