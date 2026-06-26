<?php

/*
|--------------------------------------------------------------------------
| Listado de reservas — catálogo declarativo de ramas por licencia/sistema
|--------------------------------------------------------------------------
| Réplica fiel del comportamiento del CI legacy (reserva.php::lista(),
| reserva_model.php::listar(), views/reserva/lista.php). Cada clave de "ramas"
| se resuelve con App\Support\Licencia::flag(). Las listas => booleano de
| pertenencia; los mapas licencia=>valor => valor para la licencia actual.
|
| Referencias CI: index.php:62 (LICENCIA=licencia_base), reserva.php:5729-6199,
| reserva_model.php:66-1269.
*/

return [

    'per_page' => 20,

    // Mapa área (segmento de URL) -> idsistema. Constructor CI reserva.php:19-58.
    'area_sistema' => [
        'corporativo' => 1,
        'receptivo' => 1,
        'mayorista' => 2,
        'nacional' => 7,
        'minorista' => 3,
        'consolidador' => 4,
        'administracion' => 5,
        'admin' => 5,
        'configuracion' => 6,
        // 'all' y desconocidos -> 10 (default).
    ],
    'area_sistema_default' => 10,

    // Tipos de reserva por defecto (checkboxes). reserva.php:5777.
    'tipos_default' => ['MA', 'RE', 'TK', 'ME', 'CO', 'MU', 'AD'],

    // Estados por defecto (checkboxes). reserva.php:5799. Ver status_sin_cerrada.
    'status_default' => ['CO', 'RQ', 'CL'],

    // Iconos/colores por estado (lista.php:57-77 y modelo ~icono/color).
    'estados' => [
        'RQ' => ['nombre' => 'Pendiente',  'icono' => 'pin', 'color' => 'BDC102'],
        'CO' => ['nombre' => 'Confirmada', 'icono' => 'tag', 'color' => '00B22D'],
        'CA' => ['nombre' => 'Cancelada',  'icono' => 'ban', 'color' => 'D84A38'],
        'CL' => ['nombre' => 'Cerrada',    'icono' => 'lock', 'color' => 'FF7F00'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ramas por licencia (fidelidad literal). Resueltas por Licencia::flag().
    |--------------------------------------------------------------------------
    */

    // eliminar_reservas forzado a 0 aunque el usuario tenga permiso 262. reserva.php:5745.
    'eliminar_forzado_off' => ['witwan_rays', 'witwan_newstilo'],

    // Habilita botón "Pagar Online" (pago=1). reserva.php:5763.
    'pago_online_on' => ['witwan_med', 'witwan_lozada', 'witwan_expert'],

    // Status default sin 'CL' para ciertas áreas. reserva.php:5797.
    // Mapa licencia => [áreas afectadas].
    'status_sin_cerrada' => [
        'mundotour_sdg' => ['receptivo', 'mayorista'],
    ],

    // Carga el filtro "Operativo". reserva.php:5882.
    'operativos_on' => ['witwan_tower'],

    // Con area='all', no ejecuta listar() salvo que haya >=1 filtro activo. reserva.php:6011.
    'all_requiere_filtros' => ['mundotour_sdg'],

    // Piso de fecha_alta para usuarios NO internos. reserva_model.php:175,198.
    // Mapa licencia => 'YYYY-MM-DD'.
    'fecha_alta_min' => [
        'witwan_tower' => '2016-10-12',
        'witwan_med' => '2016-10-27',
    ],

    // En consolidador (fk_sistema_id=4) sin autorizar, tildar=0 salvo estas licencias. reserva_model.php:~1115.
    'tildar_consolidador_excepto' => ['witwan_rays'],

    // Muestra columna "N°TC" + codigo_externo bajo el código. lista.php:372,396.
    'codigo_externo_visible' => ['witwan_mitika', 'witwan_intertravel'],

    // Muestra selects Cadena Cliente + Operativo en el form. lista.php:215.
    'cadenacliente_operativo_filtros' => ['witwan_tower', 'witwan_tower_dev'],

    // Oculta bloque "File facturados" y checkbox "solo ocultas". lista.php:235,277.
    'pericia' => ['witwan_tower_pericia'],

    // Badge FACTURADO por count(facturas) en vez de srv. lista.php:399 / reserva_model.php:248.
    'facturado_med' => ['witwan_med'],

    // solofacturado=2: corte de servicio_id. reserva_model.php:270. Mapa licencia => id.
    'mybeds_corte_servicio' => [
        'witwan_mybeds' => 71756,
    ],

    // Label alternativo del botón Pagar Online. lista.php:458. Mapa licencia => lang key.
    'pagar_online_label' => [
        'witwan_expert' => 'admin_pagar_online_expert',
    ],

    // Clase CSS extra en columna Productos (toggle). lista.php:444.
    'productos_toggle' => ['mundotour_sdg'],

    // Problema extra "Sin vencimiento de pago al proveedor". reserva_model.php:~1011.
    'problema_vencimiento_proveedor' => ['mundotour_sdg'],

    // Licencias con escritorio[] poblado (supervisión rel_usuariousuario). user_model.php:42.
    'escritorio_on' => ['mundotour_sdg', 'witwan_rays'],

    // Permisos (sección_id, valor) usados por el listado. reserva.php / reserva_model.php.
    'permisos' => [
        'eliminar_reservas' => ['seccion' => 262, 'valor' => 'eliminar_reservas'],
        'mover_servicio_pago' => ['seccion' => 318, 'valor' => 'mover_servicio_pago'],
        'files_cliente_asignado' => ['seccion' => 255, 'valor' => 'files_cliente_asignado'],
        'files_solo_usuario' => ['seccion' => 286, 'valor' => 'files_solo_usuario'],
    ],

    // Claves de sysconfig que afectan visibilidad de filtros. lista.php:296,306.
    'sysconfig' => [
        'fileauditado' => 'fileauditado',
        'marcar_reprogramado' => 'marcar_reprogramado',
    ],
];
