<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
            $backups->pruneConfigured();
            $this->info('Backup dibuat: '.$file['name'].' ('.number_format($file['size'] / 1024, 1).' KB)');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
