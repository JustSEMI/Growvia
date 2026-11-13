<?php

use App\Console\Commands\BackupTickCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwal backup dibaca dari pengaturan admin (backup_schedule); off = dilewati.
Schedule::command(BackupTickCommand::class)
    ->everyMinute()
    ->withoutOverlapping();
