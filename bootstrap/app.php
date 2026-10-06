<?php

use App\Http\Middleware\AuthenticateToken;
use App\Support\ProblemDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['oauth' => AuthenticateToken::class]);
        // Laravel sorts known middleware (throttle) ahead of unknown ones; the limiter needs the Principal, so auth goes first.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: AuthenticateToken::class);

        // /oauth/authorize is protected by the one-time `auth_token` Passport stores in the session
        // (it is the anti-forgery token of that step); /login is a JSON-only endpoint with no side effects
        // beyond starting a session. Both must be callable by non-browser clients (curl, mobile, tests).
        $middleware->preventRequestForgery(except: ['oauth/authorize', 'login']);

        // Atomic Lua-based limiter when Redis is the cache (production); the plain cache limiter otherwise.
        if (env('CACHE_STORE') === 'redis') {
            $middleware->throttleWithRedis();
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only app: every error is JSON, always.
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(fn (Throwable $e, Request $request) => ProblemDetails::render($e, (bool) config('app.debug')));
    })->create();
