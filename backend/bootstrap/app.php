<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // ValidationException không có getStatusCode — phải bắt trước khi rơi vào 500.
            $status = $e instanceof \Illuminate\Validation\ValidationException
                ? 422
                : (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
            $code = match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => 'VALIDATION_ERROR',
                $e instanceof NotFoundHttpException => 'NOT_FOUND',
                $e instanceof \Illuminate\Auth\AuthenticationException => 'UNAUTHENTICATED',
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException => 'HTTP_ERROR',
                default => 'INTERNAL_ERROR',
            };

            if ($status >= 500) {
                report($e);
            }

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $status >= 500 ? 'Lỗi máy chủ nội bộ.' : $e->getMessage(),
                    'details' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : null,
                    'request_id' => request()->id(),
                ],
            ], $status);
        });
    })
    ->create();

return $application;
