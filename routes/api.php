<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssistantController;

// Admin Controllers
use App\Http\Controllers\Admin\BSP\{ReportebspController, ReportebsptktController};
use App\Http\Controllers\Admin\Caja\{ConciliabancoController, ConciliacionController, OrdenadminController, ReciboController};
use App\Http\Controllers\Admin\Clientes\CreditoextraController;
use App\Http\Controllers\Admin\Contabilidad\{
    AsientocontableController,
    CanjeController,
    CentrocostoController,
    CierrearqueoController,
    CierrecajaController,
    CondicionivaController,
    CtaaplicadaController,
    MovimientoController,
    NubeanaliticoController,
    PlancuentumController
};
use App\Http\Controllers\Admin\Documentos\{
    FacturaController,
    FacturaEnvioController,
    FacturaaerolineaController,
    FacturaboletumController,
    FacturaclienteController,
    FacturaproveedorController,
    NotacreditoController,
    NotadebitoController
};
use App\Http\Controllers\Admin\General\{
    CrmbancoController,
    FormapagoController,
    GrupocomsionController,
    ImputacionController,
    ItemgastoController,
    IvaController,
    IvatipoController,
    LotedocumentoController,
    ModelocomisionController,
    ModelofeeController,
    ModoivaventumController,
    PayrollController,
    PrecompraController,
    PreventumController,
    TarjetacreditoController
};
use App\Http\Controllers\Admin\Monedas\{CotizacionController, MonedaController};

// Business Controllers
use App\Http\Controllers\Empresas\Clientes\{
    CadenaclienteController,
    ClienteController,
    ClienteExtraController,
    TagController
};
use App\Http\Controllers\Empresas\Pasajeros\PasajeroController;
use App\Http\Controllers\Empresas\Proveedores\{
    CadenahoteleraController,
    ConvenioController,
    ProveedorController
};

// Geo Controllers
use App\Http\Controllers\Geo\{
    CiudadController,
    CiudadtouricoController,
    CiudadxmlController,
    GrupopaiController,
    PaisController,
    RegionController
};

// Product Controllers
use App\Http\Controllers\Productos\{
    AlojamientoController,
    AlojamientofacilidadController,
    AlojamientohabitacionController,
    AlojamientotipoController,
    AsvController,
    CupoController,
    CupoaereoController,
    CupohistorialController,
    CuposborradoController,
    CupotktController,
    DataoffController,
    DestacadoController,
    DiumController,
    ExcursionController,
    GdController,
    GuiumController,
    HotelcategoriumController,
    InterfaseController,
    InterfasedatumController,
    PkdController,
    PkdgaleriumController,
    PkdinamicoController,
    PkditemController,
    PkdproductoController,
    PnraereoController,
    PnrremarkController,
    PnrremarksconfigController,
    PnrsegmentController,
    ProductoController,
    ProductoExtraController,
    ProductogaleriumController,
    ProductogrupoController,
    RegimanController,
    SoldoutController,
    SubmoduloController,
    TarifaController,
    TarifacategoriumController,
    TarifarioController,
    TarifarioarchivoController,
    TarifariocomisionController
};

// Reservation Controllers
use App\Http\Controllers\Reservas\{
    CtzController,
    FilearchivoController,
    FilecomentarioController,
    FilemailController,
    FilenotificacionController,
    FilestatusController,
    IdentidadfiscalController,
    MailController,
    NegocioController,
    ReservaController,
    ReservaExtraController,
    ReservainController,
    ServicioController,
    ServicioExtraController,
    ServicioNominaController,
    ServicioasociadoController,
    ServiciocontableController,
    ServicioctzController,
    ServiciofacturaController
};

// System Controllers
use App\Http\Controllers\Sistema\{CiSessionController, HistorialController, HistorialfileController, HistorialsqlController};
use App\Http\Controllers\Users\{PermisoController, PermisogrupoController, PersonalAccessTokenController};
use App\Http\Controllers\General\{
    AereoController,
    AerolineaController,
    AeropuertoController,
    AeropuertociudadController,
    AgenteemisorController,
    AutorizacionpnrController,
    CiudadauxiliarController,
    DiccionarioController,
    FeriadoController,
    IdiomaController,
    LoginterfaseController,
    MtUsuarioemailController,
    NoturmtController,
    PasajeroExtraController,
    PluginController,
    ProgramafidelidadController,
    ProyectoController,
    RelClientesistemaController,
    RelClientetagController,
    RelEerrController,
    RelFacturaproveedorocupacionController,
    RelFacturareciboController,
    RelFacturareportebspController,
    RelFilefacturaController,
    RelFilereciboController,
    RelGrupopaispaiController,
    RelGuiaidiomaController,
    RelOcupacionprecompraController,
    RelOcupacionvigenciumController,
    RelOrdenadminocupacionController,
    RelPasajerotagController,
    RelProductoalojamientofacilidadController,
    RelProductobaseController,
    RelProductociudadController,
    RelProveedorsistemaController,
    RelServicioController,
    RelServiciofacturaController,
    RelUsuariomodelocomisionController,
    RelUsuariotipousuarioController,
    RelUsuariousuarioController,
    RelVigenciadiumController,
    RelacionsigavController,
    SessionController,
    SistemaController,
    SolicitudController,
    SyscategoryController,
    SysconfigController,
    SysloginController,
    SysmenuController,
    SysmoduleController,
    SysnotificationController,
    SyspermController,
    SysroleController,
    SysuserController,
    SysuserpermController,
    TipoboletoController,
    TipocambioController,
    TipoclavefiscalController,
    TipofacturaController,
    TiposervicioController,
    TipousuarioController,
    TrasladoController,
    UsuarioController,
    UsuariocomisionController,
    VigenciaalojamientoController,
    VigenciumController,
    XmlinController
};

// Other Controllers
use App\Http\Controllers\Banner\BannerController;
use App\Http\Controllers\Base\BaseController;
use App\Http\Controllers\Eventos\ColaeventoController;
use App\Http\Controllers\Reservas\ReservaServicioController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (without authentication)
Route::post('/asistente', [AssistantController::class, 'interpret']);

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
            Route::apiResource('plan-cuentas', PlancuentumController::class);
        });

        // Document Management (Documentos)
        Route::group(['prefix' => 'documentos'], function () {
            Route::apiResource('facturas', FacturaController::class);
            Route::apiResource('facturas-envio', FacturaEnvioController::class);
            Route::apiResource('facturas-aerolinea', FacturaaerolineaController::class);
            Route::apiResource('facturas-boleta', FacturaboletumController::class);
            Route::apiResource('facturas-cliente', FacturaclienteController::class);
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
        Route::apiResource('cadenas', CadenaclienteController::class);
        Route::apiResource('clientes', ClienteController::class);
        Route::apiResource('clientes-extra', ClienteExtraController::class);
        Route::apiResource('pasajeros', PasajeroController::class);
        Route::apiResource('tags', TagController::class);

        // Client search routes
        Route::get('clientes/search', [ClienteController::class, 'search']);
        Route::get('pasajeros/search', [PasajeroController::class, 'search']);
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
        Route::apiResource('cotizaciones', CtzController::class);
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
