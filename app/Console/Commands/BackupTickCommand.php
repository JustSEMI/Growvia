<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\SettingsController;
use App\Models\Setting;
use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupTickCommand extends Command
{
    protected $signature = 'backup:tick';

    protected $description = 'Menjalankan backup database bila jadwal pada pengaturan sudah jatuh tempo';

    public function handle(DatabaseBackup $backups): int
    {
        $schedule = (string) (Setting::query()->where('key', 'backup_schedule')->value('value')
            ?: SettingsController::DEFAULTS['backup_schedule']);
        if ($schedule === '') {
            return self::SUCCESS;
        }

        $state = $backups->state();
        $lastRun = $state['last_run'] === null ? null : strtotime($state['last_run']);
        if (! self::isDue($schedule, $lastRun === false ? null : $lastRun)) {
            return self::SUCCESS;
        }

        $keepDays = (int) (Setting::query()->where('key', 'backup_keep_days')->value('value')
            ?: SettingsController::DEFAULTS['backup_keep_days']);

        try {
            $file = $backups->run();
            $backups->prune(max(1, $keepDays));
            $this->info('Backup dibuat: '.$file['name'].' ('.number_format($file['size'] / 1024, 1).' KB)');
        } catch (Throwable $exception) {
            report($exception);
            $backups->writeState([
                'last_run' => gmdate('Y-m-d\TH:i:s\Z'),
                'last_status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 400),
                'last_file' => $state['last_file'],
            ]);
            $this->error('Backup gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Jadwal hanya dari pengaturan: 'daily@HH:MM' (jam UTC) atau 'every:DETIK'
     * (minimum 60 detik). Callback manual dijalankan lewat backup:run.
     */
    public static function isDue(string $schedule, ?int $lastRun): bool
    {
        $now = time();
        if (str_starts_with($schedule, 'daily@')) {
            $target = strtotime('today '.substr($schedule, 6));
            if ($target === false || $target > $now) {
                return false;
            }

            return $lastRun === null || $lastRun < $target;
        }
        if (str_starts_with($schedule, 'every:')) {
            $interval = (int) substr($schedule, 6);

            return $interval >= 60 && ($lastRun === null || $now - $lastRun >= $interval);
        }

        return false;
    }
}
