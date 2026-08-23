<?php

use App\Http\Controllers\Web\Abm\AerolineaController;
use App\Http\Controllers\Web\Abm\BancoController;
use App\Http\Controllers\Web\Abm\CiudadController;
use App\Http\Controllers\Web\Abm\FeriadoController;
use App\Http\Controllers\Web\Abm\NegocioController;
use App\Http\Controllers\Web\Abm\PaisController;
use App\Http\Controllers\Web\Abm\ProgramaFidelidadController;
use App\Http\Controllers\Web\Abm\ProyectoController;
use App\Http\Controllers\Web\Abm\RegionController;
use App\Http\Controllers\Web\Abm\TipoclavefiscalController;
use App\Http\Controllers\Web\ClienteController;
use App\Http\Controllers\Web\Contabilidad\AsientoController;
use App\Http\Controllers\Web\Documentos\DteChileController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorController as FacturaproveedorWebController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorMultipleController;
use App\Http\Controllers\Web\PasajeroController;
use App\Http\Controllers\Web\Reservas\ReservaListadoController;
use App\Services\CiSessionReader;
use App\Services\CiUserResolver;
use App\Support\Contable\TipoAsiento;
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

    // Reservas — listado custom (réplica del CI legacy reserva/lista). {area} es el
    // sistema (receptivo, mayorista, minorista, …); el resto de filtros van por query-string.
    Route::prefix('reservas')->group(function () {
        $areas = 'corporativo|receptivo|mayorista|nacional|minorista|consolidador|administracion|admin|configuracion|all';

        Route::get('/{area}', [ReservaListadoController::class, 'index'])->where('area', $areas)->name('reservas.index');
        Route::get('/{area}/export', [ReservaListadoController::class, 'exportar'])->where('area', $areas)->name('reservas.export');
        Route::get('/{area}/resumen/{id}', [ReservaListadoController::class, 'resumen'])->where('area', $areas)->whereNumber('id')->name('reservas.resumen');
        Route::get('/{area}/clientes', [ReservaListadoController::class, 'clientesAutocomplete'])->where('area', $areas)->name('reservas.clientes');
        Route::post('/{area}/eliminar', [ReservaListadoController::class, 'eliminar'])->where('area', $areas)->name('reservas.eliminar');
        Route::post('/{area}/agrupar', [ReservaListadoController::class, 'agrupar'])->where('area', $areas)->name('reservas.agrupar');
    });

    /*
    |----------------------------------------------------------------------
    | Administración > Documentos > Facturas de Terceros
    |----------------------------------------------------------------------
    | Reemplaza a /administracion/factura3ero del CI legacy. Las rutas fijas van
    | ANTES de /{id} para que no las capture el comodín numérico.
    |
    | Los endpoints JSON auxiliares viven acá y no en routes/api.php: esa capa
    | está detrás de JWT y el front Inertia no la consume por HTTP.
    |
    | Los nombres van bajo 'documentos.' porque routes/api.php:287 ya registra un
    | apiResource('facturas-proveedor') con los nombres planos: sin prefijo, uno
    | pisaría al otro y route() devolvería la URL equivocada.
    */
    Route::prefix('facturas-proveedor')->group(function () {
        Route::get('/', [FacturaproveedorWebController::class, 'index'])->name('documentos.facturas-proveedor.index');
        Route::get('/export', [FacturaproveedorWebController::class, 'exportar'])->name('documentos.facturas-proveedor.export');
        Route::get('/subdiario', [FacturaproveedorWebController::class, 'subdiario'])->name('documentos.facturas-proveedor.subdiario');
        Route::get('/dte-chile', [DteChileController::class, 'index'])->name('documentos.facturas-proveedor.dte-chile');
        Route::get('/multiple', [FacturaproveedorMultipleController::class, 'create'])->name('documentos.facturas-proveedor.multiple');
        Route::post('/multiple', [FacturaproveedorMultipleController::class, 'store'])->name('documentos.facturas-proveedor.multiple.store');
        Route::get('/create', [FacturaproveedorWebController::class, 'create'])->name('documentos.facturas-proveedor.create');

        // Auxiliares JSON (fetch desde el Vue).
        Route::get('/proveedores', [FacturaproveedorWebController::class, 'proveedores']);
        Route::get('/cotizacion', [FacturaproveedorWebController::class, 'cotizacion']);
        Route::get('/ocupaciones', [FacturaproveedorWebController::class, 'ocupaciones']);
        Route::get('/duplicado', [FacturaproveedorWebController::class, 'duplicado']);
        Route::post('/calcular', [FacturaproveedorWebController::class, 'calcular']);

        Route::post('/', [FacturaproveedorWebController::class, 'store'])->name('documentos.facturas-proveedor.store');
        Route::get('/{id}', [FacturaproveedorWebController::class, 'show'])->whereNumber('id')->name('documentos.facturas-proveedor.show');
        Route::get('/{id}/archivo', [FacturaproveedorWebController::class, 'archivo'])->whereNumber('id')->name('documentos.facturas-proveedor.archivo');
        Route::get('/{id}/edit', [FacturaproveedorWebController::class, 'edit'])->whereNumber('id')->name('documentos.facturas-proveedor.edit');
        Route::put('/{id}', [FacturaproveedorWebController::class, 'update'])->whereNumber('id')->name('documentos.facturas-proveedor.update');
        Route::delete('/{id}', [FacturaproveedorWebController::class, 'destroy'])->whereNumber('id')->name('documentos.facturas-proveedor.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Administración > Contabilidad / Caja > Asientos
    |----------------------------------------------------------------------
    | Un solo módulo para los tres controllers del CI, discriminados por
    | `ordenadmin.tipo`:
    |
    |   contable          (tipo 'A') <- /administracion/asientocontable
    |   cuenta-corriente  (tipo 'C') <- /administracion/asientocta
    |   fondos            (tipo 'M') <- /administracion/fondos
    |
    | Las rutas fijas van ANTES de /{id} para que no las capture el comodín
    | numérico. Los endpoints JSON auxiliares viven acá y no en routes/api.php:
    | esa capa está detrás de JWT y el front Inertia no la consume por HTTP.
    |
    | `anular` es POST y no GET como en el CI: allá era un link, y un prefetch
    | del navegador alcanzaba para anular un asiento.
    */
    Route::prefix('contabilidad/asientos/{tipo}')
        ->where(['tipo' => TipoAsiento::patronDeRuta()])
        ->group(function () {
            Route::get('/', [AsientoController::class, 'index'])->name('asientos.index');
            Route::get('/export', [AsientoController::class, 'exportar'])->name('asientos.export');
            Route::get('/create', [AsientoController::class, 'create'])->name('asientos.create');
            Route::post('/', [AsientoController::class, 'store'])->name('asientos.store');

            // Auxiliares JSON (fetch desde el Vue).
            Route::get('/cuentas', [AsientoController::class, 'cuentas']);
            Route::get('/clientes', [AsientoController::class, 'clientes']);
            Route::get('/proveedores', [AsientoController::class, 'proveedores']);
            Route::get('/files', [AsientoController::class, 'files']);
            Route::get('/cotizacion', [AsientoController::class, 'cotizacion']);

            Route::get('/{id}', [AsientoController::class, 'show'])->whereNumber('id')->name('asientos.show');
            Route::get('/{id}/edit', [AsientoController::class, 'edit'])->whereNumber('id')->name('asientos.edit');
            Route::put('/{id}', [AsientoController::class, 'update'])->whereNumber('id')->name('asientos.update');
            Route::post('/{id}/anular', [AsientoController::class, 'anular'])->whereNumber('id')->name('asientos.anular');
        });

    // ABMs de configuración (config-driven, controllers que extienden Abm\AbmController).
    // Helper local: registra las 6 rutas REST de un ABM bajo un slug dado.
    $abm = function (string $slug, string $controlador) {
        Route::get("/{$slug}", [$controlador, 'index'])->name(str_replace('/', '.', $slug).'.index');
        Route::get("/{$slug}/create", [$controlador, 'create'])->name(str_replace('/', '.', $slug).'.create');
        Route::post("/{$slug}", [$controlador, 'store'])->name(str_replace('/', '.', $slug).'.store');
        Route::get("/{$slug}/{id}/edit", [$controlador, 'edit'])->whereNumber('id')->name(str_replace('/', '.', $slug).'.edit');
        Route::put("/{$slug}/{id}", [$controlador, 'update'])->whereNumber('id')->name(str_replace('/', '.', $slug).'.update');
        Route::delete("/{$slug}/{id}", [$controlador, 'destroy'])->whereNumber('id')->name(str_replace('/', '.', $slug).'.destroy');
    };

    // GEO
    $abm('geo/regiones', RegionController::class);
    $abm('geo/paises', PaisController::class);
    $abm('geo/ciudades', CiudadController::class);

    // Configuración: ABMs sencillos
    $abm('config/negocios', NegocioController::class);
    $abm('config/proyectos', ProyectoController::class);
    $abm('config/programas-fidelidad', ProgramaFidelidadController::class);
    $abm('config/tipos-clave-fiscal', TipoclavefiscalController::class);
    $abm('config/bancos', BancoController::class);
    $abm('config/feriados', FeriadoController::class);
    $abm('config/aerolineas', AerolineaController::class);

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
