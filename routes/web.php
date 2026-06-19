<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
| Smoke test del proxy /app -> Laravel.
| Ruta pública (sin auth/tenant todavía). Imprime las variables que
| identifican el entorno para verificar que el proxy reenvía bien el host
| real del cliente, el esquema https, y que corre en PHP 8.2 (Laravel), no CI.
*/

Route::prefix('app')->group(function () {

    Route::get('/', function (Request $request) {

        // Tenant: sólo si ResolveTenant ya está cableado. Si no, no rompe.
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
            'php'           => PHP_VERSION,                       // debe decir 8.2.x  -> NO es CI
            'app_env'       => app()->environment(),

            // --- lo que prueba que el proxy reenvía bien ---
            'host_resuelto' => $request->getHost(),               // subdominio REAL del cliente (vía X-Forwarded-Host)
            'host_header'   => $request->header('Host'),          // applaravel.witwan.com (vhost backend)
            'xf_host'       => $request->header('X-Forwarded-Host'),
            'scheme'        => $request->getScheme(),             // https (vía X-Forwarded-Proto)
            'is_secure'     => $request->isSecure(),
            'client_ip'     => $request->ip(),                    // IP real del cliente (vía X-Forwarded-For)
            'full_url'      => $request->fullUrl(),
            'path'          => $request->path(),

            // --- tenant (cuando exista el middleware) ---
            'tenant'        => $tenant,
            'db_tenant'     => $dbTenant,

            'server_time'   => now()->toDateTimeString(),
        ]);
    });
});
