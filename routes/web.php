<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rutas Web (Inertia) — todo bajo /app
|--------------------------------------------------------------------------
| El proxy reenvía /app/* a Laravel SIN quitar el prefijo, así que las rutas
| viven bajo prefix('app'). La API JSON (JWT/Swagger) vive aparte en
| routes/api.php; el frontend Inertia NO la consume por HTTP: ambos comparten
| el mismo core (Services/Models).
*/

Route::prefix('app')->group(function () {

    // Dashboard (maqueta). Las stats reales saldrán de un Service más adelante.
    Route::get('/', fn () => Inertia::render('Dashboard', [
        'stats' => [
            'reservasHoy'        => 0,
            'reservasPendientes' => 0,
            'facturacionMes'     => 0,
            'clientesActivos'    => 0,
        ],
    ]))->name('dashboard');

    // Smoke test del proxy / tenant (se mantiene para diagnóstico).
    Route::get('/_probe', function (Request $request) {
        $tenant = app()->bound('tenant')
            ? ['licencia' => app('tenant')->licencia ?? null, 'pais' => app('tenant')->pais ?? null]
            : 'sin resolver (ResolveTenant aún no corre)';

        try {
            $dbTenant = DB::connection('tenant')->getDatabaseName();
        } catch (\Throwable $e) {
            $dbTenant = 'conexión tenant no configurada todavía';
        }

        return response()->json([
            'ok'            => true,
            'runtime'       => 'laravel',
            'laravel'       => app()->version(),
            'php'           => PHP_VERSION,
            'app_env'       => app()->environment(),
            'host_resuelto' => $request->getHost(),
            'host_header'   => $request->header('Host'),
            'xf_host'       => $request->header('X-Forwarded-Host'),
            'scheme'        => $request->getScheme(),
            'is_secure'     => $request->isSecure(),
            'client_ip'     => $request->ip(),
            'full_url'      => $request->fullUrl(),
            'path'          => $request->path(),
            'tenant'        => $tenant,
            'db_tenant'     => $dbTenant,
            'server_time'   => now()->toDateTimeString(),
        ]);
    });
});
