<?php

/*
|--------------------------------------------------------------------------
| Asientos de administración (tabla `ordenadmin`)
|--------------------------------------------------------------------------
|
| Los tres módulos que el CI tiene separados —asiento contable, asiento en
| cuenta corriente y movimiento de fondos— son la MISMA tabla discriminada por
| `ordenadmin.tipo`, con listados y formularios casi calcados
| (administracion/asientocontable.php, asientocta.php y fondos.php). Acá se
| declaran las diferencias reales entre los tres y el resto del módulo es uno
| solo.
|
| Los códigos de `tipo` no se pueden tocar: los comparten reportes, libros y
| la contabilidad del legacy.
|
*/

return [

    'per_page' => 50,

    /*
    | Un asiento se graba con tantas filas de `movimiento` como líneas tenga la
    | grilla. El legacy recorre hasta 500 (contable/cta cte) o 15 (fondos)
    | índices del POST; acá el front manda un array y este es el techo.
    */
    'max_lineas' => 500,

    'tipos' => [

        'contable' => [
            'codigo' => 'A',
            'titulo' => 'Asiento Contable',
            'singular' => 'asiento contable',
            // Sección del CI contra la que se resuelven los permisos.
            'seccion' => 'administracion/asientocontable',
            'legacy' => '/administracion/asientocontable',
            'grilla' => 'debe-haber',

            // Columnas de la grilla, además de cuenta / leyenda / debe / haber.
            'imputa' => [],

            /*
            | `movimiento.tipo` de las líneas del DEBE. Es la única diferencia
            | de fondo entre el asiento contable y el de cuenta corriente:
            | asientocontable.php:157 deja el valor inicial 'I', mientras que
            | asientocta.php:186 lo pisa con 'D'. Las líneas del HABER son 'E'
            | en los dos.
            */
            'tipo_debe' => 'I',

            // Checkbox "tocar arqueo" (asientocontable.php:216-218): sin él, el
            // movimiento se graba con statusmovimiento='AR'.
            'arqueo' => true,

            'afecta_cobranza' => false,
            'proyecto' => false,

            // Al anular: sólo marca (statusdocumento=0). Ver 'borra_movimientos'.
            'borra_movimientos' => false,
            'libera_utilizado' => false,
        ],

        'cuenta-corriente' => [
            'codigo' => 'C',
            'titulo' => 'Asiento en Cuenta Corriente',
            'singular' => 'asiento en cuenta corriente',
            'seccion' => 'administracion/asientocta',
            'legacy' => '/administracion/asientocta',
            'grilla' => 'debe-haber',

            'imputa' => ['cliente', 'proveedor', 'file'],

            'tipo_debe' => 'D',

            'arqueo' => false,

            // Sólo se ofrece si la licencia tiene 'botonafectacobranza'
            // (formasientocta.php:66).
            'afecta_cobranza' => true,
            'proyecto' => false,

            'borra_movimientos' => false,
            // asientocta.php:246-249 libera los movimientos que este asiento
            // había marcado como usados.
            'libera_utilizado' => true,
        ],

        'fondos' => [
            'codigo' => 'M',
            'titulo' => 'Movimientos de Fondo',
            'singular' => 'movimiento de fondos',
            'seccion' => 'administracion/fondos',
            'legacy' => '/administracion/fondos',
            'grilla' => 'ingreso-egreso',

            'imputa' => [],

            // No aplica: la grilla de fondos no es debe/haber, es par
            // ingreso/egreso y cada par genera su propio asiento.
            'tipo_debe' => null,

            'arqueo' => false,
            'afecta_cobranza' => false,
            'proyecto' => true,

            /*
            | fondos.php:279 BORRA los movimientos al anular, mientras que los
            | otros dos sólo los marcan. Se respeta (hay reportes que cuentan
            | con que desaparezcan), pero antes de borrar se vuelca la fila
            | entera a `auditoria`, que es lo que el legacy perdía.
            */
            'borra_movimientos' => true,
            'libera_utilizado' => true,
        ],
    ],

    /*
    | Estados de `ordenadmin.status`, tal como los lista el filtro del CI
    | (asientocontable.php:64-68).
    */
    'estados' => [
        'OK' => 'Ok',
        'PR' => 'Procesada',
        'AN' => 'Anulada',
    ],

    /*
    | Igual que en facturas de tercero: el CI nunca chequeó permisos en el alta
    | ni en la anulación de estos módulos, así que arrancar en estricto le
    | cortaría el trabajo a roles que hoy operan sin el permiso cargado.
    | Ver App\Support\Permisos.
    */
    'permisos_estrictos' => env('ASIENTOS_PERMISOS_ESTRICTOS', false),
];
