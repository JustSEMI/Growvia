<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Resources\MobileDeviceResource;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MobileSessions
{
    /** @return array<string, mixed> */
    public static function page(Request $request): array
    {
        $input = MobileInput::validate($request, ['page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
        $page = (int) ($input['page'] ?? 1);
        $offset = ($page - 1) * 15;
        $user = $request->user();
        $currentId = $user->currentAccessToken()->getKey();
        $mobile = $user->tokens()->where('account_version', $user->auth_version)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($query) => $query->whereJsonContains('abilities', 'mobile:access')->orWhereJsonContains('abilities', '*'));
        if ($expiration = config('sanctum.expiration')) {
            $mobile->where('created_at', '>', now()->subMinutes($expiration));
        }
        $mobileCount = (clone $mobile)->count();
        $supported = config('session.driver') === 'database';
        $web = $supported ? DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp) : null;
        $total = $mobileCount + ($web ? (clone $web)->count() : 0);
        $items = [];
        if ($offset < $mobileCount) {
            $tokens = $mobile->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$currentId])
                ->orderByDesc('last_used_at')->orderByDesc('id')->offset($offset)->limit(15)
                ->get(['id', 'name', 'last_used_at', 'created_at', 'expires_at']);
            foreach ($tokens as $token) {
                $expires = $token->expires_at;
                if ($expiration) {
                    $global = $token->created_at->copy()->addMinutes($expiration);
                    $expires = $expires ? $expires->min($global) : $global;
                }
                $items[] = [
                    'kind' => 'mobile', 'label' => mb_substr(preg_replace('/[\x00-\x1f\x7f]/', '', $token->name), 0, 100),
                    'device' => 'Aplikasi mobile', 'ip' => null, 'current' => $token->getKey() === $currentId,
                    'last_active' => $token->last_used_at?->utc()->toIso8601String(),
                    'expires_at' => $expires?->utc()->toIso8601String(),
                ];
            }
        }
        if ($web && count($items) < 15) {
            $sessions = $web->orderByDesc('last_activity')->orderBy('id')->offset(max(0, $offset - $mobileCount))->limit(15 - count($items))
                ->get(['ip_address', 'user_agent', 'last_activity']);
            foreach ($sessions as $session) {
                $agent = (string) $session->user_agent;
                $items[] = [
                    'kind' => 'web',
                    'label' => match (true) {
                        str_contains($agent, 'Edg/') => 'Microsoft Edge',
                        str_contains($agent, 'OPR/') => 'Opera',
                        str_contains($agent, 'Firefox/'), str_contains($agent, 'FxiOS/') => 'Firefox',
                        str_contains($agent, 'Chrome/'), str_contains($agent, 'CriOS/') => 'Chrome',
                        str_contains($agent, 'Safari/') => 'Safari',
                        default => 'Browser tidak dikenali',
                    },
                    'device' => match (true) {
                        str_contains($agent, 'Android') => 'Android',
                        str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS / iPadOS',
                        str_contains($agent, 'Windows') => 'Windows',
                        str_contains($agent, 'Macintosh') => 'macOS',
                        str_contains($agent, 'Linux') => 'Linux',
                        default => 'Perangkat tidak dikenali',
                    },
                    'ip' => filter_var($session->ip_address, FILTER_VALIDATE_IP) ? $session->ip_address : null,
                    'current' => false,
                    'last_active' => Carbon::createFromTimestamp($session->last_activity, 'UTC')->toIso8601String(),
                    'expires_at' => Carbon::createFromTimestamp($session->last_activity, 'UTC')->addMinutes(config('session.lifetime'))->toIso8601String(),
                ];
            }
        }

        return MobileInput::page(new LengthAwarePaginator(collect($items), $total, 15, $page), MobileDeviceResource::class, $request) + ['web_supported' => $supported];
    }
}
