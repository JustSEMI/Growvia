<?php

use App\Http\Middleware\EnsureAccountSession;
use App\Http\Middleware\EnsureApiTransport;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetInterfaceLocale;
use App\Http\Middleware\ThrottleMobileApiRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $global = $middleware->getGlobalMiddleware();
        array_splice($global, array_search(TrustProxies::class, $global, true) + 1, 0, [EnsureApiTransport::class]);
        $middleware->use($global);
        $middleware->group('throttle:mobile-api', [ThrottleMobileApiRequests::using('mobile-api')]);
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ThrottleMobileApiRequests::class,
        );
        $middleware->api(prepend: [SetInterfaceLocale::class]);
        $middleware->trimStrings(except: ['profile_password', 'sessions_password', 'admin_password']);
        $middleware->web(prepend: [SetInterfaceLocale::class, SecurityHeaders::class], append: [EnsureAccountSession::class]);
        $middleware->appendToPriorityList(
            after: StartSession::class,
            append: EnsureAccountSession::class,
        );
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()->isAdmin() ? route('dashboard') : route('petani.dashboard'));
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                if ($response->getStatusCode() >= 400) {
                    $payload = json_decode($response->getContent(), true);
                    $payload = $response->getStatusCode() >= 500
                        ? ['message' => 'Layanan sementara tidak tersedia. Coba kembali nanti.']
                        : array_intersect_key(is_array($payload) ? $payload : ['message' => Response::$statusTexts[$response->getStatusCode()] ?? 'Request failed'], array_flip(['message', 'errors']));
                    $headers = array_intersect_key($response->headers->all(), array_flip([
                        'retry-after', 'allow', 'www-authenticate', 'x-ratelimit-limit',
                        'x-ratelimit-remaining', 'x-ratelimit-reset',
                    ]));
                    $response = response()->json($payload, $response->getStatusCode(), $headers);
                }
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
            }

            return $response;
        });
        $exceptions->dontFlash(['profile_password', 'sessions_password', 'admin_password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
