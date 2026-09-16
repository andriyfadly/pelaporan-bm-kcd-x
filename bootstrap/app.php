<?php

use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsurePasswordNotExpired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $e) {
            $user = auth()->user();
            $konteks = [
                'user_id' => $user?->id,
                'username' => $user?->username,
                'sekolah_id' => $user?->sekolah_id,
                'route' => request()->route()?->getName(),
                'url' => request()->fullUrl(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ];
            // ponytail: sengaja hanya file log — activity_log ikut mati saat DB down (ayam-telur),
            // dan jejak audit super_admin tetap bersih dari noise infra
            Log::error('db-error: '.$e->getMessage(), $konteks);
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            $pesan = 'Terjadi gangguan database. Coba lagi beberapa saat.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => $pesan], 500);
            }
            if ($request->header('X-Inertia')) {
                return Inertia::render('Error', ['status' => 500])->toResponse($request)->setStatusCode(500);
            }

            return back()->with('error', $pesan);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status < 400 || $status === 500) {
                return null;
            }
            if ($request->header('X-Inertia')) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return response()->view('errors.page', ['status' => $status], $status);
        });
    })->create();
