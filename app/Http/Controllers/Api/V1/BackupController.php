<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseBackup;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups) {}

    public function index(Request $request): JsonResponse
    {
        MobileInput::validate($request, []);

        return response()->json([
            'backups' => $this->backups->all(),
            'state' => $this->backups->state(),
            'schedule' => [
                'backup_schedule' => (string) (Setting::query()->where('key', 'backup_schedule')->value('value') ?? ''),
                'backup_keep_days' => (string) (Setting::query()->where('key', 'backup_keep_days')->value('value') ?? '7'),
            ],
            'formats' => DatabaseBackup::FORMATS,
        ])->header('Cache-Control', 'no-store');
    }

    public function run(Request $request): JsonResponse
    {
        [$file, $message] = MobileMutation::run($request, function (User $actor) use ($request): array {
            MobileInput::validate($request, []);
            try {
                $file = $this->backups->run();
                $keepDays = (int) (Setting::query()->where('key', 'backup_keep_days')->value('value') ?: 7);
                $this->backups->prune(max(1, $keepDays));
            } catch (RuntimeException $exception) {
                abort(500, $exception->getMessage());
            }
            ActivityLog::record('backup.created', $actor, null, ['backup_file' => $file['name']]);

            return [$file, 'Backup selesai dibuat.'];
        }, admin: true);

        return response()->json(['backup' => $file, 'message' => $message])->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, string $name): StreamedResponse
    {
        MobileInput::validate($request, ['format' => ['nullable', 'string', 'in:sql,sql.gz,tgz']]);

        return $this->backups->download($name, (string) $request->query('format', 'sql'));
    }

    public function destroy(Request $request, string $name): JsonResponse
    {
        $message = MobileMutation::run($request, function (User $actor) use ($request, $name): string {
            MobileInput::validate($request, []);
            $this->backups->delete($name);
            ActivityLog::record('backup.deleted', $actor, null, ['backup_file' => $name]);

            return 'Backup dihapus.';
        }, admin: true);

        return response()->json(['message' => $message])->header('Cache-Control', 'no-store');
    }
}
