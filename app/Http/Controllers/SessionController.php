<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AccountMutation;
use App\Support\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $user = AccountMutation::run($request, function (User $user) use ($request): User {
            $request->validate(['sessions_password' => ['required', 'string', 'current_password:web']]);
            $user->forceFill(['auth_version' => Str::random(40), 'remember_token' => Str::random(60)])->save();
            ActivityLog::record('account.sessions_revoked', $user, $user);

            return $user;
        });
        AccountSessions::keepCurrent($request, $user);

        $panelQuery = $request->user()->isAdmin() && $request->query('panel') === 'petani'
            ? ['panel' => 'petani']
            : [];

        return redirect()->route('sessions.index', $panelQuery)->with('status', 'Sesi di perangkat lain diakhiri. Perangkat ini tetap masuk.');
    }

    public function index(Request $request): View
    {
        $supported = config('session.driver') === 'database';
        $sessions = $supported
            ? DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->getKey())
                ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
                ->orderByDesc('last_activity')->paginate(15, ['id', 'ip_address', 'user_agent', 'last_activity'])
            : null;
        $sessions?->through(function (object $session) use ($request): array {
            $agent = AccountSessions::describeAgent((string) $session->user_agent);

            return [
                'browser' => $agent['browser'],
                'device' => $agent['device'],
                'ip' => $session->ip_address ?: 'Tidak tersedia',
                'current' => hash_equals($request->session()->getId(), $session->id),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->timezone(config('app.timezone'))->format('d M Y, H:i'),
            ];
        });

        // Ikuti panel asal admin (?panel=petani) agar layout tetap konsisten.
        $inPetaniPanel = ! $request->user()->isAdmin() || $request->query('panel') === 'petani';

        return view('sessions', [
            'sessions' => $sessions,
            'supported' => $supported,
            'layout' => $inPetaniPanel ? 'layouts.petani' : 'layouts.admin',
            'back' => $inPetaniPanel ? route('petani.settings') : route('settings.index'),
        ]);
    }
}
