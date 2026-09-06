<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Botonera / menú (réplica de la de CI)
    |--------------------------------------------------------------------------
    |
    | El árbol del menú vive en la BD central `brain`: tabla `seccion` (agrupada
    | por `seccion_grupo` dentro de cada `fk_sistema_id`) filtrada por
    | `rel_licenciaseccion` según la licencia. La visibilidad por usuario sale de
    | `permisogrupo` (BD del tenant, por rol): una sección se muestra si el rol
    | tiene `acceso`=1 (POW ve todo). Lo arma `App\Services\MenuService`.
    |
    */

    // Nombre visible de cada fk_sistema_id de brain.seccion. Brain no guarda los
    // nombres, así que se configuran acá (tomados de la botonera real de CI).
    'sistemas' => [
        1 => 'Receptivo',
        2 => 'Operador',
        3 => 'Minorista',
        4 => 'Aéreos Fase',
        5 => 'Administración',
        6 => 'Configuración',
    ],

    // Color por sistema (réplica de los inline de CI: tiñe el folder del sistema
    // y los íconos de sus grupos).
    'colores' => [
        1 => '#66CC00',
        2 => '#FF33FF',
        3 => '#00FFFF',
        4 => '#6633CC',
        5 => '#FFFF00',
        6 => '#FF9900',
    ],

    // Orden de los sistemas en la botonera (los no listados van al final).
    'orden_sistemas' => [1, 2, 3, 4, 5, 6],

    // permisogrupo_nombre que decide si una sección aparece en el menú.
    'permiso_acceso' => 'acceso',

    // Secciones ya migradas a /app: mapea la seccion_uri del CI legacy (en
    // brain.seccion) a la nueva ruta Inertia. MenuService::url() la consulta para
    // que el ítem apunte a /app sin tocar el resto del menú. Las claves deben
    // coincidir con el seccion_uri real (sin barra inicial). Ajustar tras revisar
    // brain.seccion del tenant.
    'rutas_migradas' => [
        'reserva/lista/receptivo' => '/app/reservas/receptivo',
        'reserva/lista/corporativo' => '/app/reservas/corporativo',
        'reserva/lista/mayorista' => '/app/reservas/mayorista',
        'reserva/lista/nacional' => '/app/reservas/nacional',
        'reserva/lista/minorista' => '/app/reservas/minorista',
        'reserva/lista/consolidador' => '/app/reservas/consolidador',

        // Administración > Documentos. El seccion_uri en brain es
        // '/administracion/factura3ero' (sección 72); MenuService::url() compara
        // sin la barra inicial. NO se mapea 'administracion/factura3erom': la
        // carga múltiple vive dentro del front nuevo, en /multiple.
        'administracion/factura3ero' => '/app/facturas-proveedor',
        'administracion/factura3ero/subdiariocompra' => '/app/facturas-proveedor/subdiario',

        // Administración > Contabilidad / Caja. Los tres son la misma tabla
        // (`ordenadmin` por `tipo`) y en /app comparten módulo; en brain siguen
        // siendo tres secciones distintas, con sus propios permisos.
        'administracion/asientocontable' => '/app/contabilidad/asientos/contable',
        'administracion/asientocta' => '/app/contabilidad/asientos/cuenta-corriente',
        'administracion/fondos' => '/app/contabilidad/asientos/fondos',

        // Configuración: ABMs config-driven (Web\Abm\*). Los seccion_uri de
        // brain vienen con y sin barra inicial ('configuracion/tag' vs
        // '/configuracion/tipousuario'); MenuService::url() compara sin ella.
        'configuracion/region' => '/app/geo/regiones',
        'configuracion/pais' => '/app/geo/paises',
        'configuracion/ciudad' => '/app/geo/ciudades',
        'configuracion/puntos' => '/app/config/puntos-interes',
        'configuracion/grupopais' => '/app/config/grupos-pais',
        'configuracion/negocios' => '/app/config/negocios',
        'configuracion/proyecto' => '/app/config/proyectos',
        'configuracion/programafidelidad' => '/app/config/programas-fidelidad',
        'configuracion/tipoclavefiscal' => '/app/config/tipos-clave-fiscal',
        'configuracion/banco' => '/app/config/bancos',
        'configuracion/feriados' => '/app/config/feriados',
        'configuracion/aerolinea' => '/app/config/aerolineas',
        'configuracion/tarjetacredito' => '/app/config/tarjetas-credito',
        'configuracion/tag' => '/app/config/tags',
        'configuracion/formasdepago' => '/app/config/formas-pago',
        'configuracion/Centrocosto' => '/app/config/centros-costo',
        'configuracion/cadenacliente' => '/app/config/cadenas-cliente',
        'configuracion/interfases' => '/app/config/interfases',
        'configuracion/filearchivo' => '/app/config/archivos-adjuntos',
        'configuracion/alojamientotipo' => '/app/config/tipos-alojamiento',
        'configuracion/habitaciontipo' => '/app/config/tipos-habitacion',
        'configuracion/facilidad' => '/app/config/facilidades',
        'configuracion/cadenahotelera' => '/app/config/cadenas-hoteleras',
        'configuracion/regimen' => '/app/config/regimenes',
        'configuracion/guia' => '/app/config/guias',

        // Configuración > Usuarios / Proveedores.
        'configuracion/tipousuario' => '/app/config/tipos-usuario',
        'configuracion/usuario' => '/app/config/usuarios',
        'configuracion/proveedor' => '/app/config/proveedores',
        'configuracion/Prestador' => '/app/config/prestadores',

        // Administración > Cuentas.
        'administracion/cuentas/cliente' => '/app/cuentas/cliente',

        // Documentos en modo lectura (las acciones siguen linkeando al legacy).
        'administracion/factura' => '/app/documentos/facturas',
        'administracion/notacredito' => '/app/documentos/notas-credito',
        'administracion/notadebito' => '/app/documentos/notas-debito',
        'administracion/recibo' => '/app/documentos/recibos',
        'administracion/ordenpago' => '/app/documentos/ordenes-pago',
        'administracion/ordenservicio' => '/app/documentos/ordenes-servicio',

        // Reservas > Cotizaciones por área.
        'reserva/cotizaciones/receptivo' => '/app/cotizaciones/receptivo',
        'reserva/cotizaciones/mayorista' => '/app/cotizaciones/mayorista',
        'reserva/cotizaciones/minorista' => '/app/cotizaciones/minorista',
        'reserva/cotizaciones/nacional' => '/app/cotizaciones/nacional',

        // Operaciones por área (Web\Operaciones\*).
        'operaciones/autorizar/lista/receptivo' => '/app/operaciones/autorizar/receptivo',
        'operaciones/autorizar/lista/mayorista' => '/app/operaciones/autorizar/mayorista',
        'operaciones/autorizar/lista/minorista' => '/app/operaciones/autorizar/minorista',
        'operaciones/autorizar/lista/nacional' => '/app/operaciones/autorizar/nacional',
        'operaciones/guardia/lista/receptivo' => '/app/operaciones/guardia/receptivo',
        'operaciones/guardia/lista/mayorista' => '/app/operaciones/guardia/mayorista',
        'operaciones/guardia/lista/nacional' => '/app/operaciones/guardia/nacional',
        'operaciones/trafico/lista/receptivo' => '/app/operaciones/trafico/receptivo',
        'operaciones/trafico/lista/mayorista' => '/app/operaciones/trafico/mayorista',
        'operaciones/trafico/lista/nacional' => '/app/operaciones/trafico/nacional',

        // Administración: monedas, contabilidad, fee y comisión.
        'administracion/moneda' => '/app/admin/monedas',
        'administracion/cambio/ultimas' => '/app/admin/tipo-cambio',
        'administracion/tablaiva' => '/app/admin/tabla-iva',
        'administracion/plancuenta' => '/app/admin/plan-cuentas',
        'administracion/Parametros' => '/app/admin/parametros-contables',
        'administracion/usuariocomision' => '/app/admin/perfiles-comision',
        'administracion/Modelocomision' => '/app/admin/modelos-comision',
        'administracion/Modelofee' => '/app/admin/modelos-fee',

        // Administración > Reportes (config-driven).
        'administracion/ventasnetas' => '/app/admin/reportes/ventas-netas',
        'administracion/reportedeuda' => '/app/admin/reportes/deuda',
        'administracion/productopororigen' => '/app/admin/reportes/productos-origen',
        'administracion/reportes/canjes' => '/app/admin/reportes/canjes',
        'administracion/reportes/r14' => '/app/admin/reportes/gastos-administrativos',
        'administracion/reportes/r12' => '/app/admin/reportes/gastos-bancarios',
        'administracion/reportes/honorarios' => '/app/admin/reportes/honorarios',
        'administracion/reportes/provisiondeuda' => '/app/admin/reportes/provision-deuda',
        'administracion/reportes/pagos' => '/app/admin/reportes/pagos',
        'administracion/reportes/gastos' => '/app/admin/reportes/gastos-area',
        'administracion/reportes/facturasaldo' => '/app/admin/reportes/facturas-impagas',

        // Productos / vigencias / tarifarios / cupos: las pantallas Inertia ya
        // existen (Web\Productos\*). Se dejan SIN mapear a propósito hasta que
        // se prueben con datos reales en el tenant: al descomentar, el menú del
        // CI pasa a abrir /app y los usuarios dejan de ver el legacy. Verificar
        // los seccion_uri exactos en brain.seccion antes de activar.
        // 'productos/hotel/lista/receptivo' => '/app/productos/receptivo/hotel',
        // 'productos/hotel/lista/mayorista' => '/app/productos/mayorista/hotel',
        // 'productos/excursion/lista/receptivo' => '/app/productos/receptivo/excursion',
        // 'productos/circuito/lista/receptivo' => '/app/productos/receptivo/circuito',
        // 'tarifario/lista/receptivo' => '/app/tarifarios/receptivo',
        // 'tarifario/lista/mayorista' => '/app/tarifarios/mayorista',
    ],

    // Cache del menú armado, en segundos (clave por licencia+rol). 0 = sin cache.
    'cache_ttl' => (int) env('MENU_CACHE_TTL', 0),

    // Store de cache del menú (file por defecto: no depende del tenant).
    'cache_store' => env('MENU_CACHE_STORE', 'file'),

];
