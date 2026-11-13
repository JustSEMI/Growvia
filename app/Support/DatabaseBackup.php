<?php

declare(strict_types=1);

namespace App\Support;

use Phar;
use PharData;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Membuat dan mengelola berkas cadangan database di storage/app/backups.
 *
 * Dump dijalankan lewat mariadb-dump atau mysqldump di mesin yang sama dengan
 * aplikasi; kredensial diambil dari koneksi database aktif (kata sandi lewat
 * variabel lingkungan MYSQL_PWD agar tidak muncul di daftar proses).
 */
final class DatabaseBackup
{
    public const FORMATS = ['sql', 'sql.gz', 'tgz'];

    public function __construct(private readonly int $timeoutSeconds = 600) {}

    public function directory(): string
    {
        $path = storage_path('app/backups');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Daftar berkas cadangan, terbaru dulu.
     *
     * @return list<array{name: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $dir = $this->directory();
        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            $full = $dir.DIRECTORY_SEPARATOR.$entry;
            if (self::isBackupName($entry) && is_file($full)) {
                $files[] = [
                    'name' => $entry,
                    'size' => (int) (filesize($full) ?: 0),
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z', (int) (filemtime($full) ?: time())),
                ];
            }
        }
        usort($files, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * @return array{last_run: ?string, last_status: ?string, last_error: ?string, last_file: ?string}
     */
    public function state(): array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.'.state.json';
        $raw = is_file($path) ? (string) file_get_contents($path) : '';
        $decoded = json_decode($raw, true);

        return [
            'last_run' => is_array($decoded) && isset($decoded['last_run']) && is_string($decoded['last_run']) ? $decoded['last_run'] : null,
            'last_status' => is_array($decoded) && isset($decoded['last_status']) && is_string($decoded['last_status']) ? $decoded['last_status'] : null,
            'last_error' => is_array($decoded) && isset($decoded['last_error']) && is_string($decoded['last_error']) ? $decoded['last_error'] : null,
            'last_file' => is_array($decoded) && isset($decoded['last_file']) && is_string($decoded['last_file']) ? $decoded['last_file'] : null,
        ];
    }

    /**
     * Jalankan dump database ke berkas baru.
     *
     * @return array{name: string, size: int, created_at: string}
     */
    public function run(): array
    {
        $connection = (string) config('database.default');
        $config = (array) config('database.connections.'.$connection, []);
        if (($config['driver'] ?? '') !== 'mysql') {
            throw new RuntimeException('Backup hanya mendukung database MySQL/MariaDB.');
        }

        $binary = self::dumpBinary();
        $name = 'growvia-'.gmdate('Ymd-His').'.sql';
        $dir = $this->directory();
        $final = $dir.DIRECTORY_SEPARATOR.$name;
        $tmp = $dir.DIRECTORY_SEPARATOR.$name.'.part';

        $process = new Process(
            array_filter([
                $binary,
                '--host='.(string) ($config['host'] ?? ''),
                '--port='.(string) ($config['port'] ?? '3306'),
                '--user='.(string) ($config['username'] ?? ''),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--no-tablespaces',
                '--result-file='.$tmp,
                (string) ($config['database'] ?? ''),
            ]),
            null,
            ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
        );
        $process->setTimeout($this->timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            @unlink($tmp);
            $tail = mb_substr(trim($process->getErrorOutput() ?: $exception->getMessage()), 0, 400);

            throw new RuntimeException('Dump database gagal: '.$tail, 0, $exception);
        }

        if (! rename($tmp, $final)) {
            @unlink($tmp);

            throw new RuntimeException('Berkas dump tidak bisa dipindahkan.');
        }

        $this->writeState([
            'last_run' => gmdate('Y-m-d\TH:i:s\Z'),
            'last_status' => 'ok',
            'last_error' => null,
            'last_file' => $name,
        ]);

        return [
            'name' => $name,
            'size' => (int) (filesize($final) ?: 0),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function delete(string $name): void
    {
        self::assertBackupName($name);
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! is_file($path) || ! @unlink($path)) {
            abort(404);
        }
    }

    public function prune(int $keepDays): void
    {
        $cutoff = time() - max(1, $keepDays) * 86400;
        $dir = $this->directory();
        foreach (scandir($dir) ?: [] as $entry) {
            $full = $dir.DIRECTORY_SEPARATOR.$entry;
            if (self::isBackupName($entry) && is_file($full) && (int) (filemtime($full) ?: 0) < $cutoff) {
                @unlink($full);
            }
        }
    }

    public function download(string $name, string $format): StreamedResponse
    {
        self::assertBackupName($name);
        if (! in_array($format, self::FORMATS, true)) {
            abort(400, 'Format tidak dikenal.');
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            abort(404);
        }

        $downloadName = match ($format) {
            'sql.gz' => $name.'.gz',
            'tgz' => preg_replace('/\.sql$/', '.tgz', $name) ?? $name.'.tgz',
            default => $name,
        };
        $contentType = $format === 'sql' ? 'application/sql' : 'application/gzip';

        return response()->streamDownload(function () use ($path, $format): void {
            if ($format === 'sql') {
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    return;
                }
                while (($chunk = fread($handle, 65536)) !== false && $chunk !== '') {
                    echo $chunk;
                }
                fclose($handle);

                return;
            }
            if ($format === 'sql.gz') {
                $handle = fopen($path, 'rb');
                $gzip = gzopen('php://output', 'wb9');
                if ($handle === false || $gzip === false) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }

                    return;
                }
                while (($chunk = fread($handle, 65536)) !== false && $chunk !== '') {
                    gzwrite($gzip, $chunk);
                }
                fclose($handle);
                gzclose($gzip);

                return;
            }
            self::streamTarGz($path);
        }, $downloadName, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function streamTarGz(string $path): void
    {
        $work = tempnam(sys_get_temp_dir(), 'growvia-tar-');
        if ($work === false) {
            return;
        }
        $tar = $work.'.tar';
        rename($work, $tar);
        try {
            $archive = new PharData($tar);
            $archive->addFile($path, basename($path));
            $archive->compress(Phar::GZ);
            $gz = $tar.'.gz';
            $handle = fopen($gz, 'rb');
            if ($handle !== false) {
                while (($chunk = fread($handle, 65536)) !== false && $chunk !== '') {
                    echo $chunk;
                }
                fclose($handle);
            }
        } finally {
            @unlink($tar);
            @unlink($tar.'.gz');
        }
    }

    public static function isBackupName(string $name): bool
    {
        return preg_match('/^growvia-\d{8}-\d{6}\.sql$/', $name) === 1;
    }

    private static function assertBackupName(string $name): void
    {
        if (! self::isBackupName($name)) {
            abort(404);
        }
    }

    private static function dumpBinary(): string
    {
        foreach (['mariadb-dump', 'mysqldump'] as $candidate) {
            $probe = new Process([$candidate, '--version']);
            $probe->setTimeout(15);
            if ($probe->run() === 0) {
                return $candidate;
            }
        }

        throw new RuntimeException('mariadb-dump atau mysqldump tidak ditemukan di server. Pasang paket mariadb-client.');
    }

    /**
     * @param  array{last_run: string, last_status: string, last_error: ?string, last_file: ?string}  $state
     */
    public function writeState(array $state): void
    {
        file_put_contents(
            $this->directory().DIRECTORY_SEPARATOR.'.state.json',
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
