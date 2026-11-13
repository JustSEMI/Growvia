<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\SettingsController;
use App\Models\Setting;
use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Membuat berkas backup database sekarang juga';

    public function handle(DatabaseBackup $backups): int
    {
        try {
            $file = $backups->run();
            $keepDays = (int) (Setting::query()->where('key', 'backup_keep_days')->value('value')
                ?: SettingsController::DEFAULTS['backup_keep_days']);
            $backups->prune(max(1, $keepDays));
            $this->info('Backup dibuat: '.$file['name'].' ('.number_format($file['size'] / 1024, 1).' KB)');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
