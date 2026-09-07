<?php

use App\Http\Controllers\Web\Abm\AerolineaController;
use App\Http\Controllers\Web\Abm\AlojamientotipoController;
use App\Http\Controllers\Web\Abm\BancoController;
use App\Http\Controllers\Web\Abm\CadenaclienteController;
use App\Http\Controllers\Web\Abm\CadenahoteleraController;
use App\Http\Controllers\Web\Abm\CentrocostoController;
use App\Http\Controllers\Web\Abm\CierreContableController;
use App\Http\Controllers\Web\Abm\CiudadController;
use App\Http\Controllers\Web\Abm\CotizacionController;
use App\Http\Controllers\Web\Abm\FacilidadController;
use App\Http\Controllers\Web\Abm\FeriadoController;
use App\Http\Controllers\Web\Abm\FilearchivoController;
use App\Http\Controllers\Web\Abm\FormapagoController;
use App\Http\Controllers\Web\Abm\GrupopaisController;
use App\Http\Controllers\Web\Abm\GuiaController;
use App\Http\Controllers\Web\Abm\HabitaciontipoController;
use App\Http\Controllers\Web\Abm\InterfaseController;
use App\Http\Controllers\Web\Abm\ModelocomisionController;
use App\Http\Controllers\Web\Abm\ModelofeeController;
use App\Http\Controllers\Web\Abm\MonedaController;
use App\Http\Controllers\Web\Abm\NegocioController;
use App\Http\Controllers\Web\Abm\PaisController;
use App\Http\Controllers\Web\Abm\PlancuentaController;
use App\Http\Controllers\Web\Abm\PrestadorController;
use App\Http\Controllers\Web\Abm\ProgramaFidelidadController;
use App\Http\Controllers\Web\Abm\ProveedorController;
use App\Http\Controllers\Web\Abm\ProyectoController;
use App\Http\Controllers\Web\Abm\PuntoInteresController;
use App\Http\Controllers\Web\Abm\RegimenController;
use App\Http\Controllers\Web\Abm\RegionController;
use App\Http\Controllers\Web\Abm\TablaIvaController;
use App\Http\Controllers\Web\Abm\TagController;
use App\Http\Controllers\Web\Abm\TarjetacreditoController;
use App\Http\Controllers\Web\Abm\TipoclavefiscalController;
use App\Http\Controllers\Web\Abm\UsuariocomisionController;
use App\Http\Controllers\Web\Admin\ParametrosContablesController;
use App\Http\Controllers\Web\Admin\TipoCambioController;
use App\Http\Controllers\Web\Caja\ArqueoController;
use App\Http\Controllers\Web\Caja\CarteraController;
use App\Http\Controllers\Web\ClienteController;
use App\Http\Controllers\Web\Config\EscritoriosController;
use App\Http\Controllers\Web\Config\SolicitudesController;
use App\Http\Controllers\Web\Config\TipousuarioController;
use App\Http\Controllers\Web\Config\UsuarioController;
use App\Http\Controllers\Web\Contabilidad\AsientoController;
use App\Http\Controllers\Web\Contabilidad\BalanceController;
use App\Http\Controllers\Web\Contabilidad\BalanceOchoController;
use App\Http\Controllers\Web\Contabilidad\IvaVentaController;
use App\Http\Controllers\Web\Contabilidad\LibroComprasClController;
use App\Http\Controllers\Web\Contabilidad\LibroDiarioController;
use App\Http\Controllers\Web\Contabilidad\LibroMayorController;
use App\Http\Controllers\Web\Contabilidad\LibroVentasClController;
use App\Http\Controllers\Web\Cuentas\CuentaCorrienteController;
use App\Http\Controllers\Web\Cuentas\EstadoCuentaController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Documentos\DteChileController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorController as FacturaproveedorWebController;
use App\Http\Controllers\Web\Documentos\FacturaproveedorMultipleController;
use App\Http\Controllers\Web\Documentos\Listados\FacturasListadoController;
use App\Http\Controllers\Web\Documentos\Listados\MovimientosFondosListadoController;
use App\Http\Controllers\Web\Documentos\Listados\NotasCreditoListadoController;
use App\Http\Controllers\Web\Documentos\Listados\NotasDebitoListadoController;
use App\Http\Controllers\Web\Documentos\Listados\OrdenesPagoListadoController;
use App\Http\Controllers\Web\Documentos\Listados\OrdenesServicioListadoController;
use App\Http\Controllers\Web\Documentos\Listados\RecibosListadoController;
use App\Http\Controllers\Web\Operaciones\AutorizarController;
use App\Http\Controllers\Web\Operaciones\CierreGrupoController;
use App\Http\Controllers\Web\Operaciones\GuardiaController;
use App\Http\Controllers\Web\Operaciones\OperacionesController;
use App\Http\Controllers\Web\Operaciones\TraficoController;
use App\Http\Controllers\Web\PasajeroController;
use App\Http\Controllers\Web\Productos\CupoController;
use App\Http\Controllers\Web\Productos\ProductoController;
use App\Http\Controllers\Web\Productos\TarifarioController;
use App\Http\Controllers\Web\Productos\VigenciaController;
use App\Http\Controllers\Web\Proveedores\CanjesListadoController;
use App\Http\Controllers\Web\Proveedores\CreditoProveedorListadoController;
use App\Http\Controllers\Web\Proveedores\PrecomprasListadoController;
use App\Http\Controllers\Web\Reportes\AnaliticoVentasController;
use App\Http\Controllers\Web\Reportes\CanjesReporteController;
use App\Http\Controllers\Web\Reportes\ControlCreditoController;
use App\Http\Controllers\Web\Reportes\DiferenciaCambioController;
use App\Http\Controllers\Web\Reportes\FacturadosController;
use App\Http\Controllers\Web\Reportes\FacturasImpagasController;
use App\Http\Controllers\Web\Reportes\GastosAdministrativosController;
use App\Http\Controllers\Web\Reportes\GastosBancariosController;
use App\Http\Controllers\Web\Reportes\GastosPorAreaController;
use App\Http\Controllers\Web\Reportes\GastosReservaController;
use App\Http\Controllers\Web\Reportes\HonorariosController;
use App\Http\Controllers\Web\Reportes\OpNacionalesController;
use App\Http\Controllers\Web\Reportes\PagosProveedoresController;
use App\Http\Controllers\Web\Reportes\PendientesFacturaController;
use App\Http\Controllers\Web\Reportes\ProductosPorOrigenController;
use App\Http\Controllers\Web\Reportes\ProvisionDeudaController;
use App\Http\Controllers\Web\Reportes\ReporteDeudaController;
use App\Http\Controllers\Web\Reportes\ReservasAFacturarController;
use App\Http\Controllers\Web\Reportes\ServiciosSinFacturaController;
use App\Http\Controllers\Web\Reportes\VentasNetasController;
use App\Http\Controllers\Web\Reservas\BuscarPaxController;
use App\Http\Controllers\Web\Reservas\CotizacionesListadoController;
use App\Http\Controllers\Web\Reservas\NuevaReservaController;
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

    // Inicio (réplica de dashboard.php): widgets por permiso + gráficos por JSON.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/reservas', [DashboardController::class, 'reservas'])->name('dashboard.reservas');
    Route::get('/dashboard/cobranzas', [DashboardController::class, 'cobranzas'])->name('dashboard.cobranzas');

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
        Route::get('/{area}/nueva', [NuevaReservaController::class, 'create'])->where('area', $areas)->name('reservas.nueva');
        Route::post('/{area}/nueva', [NuevaReservaController::class, 'store'])->where('area', $areas)->name('reservas.nueva.store');
        Route::post('/{area}/nueva/validar', [NuevaReservaController::class, 'validar'])->where('area', $areas)->name('reservas.nueva.validar');
        Route::post('/{area}/nueva/cotizar', [NuevaReservaController::class, 'cotizar'])->where('area', $areas)->name('reservas.nueva.cotizar');
        Route::get('/{area}/nueva/clientes', [NuevaReservaController::class, 'clientes'])->where('area', $areas)->name('reservas.nueva.clientes');
        Route::get('/{area}/nueva/cliente/{id}', [NuevaReservaController::class, 'cliente'])->where('area', $areas)->whereNumber('id')->name('reservas.nueva.cliente');
        Route::get('/{area}/nueva/proveedores', [NuevaReservaController::class, 'proveedores'])->where('area', $areas)->name('reservas.nueva.proveedores');
        Route::get('/{area}/nueva/ciudades', [NuevaReservaController::class, 'ciudades'])->where('area', $areas)->name('reservas.nueva.ciudades');
        Route::get('/{area}/nueva/productos', [NuevaReservaController::class, 'productos'])->where('area', $areas)->name('reservas.nueva.productos');
        Route::post('/{area}/nueva/buscar', [NuevaReservaController::class, 'buscar'])->where('area', $areas)->name('reservas.nueva.buscar');
        Route::post('/{area}/nueva/ofertas', [NuevaReservaController::class, 'ofertas'])->where('area', $areas)->name('reservas.nueva.ofertas');
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

    /*
    |----------------------------------------------------------------------
    | Productos, vigencias/tarifas, tarifarios y cupos
    |----------------------------------------------------------------------
    | Reemplaza a /productos/{tipo}/{accion}/{sistema}, /vigencia/*,
    | /tarifario/* y /producto/vercupo del CI (docs/PRODUCTOS_VIGENCIAS_TARIFAS.md).
    | {sistema} y {tipo} son los slugs del legacy (receptivo/hotel...) y se
    | validan contra config/productos.php. Las rutas fijas van ANTES de /{id}.
    */
    $sistemas = implode('|', array_keys((array) config('productos.sistemas')));
    $tipos = implode('|', array_column((array) config('productos.tipos'), 'slug'));

    // Vigencias y cupos cuelgan del id de producto (el tipo/sistema sale de la fila).
    Route::prefix('productos/{producto}')->whereNumber('producto')->group(function () {
        Route::get('/vigencias', [VigenciaController::class, 'index'])->name('vigencias.index');
        Route::get('/vigencias/create', [VigenciaController::class, 'create'])->name('vigencias.create');
        Route::post('/vigencias', [VigenciaController::class, 'store'])->name('vigencias.store');
        Route::post('/vigencias/preview-venta', [VigenciaController::class, 'previewVenta'])->name('vigencias.preview-venta');
        Route::post('/cotizar', [VigenciaController::class, 'cotizar'])->name('productos.cotizar');
        Route::get('/vigencias/{vigencia}/edit', [VigenciaController::class, 'edit'])->whereNumber('vigencia')->name('vigencias.edit');
        Route::put('/vigencias/{vigencia}', [VigenciaController::class, 'update'])->whereNumber('vigencia')->name('vigencias.update');
        Route::post('/vigencias/{vigencia}/clonar', [VigenciaController::class, 'clonar'])->whereNumber('vigencia')->name('vigencias.clonar');
        Route::delete('/vigencias/{vigencia}', [VigenciaController::class, 'destroy'])->whereNumber('vigencia')->name('vigencias.destroy');

        Route::prefix('cupos/{categoria}')->whereNumber('categoria')->group(function () {
            Route::get('/', [CupoController::class, 'calendario'])->name('cupos.calendario');
            Route::get('/mes', [CupoController::class, 'mes'])->name('cupos.mes');
            Route::post('/cupo', [CupoController::class, 'storeCupo'])->name('cupos.cupo.store');
            Route::post('/soldout', [CupoController::class, 'storeSoldout'])->name('cupos.soldout.store');
            Route::post('/bloqueo', [CupoController::class, 'bloqueo'])->name('cupos.bloqueo');
            Route::post('/cupo/eliminar', [CupoController::class, 'destroyCupos'])->name('cupos.cupo.eliminar');
            Route::post('/soldout/eliminar', [CupoController::class, 'destroySoldouts'])->name('cupos.soldout.eliminar');
        });
    });

    Route::prefix('productos/{sistema}/{tipo}')->where(['sistema' => $sistemas, 'tipo' => $tipos])->group(function () {
        Route::get('/', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/create', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/{id}/edit', [ProductoController::class, 'edit'])->whereNumber('id')->name('productos.edit');
        Route::put('/{id}', [ProductoController::class, 'update'])->whereNumber('id')->name('productos.update');
        Route::post('/{id}/clonar', [ProductoController::class, 'clonar'])->whereNumber('id')->name('productos.clonar');
        Route::delete('/{id}', [ProductoController::class, 'destroy'])->whereNumber('id')->name('productos.destroy');
        Route::get('/{id}/habitaciones', [ProductoController::class, 'habitaciones'])->whereNumber('id')->name('productos.habitaciones');
    });

    Route::prefix('tarifarios/{sistema}')->where(['sistema' => $sistemas])->group(function () {
        Route::get('/', [TarifarioController::class, 'index'])->name('tarifarios.index');
        Route::get('/create', [TarifarioController::class, 'create'])->name('tarifarios.create');
        Route::post('/', [TarifarioController::class, 'store'])->name('tarifarios.store');
        Route::get('/{id}/edit', [TarifarioController::class, 'edit'])->whereNumber('id')->name('tarifarios.edit');
        Route::put('/{id}', [TarifarioController::class, 'update'])->whereNumber('id')->name('tarifarios.update');
        Route::delete('/{id}', [TarifarioController::class, 'destroy'])->whereNumber('id')->name('tarifarios.destroy');
    });

    // ABMs de configuración (config-driven, controllers que extienden Abm\AbmController).
    // Helper local: registra las 6 rutas REST de un ABM bajo un slug dado.
    // $pkNumerica=false para tablas cuya PK es un código (moneda_id 'ARS').
    $abm = function (string $slug, string $controlador, bool $pkNumerica = true) {
        $id = $pkNumerica ? '[0-9]+' : '[A-Za-z0-9_-]+';
        Route::get("/{$slug}", [$controlador, 'index'])->name(str_replace('/', '.', $slug).'.index');
        Route::get("/{$slug}/create", [$controlador, 'create'])->name(str_replace('/', '.', $slug).'.create');
        Route::post("/{$slug}", [$controlador, 'store'])->name(str_replace('/', '.', $slug).'.store');
        Route::get("/{$slug}/{id}/edit", [$controlador, 'edit'])->where('id', $id)->name(str_replace('/', '.', $slug).'.edit');
        Route::put("/{$slug}/{id}", [$controlador, 'update'])->where('id', $id)->name(str_replace('/', '.', $slug).'.update');
        Route::delete("/{$slug}/{id}", [$controlador, 'destroy'])->where('id', $id)->name(str_replace('/', '.', $slug).'.destroy');
    };

    // GEO
    $abm('geo/regiones', RegionController::class);
    $abm('geo/paises', PaisController::class);
    $abm('geo/ciudades', CiudadController::class);
    $abm('config/puntos-interes', PuntoInteresController::class);
    $abm('config/grupos-pais', GrupopaisController::class);

    // Configuración: ABMs sencillos
    $abm('config/negocios', NegocioController::class);
    $abm('config/proyectos', ProyectoController::class);
    $abm('config/programas-fidelidad', ProgramaFidelidadController::class);
    $abm('config/tipos-clave-fiscal', TipoclavefiscalController::class);
    $abm('config/bancos', BancoController::class);
    $abm('config/feriados', FeriadoController::class);
    $abm('config/aerolineas', AerolineaController::class);
    $abm('config/tarjetas-credito', TarjetacreditoController::class);
    $abm('config/tags', TagController::class);
    $abm('config/formas-pago', FormapagoController::class);
    $abm('config/centros-costo', CentrocostoController::class);
    $abm('config/cadenas-cliente', CadenaclienteController::class);
    $abm('config/interfases', InterfaseController::class);
    $abm('config/archivos-adjuntos', FilearchivoController::class);

    // Administración: monedas, contabilidad, fee y comisión
    $abm('admin/monedas', MonedaController::class, false);
    $abm('admin/cotizaciones', CotizacionController::class);
    $abm('admin/tabla-iva', TablaIvaController::class);
    $abm('admin/plan-cuentas', PlancuentaController::class);
    $abm('admin/perfiles-comision', UsuariocomisionController::class);
    $abm('admin/modelos-comision', ModelocomisionController::class);
    $abm('admin/modelos-fee', ModelofeeController::class);
    Route::get('/admin/tipo-cambio', [TipoCambioController::class, 'index'])->name('admin.tipo-cambio');
    Route::post('/admin/tipo-cambio', [TipoCambioController::class, 'guardar'])->name('admin.tipo-cambio.guardar');
    Route::get('/admin/parametros-contables', [ParametrosContablesController::class, 'index'])->name('admin.parametros-contables');
    Route::post('/admin/parametros-contables', [ParametrosContablesController::class, 'guardar'])->name('admin.parametros-contables.guardar');

    // Reportes config-driven (Web\Reportes\ReporteController): listado + export CSV.
    $reporte = function (string $slug, string $controlador) {
        $nombre = str_replace('/', '.', $slug);
        Route::get("/{$slug}", [$controlador, 'index'])->name($nombre);
        Route::get("/{$slug}/export", [$controlador, 'exportar'])->name($nombre.'.export');
    };
    $reporte('admin/reportes/ventas-netas', VentasNetasController::class);
    $reporte('admin/reportes/deuda', ReporteDeudaController::class);
    $reporte('admin/reportes/productos-origen', ProductosPorOrigenController::class);
    $reporte('admin/reportes/canjes', CanjesReporteController::class);
    $reporte('admin/reportes/gastos-administrativos', GastosAdministrativosController::class);
    $reporte('admin/reportes/gastos-bancarios', GastosBancariosController::class);
    $reporte('admin/reportes/honorarios', HonorariosController::class);
    $reporte('admin/reportes/provision-deuda', ProvisionDeudaController::class);
    $reporte('admin/reportes/pagos', PagosProveedoresController::class);
    $reporte('admin/reportes/gastos-area', GastosPorAreaController::class);
    $reporte('admin/reportes/facturas-impagas', FacturasImpagasController::class);
    $reporte('admin/reportes/servicios-sin-factura', ServiciosSinFacturaController::class);
    $reporte('admin/reportes/diferencia-cambio', DiferenciaCambioController::class);
    $reporte('admin/reportes/analitico-ventas', AnaliticoVentasController::class);
    $reporte('admin/reportes/gastos-reserva', GastosReservaController::class);
    $reporte('admin/reportes/op-nacionales', OpNacionalesController::class);
    $reporte('reservas-buscar', BuscarPaxController::class);
    $reporte('admin/reportes/control-credito', ControlCreditoController::class);
    Route::post('/admin/reportes/control-credito/{cliente}/extra', [ControlCreditoController::class, 'extra'])->whereNumber('cliente');
    $reporte('admin/reportes/pendientes-factura', PendientesFacturaController::class);
    $reporte('admin/reportes/facturados', FacturadosController::class);
    $reporte('admin/reportes/reservas-a-facturar', ReservasAFacturarController::class);
    $reporte('admin/reportes/facturacion-acumulada', ReservasAFacturarController::class);

    // Administración > Contabilidad > Libros.
    $reporte('contabilidad/libro-diario', LibroDiarioController::class);
    $reporte('contabilidad/libro-mayor', LibroMayorController::class);
    $reporte('contabilidad/balance', BalanceController::class);
    $reporte('contabilidad/libro-ventas', LibroVentasClController::class);
    $reporte('contabilidad/libro-compras', LibroComprasClController::class);
    $reporte('contabilidad/iva-venta', IvaVentaController::class);
    $reporte('contabilidad/balance-8', BalanceOchoController::class);
    $reporte('cuentas/estado', EstadoCuentaController::class);

    // Administración > Caja (listados; los movimientos transaccionales siguen en el legacy).
    $reporte('caja/cartera', CarteraController::class);
    Route::post('/caja/cartera/{movimiento}/utilizar', [CarteraController::class, 'utilizar'])->whereNumber('movimiento');
    $reporte('caja/arqueo', ArqueoController::class);
    $reporte('config/solicitudes', SolicitudesController::class);

    // Configuración > Usuarios > Escritorios (sólo POW).
    Route::get('/config/escritorios', [EscritoriosController::class, 'index'])->name('config.escritorios');
    Route::post('/config/escritorios/agregar', [EscritoriosController::class, 'agregar'])->name('config.escritorios.agregar');
    Route::post('/config/escritorios/quitar', [EscritoriosController::class, 'quitar'])->name('config.escritorios.quitar');
    $reporte('operaciones/cierre-grupo', CierreGrupoController::class);

    // Administración > Cuentas.
    Route::get('/cuentas/cliente', [CuentaCorrienteController::class, 'cliente'])->name('cuentas.cliente');
    Route::get('/cuentas/proveedor', [CuentaCorrienteController::class, 'proveedor'])->name('cuentas.proveedor');

    // Documentos en modo lectura (acciones al legacy) y cotizaciones por área.
    $reporte('documentos/facturas', FacturasListadoController::class);
    $reporte('documentos/notas-credito', NotasCreditoListadoController::class);
    $reporte('documentos/notas-debito', NotasDebitoListadoController::class);
    $reporte('documentos/recibos', RecibosListadoController::class);
    $reporte('documentos/ordenes-pago', OrdenesPagoListadoController::class);
    $reporte('documentos/ordenes-servicio', OrdenesServicioListadoController::class);
    $reporte('documentos/movimientos-fondos', MovimientosFondosListadoController::class);

    // Administración > Proveedores (canjes, pre-compras, crédito).
    $reporte('proveedores/canjes', CanjesListadoController::class);
    $reporte('proveedores/precompras', PrecomprasListadoController::class);
    $reporte('proveedores/creditos', CreditoProveedorListadoController::class);
    Route::get('/cotizaciones/{area}', [CotizacionesListadoController::class, 'index'])->where('area', OperacionesController::patronDeArea().'|all')->name('cotizaciones.index');
    Route::get('/cotizaciones/{area}/export', [CotizacionesListadoController::class, 'exportar'])->where('area', OperacionesController::patronDeArea().'|all')->name('cotizaciones.export');

    // Operaciones por área (receptivo, mayorista, minorista, nacional, corporativo, consolidador).
    Route::prefix('operaciones')->where(['area' => OperacionesController::patronDeArea()])->group(function () {
        Route::get('/autorizar/{area}', [AutorizarController::class, 'lista'])->name('operaciones.autorizar');
        Route::get('/autorizar/{area}/{id}', [AutorizarController::class, 'file'])->whereNumber('id')->name('operaciones.autorizar.file');
        Route::post('/autorizar/{area}/{id}/actualizar', [AutorizarController::class, 'actualizar'])->whereNumber('id')->name('operaciones.autorizar.actualizar');
        Route::post('/autorizar/{area}/{id}/autorizar', [AutorizarController::class, 'autorizar'])->whereNumber('id')->name('operaciones.autorizar.autorizar');

        Route::get('/guardia/{area}', [GuardiaController::class, 'lista'])->name('operaciones.guardia');
        Route::post('/guardia/{area}/reporte', [GuardiaController::class, 'reporte'])->name('operaciones.guardia.reporte');

        Route::get('/trafico/{area}', [TraficoController::class, 'lista'])->name('operaciones.trafico');
        Route::get('/trafico/{area}/productos/{proveedor}', [TraficoController::class, 'productos'])->whereNumber('proveedor')->name('operaciones.trafico.productos');
        Route::post('/trafico/{area}/guardar', [TraficoController::class, 'guardar'])->name('operaciones.trafico.guardar');
    });

    // Configuración > Usuarios / Proveedores
    $abm('config/tipos-usuario', TipousuarioController::class, false);
    $abm('config/usuarios', UsuarioController::class);
    $abm('contabilidad/cierres', CierreContableController::class);
    Route::post('/config/usuarios/{id}/apikey', [UsuarioController::class, 'apikey'])->whereNumber('id')->name('config.usuarios.apikey');
    $abm('config/proveedores', ProveedorController::class);
    $abm('config/prestadores', PrestadorController::class);

    // Configuración > Productos
    $abm('config/tipos-alojamiento', AlojamientotipoController::class);
    $abm('config/tipos-habitacion', HabitaciontipoController::class);
    $abm('config/facilidades', FacilidadController::class);
    $abm('config/cadenas-hoteleras', CadenahoteleraController::class);
    $abm('config/regimenes', RegimenController::class);
    $abm('config/guias', GuiaController::class);

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
