<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountMutation;
use App\Support\AccountSessions;
use App\Support\SettingRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const DEFAULTS = [
        'mqtt_host' => '',
        'mqtt_port' => '1883',
        'mqtt_user' => '',
        'mqtt_topic' => 'growvia/node',
        'update_interval' => '10',
        'th_suhu_min' => '25',
        'th_suhu_max' => '30',
        'th_hum_min' => '60',
        'th_hum_max' => '80',
        'th_light_min' => '20',
        'th_co2_min' => '800',
        'th_co2_max' => '1200',
        'soil_on' => '30',
        'soil_off' => '60',
        'tank_min' => '20',
        'pump_max_run' => '15',
        'pump_mode' => 'auto',
        'notif_pompa' => '1',
        'notif_cahaya' => '1',
        'notif_tandon' => '1',
        'notif_offline' => '1',
    ];

    public function index(): View
    {
        $stored = Setting::query()->pluck('value', 'key')->all();

        return view('settings', [
            'settings' => array_merge(self::DEFAULTS, $stored),
            'user' => auth()->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = AccountMutation::run($request, function (User $user) use ($request): User {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'profile_password' => [Rule::requiredIf($request->input('email') !== $user->email), 'nullable', 'string', 'current_password:web'],
            ]);
            $actor = clone $user;
            $user->fill(collect($validated)->only(['name', 'email'])->all())->save();
            if ($user->wasChanged(['name', 'email'])) {
                ActivityLog::record($user->wasChanged('email') ? 'account.email_changed' : 'account.profile_updated', $actor, $user, array_keys($user->getChanges()));
            }

            return $user;
        });
        if ($user->wasChanged('auth_version')) {
            AccountSessions::keepCurrent($request, $user);
        } else {
            Auth::guard('web')->setUser($user);
        }

        return back()->with('status', 'Profil kesimpen.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = AccountMutation::run($request, function (User $user) use ($request): User {
            $validated = $request->validate([
                'current_password' => ['required', 'string', 'current_password:web'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
            $user->update(['password' => Hash::make($validated['password'])]);
            ActivityLog::record('account.password_changed', $user, $user);

            return $user;
        });
        AccountSessions::keepCurrent($request, $user);

        return back()->with('status', 'Kata sandi keganti.');
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        AccountMutation::run($request, function (User $actor) use ($request): void {
            abort_unless($actor->isAdmin(), 403);
            $notificationKeys = SettingRules::NOTIFICATION_KEYS;
            $request->validate(array_fill_keys($notificationKeys, ['sometimes', 'boolean']));
            $stored = array_merge(self::DEFAULTS, Setting::query()->lockForUpdate()->pluck('value', 'key')->all());
            $input = array_merge($stored, $request->only(array_keys(self::DEFAULTS)));

            foreach ($notificationKeys as $key) {
                if ($request->input('section') === 'notif' || $request->exists($key)) {
                    $input[$key] = $request->boolean($key) ? '1' : '0';
                }
            }

            $validated = Validator::make($input, SettingRules::rules())->validate();
            $values = $validated;
            $changed = [];
            foreach ($values as $key => $value) {
                if ($value !== $stored[$key] && (string) $value !== (string) $stored[$key]) {
                    $changed[] = $key;
                }
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
            if ($changed !== []) {
                ActivityLog::record('settings.updated', $actor, null, $changed);
            }
        });

        return back()->with('status', 'Pengaturan sistem kesimpen.');
    }
}
