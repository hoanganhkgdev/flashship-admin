<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',        // API mặc định
            __DIR__.'/../routes/api_admin.php',  // ✅ thêm file riêng cho app admin
        ],
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php', // ⛔ Tắt - không dùng Pusher/WebSocket
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Nhóm middleware cho API
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            // ✅ check trạng thái user
            \App\Http\Middleware\CheckUserStatus::class,
        ]);

        // ✅ alias middleware để dùng riêng cho route
        $middleware->alias([
            'check.status' => \App\Http\Middleware\CheckUserStatus::class,
            'check.debt'   => \App\Http\Middleware\CheckDriverDebt::class,
            'city.scope' => \App\Http\Middleware\CityScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
