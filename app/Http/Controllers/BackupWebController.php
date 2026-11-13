<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountMutation;
use App\Support\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BackupWebController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups) {}

    public function index(): View
    {
        $stored = Setting::query()->whereIn('key', ['backup_schedule', 'backup_keep_days'])->pluck('value', 'key')->all();
        $raw = (string) ($stored['backup_schedule'] ?? SettingsController::DEFAULTS['backup_schedule']);

        $parsed = ['mode' => 'off', 'time' => '02:30', 'interval' => 3600];
        if (str_starts_with($raw, 'daily@')) {
            $parsed['mode'] = 'daily';
            $parsed['time'] = substr($raw, 6);
        } elseif (str_starts_with($raw, 'every:')) {
            $parsed['mode'] = 'every';
            $parsed['interval'] = (int) substr($raw, 6);
        }

        return view('backup', [
            'backups' => $this->backups->all(),
            'state' => $this->backups->state(),
            'schedule' => [
                'backup_schedule' => $raw,
                'backup_keep_days' => (string) ($stored['backup_keep_days'] ?? SettingsController::DEFAULTS['backup_keep_days']),
            ],
            'parsed' => $parsed,
            'formats' => DatabaseBackup::FORMATS,
            'user' => auth()->user(),
        ]);
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        AccountMutation::run($request, function (User $actor) use ($request): void {
            abort_unless($actor->isAdmin(), 403);
            $validated = $request->validate([
                'backup_mode' => ['required', Rule::in(['off', 'daily', 'every'])],
                'backup_time' => ['required_if:backup_mode,daily', 'nullable', 'date_format:H:i'],
                'backup_interval' => ['required_if:backup_mode,every', 'nullable', 'integer', 'min:60', 'max:999999'],
                'backup_keep_days' => ['required', 'integer', 'min:1', 'max:90'],
            ]);

            $schedule = match ($validated['backup_mode']) {
                'daily' => 'daily@'.$validated['backup_time'],
                'every' => 'every:'.$validated['backup_interval'],
                default => '',
            };

            $stored = Setting::query()->whereIn('key', ['backup_schedule', 'backup_keep_days'])->pluck('value', 'key')->all();
            $values = [
                'backup_schedule' => $schedule,
                'backup_keep_days' => (string) $validated['backup_keep_days'],
            ];
            $changed = [];
            foreach ($values as $key => $value) {
                if ((string) ($stored[$key] ?? '') !== $value) {
                    $changed[] = $key;
                }
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            if ($changed !== []) {
                ActivityLog::record('settings.updated', $actor, null, $changed);
            }
        });

        return back()->with('status', 'Jadwal backup kesimpen.');
    }

    public function run(Request $request): RedirectResponse
    {
        try {
            $file = AccountMutation::run($request, function (User $actor): array {
                abort_unless($actor->isAdmin(), 403);
                try {
                    $created = $this->backups->run();
                } catch (RuntimeException $exception) {
                    throw new HttpException(500, $exception->getMessage());
                }
                $keepDays = (int) (Setting::query()->where('key', 'backup_keep_days')->value('value') ?: 7);
                $this->backups->prune(max(1, $keepDays));
                ActivityLog::record('backup.created', $actor, null, ['backup_file' => $created['name']]);

                return $created;
            });
        } catch (HttpException $exception) {
            return back()->withErrors([
                'backup' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Backup gagal dibuat.',
            ]);
        }

        return back()->with('status', 'Backup dibuat: '.$file['name']);
    }

    public function download(Request $request, string $name): StreamedResponse
    {
        abort_unless((bool) Auth::user()?->isAdmin(), 403);

        return $this->backups->download($name, (string) $request->query('format', 'sql'));
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        try {
            AccountMutation::run($request, function (User $actor) use ($name): void {
                abort_unless($actor->isAdmin(), 403);
                $this->backups->delete($name);
                ActivityLog::record('backup.deleted', $actor, null, ['backup_file' => $name]);
            });
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 404) {
                return back()->withErrors(['backup' => 'Berkas backup tidak ditemukan.']);
            }

            throw $exception;
        }

        return back()->with('status', 'Backup dihapus.');
    }
}
