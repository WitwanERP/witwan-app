<?php

use App\Http\Controllers\Admin\BSP\ReportebspController;
use App\Http\Controllers\Admin\BSP\ReportebsptktController;
// Controllers
use App\Http\Controllers\Admin\Caja\ConciliabancoController;
use App\Http\Controllers\Admin\Caja\ConciliacionController;
use App\Http\Controllers\Admin\Caja\OrdenadminController;
use App\Http\Controllers\Admin\Caja\ReciboController;
use App\Http\Controllers\Admin\Clientes\CreditoextraController;
// Admin Controllers
use App\Http\Controllers\Admin\Contabilidad\AsientocontableController;
use App\Http\Controllers\Admin\Contabilidad\CanjeController;
use App\Http\Controllers\Admin\Contabilidad\CentrocostoController;
use App\Http\Controllers\Admin\Contabilidad\CierrearqueoController;
use App\Http\Controllers\Admin\Contabilidad\CierrecajaController;
use App\Http\Controllers\Admin\Contabilidad\CondicionivaController;
use App\Http\Controllers\Admin\Contabilidad\CtaaplicadaController;
use App\Http\Controllers\Admin\Contabilidad\MovimientoController;
use App\Http\Controllers\Admin\Contabilidad\NubeanaliticoController;
use App\Http\Controllers\Admin\Contabilidad\PlancuentaController;
use App\Http\Controllers\Admin\Documentos\FacturaaerolineaController;
use App\Http\Controllers\Admin\Documentos\FacturaboletumController;
use App\Http\Controllers\Admin\Documentos\FacturaclienteController;
use App\Http\Controllers\Admin\Documentos\FacturaController;
use App\Http\Controllers\Admin\Documentos\FacturaEnvioController;
use App\Http\Controllers\Admin\Documentos\FacturaproveedorController;
use App\Http\Controllers\Admin\Documentos\NotacreditoController;
use App\Http\Controllers\Admin\Documentos\NotadebitoController;
use App\Http\Controllers\Admin\General\CrmbancoController;
use App\Http\Controllers\Admin\General\FormapagoController;
use App\Http\Controllers\Admin\General\GrupocomsionController;
use App\Http\Controllers\Admin\General\ImputacionController;
use App\Http\Controllers\Admin\General\ItemgastoController;
use App\Http\Controllers\Admin\General\IvaController;
use App\Http\Controllers\Admin\General\IvatipoController;
use App\Http\Controllers\Admin\General\LotedocumentoController;
use App\Http\Controllers\Admin\General\ModelocomisionController;
use App\Http\Controllers\Admin\General\ModelofeeController;
use App\Http\Controllers\Admin\General\ModoivaventumController;
use App\Http\Controllers\Admin\General\PayrollController;
use App\Http\Controllers\Admin\General\PrecompraController;
use App\Http\Controllers\Admin\General\PreventumController;
use App\Http\Controllers\Admin\General\TarjetacreditoController;
use App\Http\Controllers\Admin\Monedas\CotizacionController;
use App\Http\Controllers\Admin\Monedas\MonedaController;
use App\Http\Controllers\Api\AdvancePaymentController;
use App\Http\Controllers\Api\TariffController;
use App\Http\Controllers\Api\TestPricingController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Banner\BannerController;
use App\Http\Controllers\Base\BaseController;
// Business Controllers
use App\Http\Controllers\Empresas\Clientes\CadenaclienteController;
use App\Http\Controllers\Empresas\Clientes\ClienteController;
use App\Http\Controllers\Empresas\Clientes\ClienteExtraController;
use App\Http\Controllers\Empresas\Clientes\TagController;
use App\Http\Controllers\Empresas\Pasajeros\PasajeroController;
use App\Http\Controllers\Empresas\Proveedores\CadenahoteleraController;
use App\Http\Controllers\Empresas\Proveedores\ConvenioController;
use App\Http\Controllers\Empresas\Proveedores\ProveedorController;
// Geo Controllers
use App\Http\Controllers\Eventos\ColaeventoController;
use App\Http\Controllers\General\AereoController;
use App\Http\Controllers\General\AerolineaController;
use App\Http\Controllers\General\AeropuertociudadController;
use App\Http\Controllers\General\AeropuertoController;
use App\Http\Controllers\General\AgenteemisorController;
// Product Controllers
use App\Http\Controllers\General\AutorizacionpnrController;
use App\Http\Controllers\General\CiudadauxiliarController;
use App\Http\Controllers\General\DiccionarioController;
use App\Http\Controllers\General\FeriadoController;
use App\Http\Controllers\General\IdiomaController;
use App\Http\Controllers\General\LoginterfaseController;
use App\Http\Controllers\General\MtUsuarioemailController;
use App\Http\Controllers\General\NoturmtController;
use App\Http\Controllers\General\PasajeroExtraController;
use App\Http\Controllers\General\PluginController;
use App\Http\Controllers\General\ProgramafidelidadController;
use App\Http\Controllers\General\ProyectoController;
use App\Http\Controllers\General\RelacionsigavController;
use App\Http\Controllers\General\RelClientesistemaController;
use App\Http\Controllers\General\RelClientetagController;
use App\Http\Controllers\General\RelEerrController;
use App\Http\Controllers\General\RelFacturaproveedorocupacionController;
use App\Http\Controllers\General\RelFacturareciboController;
use App\Http\Controllers\General\RelFacturareportebspController;
use App\Http\Controllers\General\RelFilefacturaController;
use App\Http\Controllers\General\RelFilereciboController;
use App\Http\Controllers\General\RelGrupopaispaiController;
use App\Http\Controllers\General\RelGuiaidiomaController;
use App\Http\Controllers\General\RelOcupacionprecompraController;
use App\Http\Controllers\General\RelOcupacionvigenciumController;
use App\Http\Controllers\General\RelOrdenadminocupacionController;
use App\Http\Controllers\General\RelPasajerotagController;
use App\Http\Controllers\General\RelProductoalojamientofacilidadController;
use App\Http\Controllers\General\RelProductobaseController;
use App\Http\Controllers\General\RelProductociudadController;
use App\Http\Controllers\General\RelProveedorsistemaController;
use App\Http\Controllers\General\RelServicioController;
use App\Http\Controllers\General\RelServiciofacturaController;
use App\Http\Controllers\General\RelUsuariomodelocomisionController;
use App\Http\Controllers\General\RelUsuariotipousuarioController;
use App\Http\Controllers\General\RelUsuariousuarioController;
use App\Http\Controllers\General\RelVigenciadiumController;
use App\Http\Controllers\General\SessionController;
use App\Http\Controllers\General\SistemaController;
use App\Http\Controllers\General\SolicitudController;
// Reservation Controllers
use App\Http\Controllers\General\SyscategoryController;
use App\Http\Controllers\General\SysconfigController;
use App\Http\Controllers\General\SysloginController;
use App\Http\Controllers\General\SysmenuController;
use App\Http\Controllers\General\SysmoduleController;
use App\Http\Controllers\General\SysnotificationController;
use App\Http\Controllers\General\SyspermController;
use App\Http\Controllers\General\SysroleController;
use App\Http\Controllers\General\SysuserController;
use App\Http\Controllers\General\SysuserpermController;
use App\Http\Controllers\General\TipoboletoController;
use App\Http\Controllers\General\TipocambioController;
use App\Http\Controllers\General\TipoclavefiscalController;
use App\Http\Controllers\General\TipofacturaController;
use App\Http\Controllers\General\TiposervicioController;
use App\Http\Controllers\General\TipousuarioController;
use App\Http\Controllers\General\TrasladoController;
use App\Http\Controllers\General\UsuariocomisionController;
use App\Http\Controllers\General\UsuarioController;
// System Controllers
use App\Http\Controllers\General\VigenciaalojamientoController;
use App\Http\Controllers\General\VigenciumController;
use App\Http\Controllers\General\XmlinController;
use App\Http\Controllers\Geo\CiudadController;
use App\Http\Controllers\Geo\CiudadtouricoController;
use App\Http\Controllers\Geo\CiudadxmlController;
use App\Http\Controllers\Geo\GrupopaiController;
use App\Http\Controllers\Geo\PaisController;
use App\Http\Controllers\Geo\RegionController;
use App\Http\Controllers\Productos\AlojamientoController;
use App\Http\Controllers\Productos\AlojamientofacilidadController;
use App\Http\Controllers\Productos\AlojamientohabitacionController;
use App\Http\Controllers\Productos\AlojamientotipoController;
use App\Http\Controllers\Productos\AsvController;
use App\Http\Controllers\Productos\CupoaereoController;
use App\Http\Controllers\Productos\CupoController;
use App\Http\Controllers\Productos\CupohistorialController;
use App\Http\Controllers\Productos\CuposborradoController;
use App\Http\Controllers\Productos\CupotktController;
use App\Http\Controllers\Productos\DataoffController;
use App\Http\Controllers\Productos\DestacadoController;
use App\Http\Controllers\Productos\DiumController;
use App\Http\Controllers\Productos\ExcursionController;
use App\Http\Controllers\Productos\GdController;
use App\Http\Controllers\Productos\GuiumController;
use App\Http\Controllers\Productos\HotelcategoriumController;
use App\Http\Controllers\Productos\InterfaseController;
use App\Http\Controllers\Productos\InterfasedatumController;
use App\Http\Controllers\Productos\PkdController;
use App\Http\Controllers\Productos\PkdgaleriumController;
use App\Http\Controllers\Productos\PkdinamicoController;
use App\Http\Controllers\Productos\PkditemController;
use App\Http\Controllers\Productos\PkdproductoController;
use App\Http\Controllers\Productos\PnraereoController;
use App\Http\Controllers\Productos\PnrremarkController;
use App\Http\Controllers\Productos\PnrremarksconfigController;
use App\Http\Controllers\Productos\PnrsegmentController;
use App\Http\Controllers\Productos\ProductoController;
use App\Http\Controllers\Productos\ProductoExtraController;
use App\Http\Controllers\Productos\ProductogaleriumController;
use App\Http\Controllers\Productos\ProductogrupoController;
use App\Http\Controllers\Productos\RegimanController;
use App\Http\Controllers\Productos\SoldoutController;
use App\Http\Controllers\Productos\SubmoduloController;
use App\Http\Controllers\Productos\TarifacategoriumController;
use App\Http\Controllers\Productos\TarifaController;
use App\Http\Controllers\Productos\TarifarioarchivoController;
use App\Http\Controllers\Productos\TarifariocomisionController;
use App\Http\Controllers\Productos\TarifarioController;
use App\Http\Controllers\Reservas\CtzController;
use App\Http\Controllers\Reservas\FilearchivoController;
use App\Http\Controllers\Reservas\FilecomentarioController;
use App\Http\Controllers\Reservas\FilemailController;
use App\Http\Controllers\Reservas\FilenotificacionController;
use App\Http\Controllers\Reservas\FilestatusController;
use App\Http\Controllers\Reservas\IdentidadfiscalController;
use App\Http\Controllers\Reservas\MailController;
use App\Http\Controllers\Reservas\NegocioController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Reservas\ReservaExtraController;
use App\Http\Controllers\Reservas\ReservainController;
use App\Http\Controllers\Reservas\ReservaServicioController;
use App\Http\Controllers\Reservas\ServicioasociadoController;
use App\Http\Controllers\Reservas\ServiciocontableController;
use App\Http\Controllers\Reservas\ServicioController;
use App\Http\Controllers\Reservas\ServicioctzController;
use App\Http\Controllers\Reservas\ServicioExtraController;
use App\Http\Controllers\Reservas\ServiciofacturaController;
use App\Http\Controllers\Reservas\ServicioNominaController;
use App\Http\Controllers\Sistema\CiSessionController;
use App\Http\Controllers\Sistema\HistorialController;
use App\Http\Controllers\Sistema\HistorialfileController;
use App\Http\Controllers\Sistema\HistorialsqlController;
use App\Http\Controllers\Users\PermisoController;
// Other Controllers
use App\Http\Controllers\Users\PermisogrupoController;
use App\Http\Controllers\Users\PersonalAccessTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (without authentication)
Route::post('/asistente', [AssistantController::class, 'interpret']);

