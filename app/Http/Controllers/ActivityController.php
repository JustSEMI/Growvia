<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', Rule::in(array_keys(ActivityLog::actionLabels()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ], [
            'q.string' => 'Pencarian nama harus berupa teks.',
            'q.max' => 'Pencarian nama maksimal 100 karakter.',
            'action.string' => 'Pilih jenis aktivitas yang tersedia.',
            'action.in' => 'Pilih jenis aktivitas yang tersedia.',
            'from.date_format' => 'Tanggal mulai harus berformat tahun-bulan-tanggal.',
            'to.date_format' => 'Tanggal akhir harus berformat tahun-bulan-tanggal.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ])->validate();
        $query = ActivityLog::query();

        $search = $filters['q'] ?? '';

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('actor_name', 'like', '%'.$search.'%')
                    ->orWhere('subject_name', 'like', '%'.$search.'%');
            });
        }

        if ($action = $filters['action'] ?? null) {
            $query->where('action', $action);
        }

        $query->betweenDates($filters['from'] ?? null, $filters['to'] ?? null);

        return view('activity', [
            'activities' => $query->latest('created_at')->latest('id')->paginate(15)->appends($filters),
            'filters' => $filters,
            'filtered' => collect($filters)->contains(fn (?string $value): bool => $value !== null && $value !== ''),
            'search' => $filters['q'] ?? '',
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'actionLabels' => ActivityLog::actionLabels(),
            'fieldLabels' => ActivityLog::fieldLabels(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
