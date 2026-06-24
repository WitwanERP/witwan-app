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

    // Cache del menú armado, en segundos (clave por licencia+rol). 0 = sin cache.
    'cache_ttl' => (int) env('MENU_CACHE_TTL', 0),

    // Store de cache del menú (file por defecto: no depende del tenant).
    'cache_store' => env('MENU_CACHE_STORE', 'file'),

];
