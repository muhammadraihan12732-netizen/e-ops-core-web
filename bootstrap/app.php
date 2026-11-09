<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua exception yang dilaporkan Laravel ikut tercatat di tabel error_logs (tampil di Monitoring).
        $exceptions->report(function (\Throwable $e) {
            try {
                \App\Models\ErrorLog::create([
                    'message' => mb_substr($e->getMessage() ?: get_class($e), 0, 1000),
                    'context' => $e->getFile() . ':' . $e->getLine(),
                ]);
            } catch (\Throwable $x) {
                // jangan sampai pencatatan error menimbulkan error baru
            }
        });
    })->create();
