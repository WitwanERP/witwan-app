<?php

use App\Http\Controllers\Web\ClienteController;
use App\Http\Controllers\Web\PasajeroController;
use App\Services\CiSessionReader;
use App\Services\CiUserResolver;
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
            'reservasHoy' => 0,
            'reservasPendientes' => 0,
            'facturacionMes' => 0,
            'clientesActivos' => 0,
        ],
    ]))->name('dashboard');

    // Clientes
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/chequear-cuit', [ClienteController::class, 'chequearCuit'])->name('clientes.chequear-cuit');
    Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}/edit', [ClienteController::class, 'edit'])->whereNumber('cliente')->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('clientes.update');

    // Pasajeros
    Route::get('/pasajeros', [PasajeroController::class, 'index'])->name('pasajeros.index');
    Route::get('/pasajeros/create', [PasajeroController::class, 'create'])->name('pasajeros.create');
    Route::post('/pasajeros', [PasajeroController::class, 'store'])->name('pasajeros.store');
    Route::get('/pasajeros/{pasajero}/edit', [PasajeroController::class, 'edit'])->whereNumber('pasajero')->name('pasajeros.edit');
    Route::put('/pasajeros/{pasajero}', [PasajeroController::class, 'update'])->whereNumber('pasajero')->name('pasajeros.update');

    // Smoke test del proxy / tenant (se mantiene para diagnóstico).
    Route::get('/_probe', function (Request $request) {
        $tenant = app()->bound('tenant')
            ? [
                'licencia' => app('tenant')->licencia ?? null,
                'pais' => app('tenant')->pais ?? null,
                'base' => app('tenant')->base ?? null,
            ]
            : 'sin resolver';

        // La conexión por defecto (mysql) apunta a la BD del tenant tras ResolveTenant.
        try {
            $dbTenant = DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            $dbTenant = 'error al resolver conexión: '.$e->getMessage();
        }

        // Diagnóstico del pseudo-SSO (solo con CI_SSO_DEBUG=true): muestra en qué
        // etapa queda la resolución del usuario desde la cookie de CI. Usa la cookie
        // real del request, así también revela si EncryptCookies la descartó.
        $pseudoSso = config('ci.debug')
            ? app(CiSessionReader::class)->diagnose($request, app(CiUserResolver::class))
            : 'desactivado (CI_SSO_DEBUG=false)';

        return response()->json([
            'ok' => true,
            'runtime' => 'laravel',
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'app_env' => app()->environment(),
            'host_resuelto' => $request->getHost(),
            'host_header' => $request->header('Host'),
            'xf_host' => $request->header('X-Forwarded-Host'),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),
            'client_ip' => $request->ip(),
            'full_url' => $request->fullUrl(),
            'path' => $request->path(),
            'tenant' => $tenant,
            'db_tenant' => $dbTenant,
            'pseudo_sso' => $pseudoSso,
            'server_time' => now()->toDateTimeString(),
        ]);
    });
});
