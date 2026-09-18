<?php

declare(strict_types=1);

// Siapkan direktori storage sementara di /tmp untuk lingkungan serverless Vercel
$storagePath = '/tmp/storage';
foreach ([
    $storagePath . '/framework/views',
    $storagePath . '/framework/cache/data',
    $storagePath . '/framework/sessions',
    $storagePath . '/logs',
    $storagePath . '/app/public',
] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;

// Dukungan database SQLite jika pengguna menggunakan SQLite di Vercel
$connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
if ($connection === 'sqlite') {
    $targetDb = '/tmp/database.sqlite';
    if (! file_exists($targetDb)) {
        $sourceDb = __DIR__ . '/../database/database.sqlite';
        if (file_exists($sourceDb)) {
            copy($sourceDb, $targetDb);
        } else {
            touch($targetDb);
        }
    }
    $_ENV['DB_DATABASE'] = $targetDb;
    $_SERVER['DB_DATABASE'] = $targetDb;
    putenv("DB_DATABASE={$targetDb}");
}

// Teruskan eksekusi ke public/index.php standar Laravel
require __DIR__ . '/../public/index.php';
