<?php

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => AuthenticateJwt::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            return response()->json([
                'status_code' => 422,
                'message' => 'Dữ liệu không hợp lệ',
                'data' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpExceptionInterface) {
                return response()->json([
                    'status_code' => $exception->getStatusCode(),
                    'message' => $exception->getMessage() ?: 'Yêu cầu không hợp lệ',
                    'data' => null,
                ], $exception->getStatusCode());
            }

            return response()->json([
                'status_code' => 500,
                'message' => 'Đã xảy ra lỗi hệ thống',
                'data' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        });
    })
    ->create();