// Webhook público de Travelcompositor (secreto vía ?token=, header o segmento opcional)
Route::post('/webhooks/travelcompositor/{secret?}', [\App\Http\Controllers\Webhooks\TravelcompositorWebhookController::class, 'handle']);

// Authentication routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('ci.bridge');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt.auth');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth');
    Route::get('/profile', [AuthController::class, 'profile'])->middleware('jwt.auth');
});

// Protected routes (require JWT authentication)
Route::group(['middleware' => 'jwt.auth'], function () {

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'admin'], function () {

        // BSP Management
        Route::group(['prefix' => 'bsp'], function () {
            Route::apiResource('reportes', ReportebspController::class);
            Route::apiResource('reportes-tkt', ReportebsptktController::class);
        });

        // Cash Management (Caja)
        Route::group(['prefix' => 'caja'], function () {
            Route::apiResource('conciliaciones-banco', ConciliabancoController::class);
            Route::apiResource('conciliaciones', ConciliacionController::class);
            Route::apiResource('ordenes-admin', OrdenadminController::class);
            Route::apiResource('recibos', ReciboController::class);
            Route::post('recibos/process', [ReciboController::class, 'process']);
        });

        // Client Management
        Route::group(['prefix' => 'clientes'], function () {
            Route::apiResource('creditos-extra', CreditoextraController::class);
        });

        // Accounting (Contabilidad)
        Route::group(['prefix' => 'contabilidad'], function () {
            Route::apiResource('asientos-contables', AsientocontableController::class);
            Route::apiResource('canjes', CanjeController::class);
            Route::apiResource('centros-costo', CentrocostoController::class);
            Route::apiResource('cierres-arqueo', CierrearqueoController::class);
            Route::apiResource('cierres-caja', CierrecajaController::class);
            Route::apiResource('condiciones-iva', CondicionivaController::class);
            Route::apiResource('cuentas-aplicadas', CtaaplicadaController::class);
            Route::apiResource('movimientos', MovimientoController::class);
            Route::apiResource('nube-analitico', NubeanaliticoController::class);
            Route::apiResource('plan-cuentas', PlancuentaController::class);
        });

        // Document Management (Documentos)
        Route::group(['prefix' => 'documentos'], function () {
            Route::apiResource('facturas', FacturaController::class);
            Route::apiResource('facturas-envio', FacturaEnvioController::class);
            Route::apiResource('facturas-aerolinea', FacturaaerolineaController::class);
            Route::apiResource('facturas-boleta', FacturaboletumController::class);
            Route::apiResource('facturas-cliente', FacturaclienteController::class);
            Route::get('facturas-proveedor/create', [FacturaproveedorController::class, 'create']);
            Route::get('facturas-proveedor/control', [FacturaproveedorController::class, 'control']);
            Route::get('facturas-proveedor/{id}/imprimir', [FacturaproveedorController::class, 'imprimir']);
            Route::apiResource('facturas-proveedor', FacturaproveedorController::class);
            Route::apiResource('notas-credito', NotacreditoController::class);
            Route::apiResource('notas-debito', NotadebitoController::class);
        });

        // General Admin
        Route::group(['prefix' => 'general'], function () {
            Route::apiResource('crm-banco', CrmbancoController::class);
            Route::apiResource('formas-pago', FormapagoController::class);
            Route::apiResource('grupos-comision', GrupocomsionController::class);
            Route::apiResource('imputaciones', ImputacionController::class);
            Route::apiResource('items-gasto', ItemgastoController::class);
            Route::apiResource('iva', IvaController::class);
            Route::apiResource('iva-tipos', IvatipoController::class);
            Route::apiResource('lotes-documento', LotedocumentoController::class);
            Route::apiResource('modelos-comision', ModelocomisionController::class);
            Route::apiResource('modelos-fee', ModelofeeController::class);
            Route::apiResource('modos-iva-venta', ModoivaventumController::class);
            Route::apiResource('payroll', PayrollController::class);
            Route::apiResource('precompras', PrecompraController::class);
            Route::apiResource('preventas', PreventumController::class);
            Route::apiResource('tarjetas-credito', TarjetacreditoController::class);
        });

        // Currency Management (Monedas)
        Route::group(['prefix' => 'monedas'], function () {
            Route::apiResource('cotizaciones', CotizacionController::class);
            Route::apiResource('monedas', MonedaController::class);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | BUSINESS ROUTES
    |--------------------------------------------------------------------------
    */

    // Client Management
    Route::group(['prefix' => 'clientes'], function () {
        // Specific routes must be declared BEFORE apiResource so they don't get
        // matched by the {cliente} show route.
        Route::get('clientes/options', [ClienteController::class, 'options']);
        Route::get('clientes/search', [ClienteController::class, 'search']);
        Route::get('clientes/{clientId}/credit-limit', [ClienteController::class, 'creditLimit']);
        Route::get('clientes/{clientId}/remaining-credit', [ClienteController::class, 'remainingCredit']);
        Route::get('pasajeros/search', [PasajeroController::class, 'search']);

        Route::apiResource('cadenas', CadenaclienteController::class);
        Route::apiResource('clientes', ClienteController::class);
        Route::apiResource('clientes-extra', ClienteExtraController::class);
        Route::apiResource('pasajeros', PasajeroController::class);
        Route::apiResource('tags', TagController::class);
    });

    // Provider Management
    Route::group(['prefix' => 'proveedores'], function () {
        Route::apiResource('cadenas-hoteleras', CadenahoteleraController::class);
        Route::apiResource('convenios', ConvenioController::class);
        Route::apiResource('proveedores', ProveedorController::class);

        // Provider search routes
        Route::get('proveedores/search', [ProveedorController::class, 'search']);
    });

    /*
    |--------------------------------------------------------------------------
    | GEOGRAPHIC ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'geo'], function () {
        Route::apiResource('ciudades', CiudadController::class);
        Route::apiResource('ciudades-tourico', CiudadtouricoController::class);
        Route::apiResource('ciudades-xml', CiudadxmlController::class);
        Route::apiResource('grupos-pais', GrupopaiController::class);
        Route::apiResource('paises', PaisController::class);
        Route::apiResource('regiones', RegionController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | PRODUCT ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'productos'], function () {
        Route::apiResource('alojamientos', AlojamientoController::class);
        Route::apiResource('alojamientos-facilidades', AlojamientofacilidadController::class);
        Route::apiResource('alojamientos-habitaciones', AlojamientohabitacionController::class);
        Route::apiResource('alojamientos-tipos', AlojamientotipoController::class);
        Route::apiResource('asv', AsvController::class);
        Route::apiResource('cupos', CupoController::class);
        Route::apiResource('cupos-aereo', CupoaereoController::class);
        Route::apiResource('cupos-historial', CupohistorialController::class);
        Route::apiResource('cupos-borrados', CuposborradoController::class);
        Route::apiResource('cupos-tkt', CupotktController::class);
        Route::apiResource('data-off', DataoffController::class);
        Route::apiResource('destacados', DestacadoController::class);
        Route::apiResource('dias', DiumController::class);
        Route::apiResource('excursiones', ExcursionController::class);
        Route::apiResource('gds', GdController::class);
        Route::apiResource('guias', GuiumController::class);
        Route::apiResource('hoteles-categorias', HotelcategoriumController::class);
        Route::apiResource('interfaces', InterfaseController::class);
        Route::apiResource('interfaces-data', InterfasedatumController::class);
        Route::apiResource('pkd', PkdController::class);
        Route::apiResource('pkd-galerias', PkdgaleriumController::class);
        Route::apiResource('pkd-dinamicos', PkdinamicoController::class);
        Route::apiResource('pkd-items', PkditemController::class);
        Route::apiResource('pkd-productos', PkdproductoController::class);
        Route::apiResource('pnr-aereos', PnraereoController::class);
        Route::apiResource('pnr-remarks', PnrremarkController::class);
        Route::apiResource('pnr-remarks-config', PnrremarksconfigController::class);
        Route::apiResource('pnr-segments', PnrsegmentController::class);
        Route::apiResource('productos', ProductoController::class);
        Route::apiResource('productos-extra', ProductoExtraController::class);
        Route::apiResource('productos-galerias', ProductogaleriumController::class);
        Route::apiResource('productos-grupos', ProductogrupoController::class);
        Route::apiResource('regimenes', RegimanController::class);
        Route::apiResource('sold-out', SoldoutController::class);
        Route::apiResource('submodulos', SubmoduloController::class);
        Route::apiResource('tarifas', TarifaController::class);
        Route::apiResource('tarifas-categorias', TarifacategoriumController::class);
        Route::apiResource('tarifarios', TarifarioController::class);
        Route::apiResource('tarifarios-archivos', TarifarioarchivoController::class);
        Route::apiResource('tarifarios-comisiones', TarifariocomisionController::class);

        // Product search routes
        Route::get('productos/search', [ProductoController::class, 'search']);
        Route::get('alojamientos/search', [AlojamientoController::class, 'search']);
    });

    /*
    |--------------------------------------------------------------------------
    | RESERVATION ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'reservas'], function () {
        Route::apiResource('cotizaciones', CtzController::class)->names('reservas.cotizaciones');
        Route::apiResource('archivos', FilearchivoController::class);
        Route::apiResource('comentarios', FilecomentarioController::class);
        Route::apiResource('mails', FilemailController::class);
        Route::apiResource('notificaciones', FilenotificacionController::class);
        Route::apiResource('estados', FilestatusController::class);
        Route::apiResource('identidades-fiscales', IdentidadfiscalController::class);
        Route::apiResource('mails-reserva', MailController::class);
        Route::apiResource('negocios', NegocioController::class);
        Route::apiResource('reservas', ReservaController::class);
        Route::apiResource('reservas-extra', ReservaExtraController::class);
        Route::apiResource('reservas-in', ReservainController::class);
        Route::apiResource('servicios', ServicioController::class);
        Route::apiResource('servicios-extra', ServicioExtraController::class);
        Route::apiResource('servicios-nomina', ServicioNominaController::class);
        Route::apiResource('servicios-asociados', ServicioasociadoController::class);
        Route::apiResource('servicios-contables', ServiciocontableController::class);
        Route::apiResource('servicios-ctz', ServicioctzController::class);
        Route::apiResource('servicios-facturas', ServiciofacturaController::class);

        // Reservation search routes
        Route::get('reservas/reservar', [ReservaController::class, 'reservar']);
        Route::get('reservas/search', [ReservaController::class, 'search']);
        Route::get('servicios/search', [ServicioController::class, 'search']);
        Route::get('negocios/search', [NegocioController::class, 'search']);
    });

    Route::group(['prefix' => 'reservas-servicios'], function () {
        Route::post('/', [ReservaServicioController::class, 'store']);
        Route::get('/{id}', [ReservaServicioController::class, 'show']);
        Route::put('/{id}', [ReservaServicioController::class, 'update']);
        Route::patch('/{id}/status', [ReservaServicioController::class, 'cambiarStatus']);
        Route::post('/{id}/recalcular-totales', [ReservaServicioController::class, 'recalcularTotales']);
    });

    /*
    |--------------------------------------------------------------------------
    | SYSTEM ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'sistema'], function () {
        Route::apiResource('sessions', CiSessionController::class);
        Route::apiResource('historial', HistorialController::class);
        Route::apiResource('historial-archivos', HistorialfileController::class);
        Route::apiResource('historial-sql', HistorialsqlController::class);
    });

    // User Management
    Route::group(['prefix' => 'usuarios'], function () {
        Route::apiResource('permisos', PermisoController::class);
        Route::apiResource('grupos-permisos', PermisogrupoController::class);
        Route::apiResource('tokens-acceso', PersonalAccessTokenController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | GENERAL SYSTEM ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'general'], function () {
        Route::apiResource('aereos', AereoController::class);
        Route::apiResource('aerolineas', AerolineaController::class);
        Route::apiResource('aeropuertos', AeropuertoController::class);
        Route::apiResource('aeropuertos-ciudades', AeropuertociudadController::class);
        Route::apiResource('agentes-emisores', AgenteemisorController::class);
        Route::apiResource('autorizaciones-pnr', AutorizacionpnrController::class);
        Route::apiResource('ciudades-auxiliares', CiudadauxiliarController::class);
        Route::apiResource('diccionario', DiccionarioController::class);
        Route::apiResource('feriados', FeriadoController::class);
        Route::apiResource('idiomas', IdiomaController::class);
        Route::apiResource('login-interfaces', LoginterfaseController::class);
        Route::apiResource('usuarios-emails', MtUsuarioemailController::class);
        Route::apiResource('notificaciones-sistema', NoturmtController::class);
        Route::apiResource('pasajeros-extra', PasajeroExtraController::class);
        Route::apiResource('plugins', PluginController::class);
        Route::apiResource('programas-fidelidad', ProgramafidelidadController::class);
        Route::apiResource('proyectos', ProyectoController::class);
        Route::apiResource('sessions-general', SessionController::class);
        Route::apiResource('sistemas', SistemaController::class);
        Route::apiResource('solicitudes', SolicitudController::class);
        Route::apiResource('categorias-sistema', SyscategoryController::class);
        Route::apiResource('configuraciones-sistema', SysconfigController::class);
        Route::apiResource('logins-sistema', SysloginController::class);
        Route::apiResource('menus-sistema', SysmenuController::class);
        Route::apiResource('modulos-sistema', SysmoduleController::class);
        Route::apiResource('notificaciones-sistema', SysnotificationController::class);
        Route::apiResource('permisos-sistema', SyspermController::class);
        Route::apiResource('roles-sistema', SysroleController::class);
        Route::apiResource('usuarios-sistema', SysuserController::class);
        Route::apiResource('usuarios-permisos', SysuserpermController::class);
        Route::apiResource('tipos-boleto', TipoboletoController::class);
        Route::apiResource('tipos-cambio', TipocambioController::class);
        Route::apiResource('tipos-clave-fiscal', TipoclavefiscalController::class);
        Route::apiResource('tipos-factura', TipofacturaController::class);
        Route::apiResource('tipos-servicio', TiposervicioController::class);
        Route::apiResource('tipos-usuario', TipousuarioController::class);
        Route::apiResource('traslados', TrasladoController::class);
        Route::apiResource('usuarios', UsuarioController::class);
        Route::apiResource('usuarios-comisiones', UsuariocomisionController::class);
        Route::apiResource('vigencias-alojamiento', VigenciaalojamientoController::class);
        Route::apiResource('vigencias', VigenciumController::class);
        Route::apiResource('xml-in', XmlinController::class);

        // Relationship routes
        /*Route::apiResource('rel-cliente-sistema', RelClientesistemaController::class);
        Route::apiResource('rel-cliente-tag', RelClientetagController::class);
        Route::apiResource('rel-errores', RelEerrController::class);
        Route::apiResource('rel-factura-proveedor-ocupacion', RelFacturaproveedorocupacionController::class);
        Route::apiResource('rel-factura-recibo', RelFacturareciboController::class);
        Route::apiResource('rel-factura-reporte-bsp', RelFacturareportebspController::class);
        Route::apiResource('rel-file-factura', RelFilefacturaController::class);
        Route::apiResource('rel-file-recibo', RelFilereciboController::class);
        Route::apiResource('rel-grupo-pais', RelGrupopaispaiController::class);
        Route::apiResource('rel-guia-idioma', RelGuiaidiomaController::class);
        Route::apiResource('rel-ocupacion-precompra', RelOcupacionprecompraController::class);
        Route::apiResource('rel-ocupacion-vigencia', RelOcupacionvigenciumController::class);
        Route::apiResource('rel-orden-admin-ocupacion', RelOrdenadminocupacionController::class);
        Route::apiResource('rel-pasajero-tag', RelPasajerotagController::class);
        Route::apiResource('rel-producto-alojamiento-facilidad', RelProductoalojamientofacilidadController::class);
        Route::apiResource('rel-producto-base', RelProductobaseController::class);
        Route::apiResource('rel-producto-ciudad', RelProductociudadController::class);
        Route::apiResource('rel-proveedor-sistema', RelProveedorsistemaController::class);
        Route::apiResource('rel-servicio', RelServicioController::class);
        Route::apiResource('rel-servicio-factura', RelServiciofacturaController::class);
        Route::apiResource('rel-usuario-modelo-comision', RelUsuariomodelocomisionController::class);
        Route::apiResource('rel-usuario-tipo-usuario', RelUsuariotipousuarioController::class);
        Route::apiResource('rel-usuario-usuario', RelUsuariousuarioController::class);
        Route::apiResource('rel-vigencia-dia', RelVigenciadiumController::class);
        Route::apiResource('relacion-sigav', RelacionsigavController::class);*/
    });

    /*
    |--------------------------------------------------------------------------
    | OTHER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::apiResource('banners', BannerController::class);
    Route::apiResource('base', BaseController::class);
    Route::apiResource('eventos', ColaeventoController::class);

    /*
    |--------------------------------------------------------------------------
    | PRICING/TARIFF ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'tariff'], function () {
        Route::post('calculate', [TariffController::class, 'calculate']);
        Route::post('calculate-bulk', [TariffController::class, 'calculateBulk']);
        Route::post('legacy', [TariffController::class, 'legacy']); // Compatibilidad con método obsoleto
    });

    /*
    |--------------------------------------------------------------------------
    | QUOTE ROUTES - Búsqueda + Tarifación Unificada
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'quote'], function () {
        Route::post('search', [App\Http\Controllers\Api\QuoteController::class, 'search']);
        Route::post('search-by-id', [App\Http\Controllers\Api\QuoteController::class, 'searchById']);
        Route::post('calculate', [App\Http\Controllers\Api\QuoteController::class, 'calculate']);
        Route::post('calculate-multiple', [App\Http\Controllers\Api\QuoteController::class, 'calculateMultiple']);
        Route::post('availability', [App\Http\Controllers\Api\QuoteController::class, 'availability']);
    });

    /*
    |--------------------------------------------------------------------------
    | TEST PRICING ROUTES (Temporal)
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'pricing'], function () {
        Route::get('status', [TestPricingController::class, 'status']);
        Route::post('test', [TestPricingController::class, 'test']);
        Route::post('mock', [TestPricingController::class, 'mock']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADVANCE PAYMENT ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'advance-payment'], function () {
        Route::post('create', [AdvancePaymentController::class, 'create']);
    });

    /*
    |--------------------------------------------------------------------------
    | SEARCH ROUTES
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'search'], function () {
        Route::get('global', function (Request $request) {
            // Implementar búsqueda global aquí
            return response()->json(['message' => 'Global search endpoint']);
        });
    });
});
