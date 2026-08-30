<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Betrieb hinter Nginx/Apache als Reverse Proxy: Weiterleitungs-Kopfzeilen
         * (X-Forwarded-Proto, -For, -Host) werden ausschließlich von Proxys auf
         * dem lokalen Rechner oder im privaten Netz akzeptiert. Damit werden
         * Protokoll und echte Client-IP korrekt erkannt (Login-Historie,
         * Audit-Trail, Rate-Limiting), ohne dass Dritte aus dem Internet die
         * Kopfzeilen fälschen können: deren Quell-IP liegt außerhalb dieser
         * Bereiche und wird daher nicht als Proxy anerkannt.
         *
         * Bewusst statisch und nicht über env(): bei gecachter Konfiguration
         * (php artisan config:cache) wird die .env nicht geladen.
         */
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'fc00::/7',
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => RequireTwoFactor::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
