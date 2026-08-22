<?php

/**
 * Catálogo declarativo del módulo de Facturas de Tercero (facturas de proveedor).
 *
 * Reemplaza las ramas `if (LICPAIS == 'CL')` / `if (LICENCIA == '...')` que el CI
 * legacy tiene desperdigadas entre el controller y la vista. Se resuelve con
 * `App\Support\Licencia::flag('facturaproveedor.<clave>')`, que soporta dos
 * formas: lista de licencias => booleano de pertenencia, o mapa licencia => valor.
 * Los mapas por PAÍS se leen directo con config(), usando Licencia::pais().
 *
 * Trazabilidad: application/controllers/administracion/factura3ero.php y
 * application/views/administracion/documentos/factura3ro.php del CI.
 */
return [

    'per_page' => 50,

    /*
    |--------------------------------------------------------------------------
    | Alícuotas y redondeo
    |--------------------------------------------------------------------------
    | Sólo la alícuota general varía por país; el resto están hardcodeadas en el
    | legacy (factura3ero.php:869) para TODAS las licencias, Chile incluido.
    | La general se sobrescribe en runtime con sysconfig.tasageneral cuando existe.
    */
    'alicuotas_fijas' => [
        'especial' => 0.105,
        'monto27' => 0.27,
        'monto25' => 0.025,
    ],

    'alicuota_general' => [
        'AR' => 0.21,
        'CL' => 0.19,
        'DO' => 0.18,
    ],

    // Decimales del redondeo del IVA. Chile trabaja con pesos enteros.
    'decimales_iva' => ['CL' => 0],

    // Países donde el usuario carga el IVA a mano y el servidor lo respeta
    // (factura3ero.php:870-872: `soloiva = round($pst['ivatotal'])`).
    'ivatotal_editable' => ['CL'],

    // El IVA de la alícuota general se redondea a entero (scriptfactura3ro.js:62-65).
    'iva_general_entero' => ['CL'],

    // Sumas válidas para la imputación por área (scriptfactura3ro.js:318-323).
    'imputacion_suma_valida' => ['CL' => [100, 200]],

    /*
    |--------------------------------------------------------------------------
    | Catálogos del formulario  (factura3ro.php:65-145)
    |--------------------------------------------------------------------------
    */
    'tipos_movimiento' => [
        'comunes' => [
            'Servicio' => 'Servicio en reserva',
            'BSP' => 'BSP',
            'Provision Facturas a Recibir' => 'Provision Facturas a Recibir',
        ],
        'por_pais' => [
            'CL' => [
                'Gasto' => 'Gasto del Giro',
                'IVA no recuperable' => 'IVA no recuperable',
                'Supermercado' => 'Supermercado',
                'Activo Fijo' => 'Activo Fijo',
                'Bienes Raices' => 'Bienes Raices',
            ],
            'default' => ['Gasto' => 'Gasto'],
        ],
        // Sólo la familia secontur (_t_l == 'secontur').
        'extra_familia_secontur' => ['Gasto IVA directo' => 'Gasto IVA directo'],
    ],

    'tipos_documento' => [
        'Factura' => 'Factura',
        'Nota de Credito' => 'Nota de Credito',
        'Boleta' => 'Boleta',
        'Nota de Debito' => 'Nota de Debito',
        'Factura Exterior' => 'Facturas del Exterior',
    ],

    'tipos_factura' => [
        'AR' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'E' => 'E', 'T' => 'T', 'M' => 'M'],
        'default' => ['A' => 'Afecta', 'E' => 'Exenta', 'B' => 'Boleta', 'P' => 'Pro-forma', 'X' => 'Interno'],
    ],

    'mascara_numero' => ['AR' => '99999-99999999'],

    /*
    |--------------------------------------------------------------------------
    | Imputación por área  (factura3ro.php:321-388)
    |--------------------------------------------------------------------------
    | 'origen' indica de dónde salen las áreas:
    |   - 'fijas'    => el mapa literal de 'areas'
    |   - 'tabla'    => filas de `centrocosto`
    |   - 'sistemas' => nombres de la tabla `sistema` (1..4) + ADMINISTRACION (5),
    |                   con los fallbacks del legacy cuando el sistema no existe.
    */
    'imputacion' => [
        'witwan_secontur' => [
            'origen' => 'fijas',
            'titulo' => 'IMPUTACION (en %)',
            'areas' => [1 => 'TEAM 1', 2 => 'TEAM 2', 3 => 'TEAM 3'],
        ],
        'mundotour_sdg' => [
            'origen' => 'tabla',
            'titulo' => 'CENTRO DE COSTO (en %)',
        ],
        'default' => [
            'origen' => 'sistemas',
            'titulo' => 'IMPUTACION (en %)',
            'fallbacks' => [1 => 'RECEPTIVO', 2 => 'MAYORISTA', 3 => 'CORPORATIVO', 4 => 'CONSOLIDADOR', 5 => 'ADMINISTRACION'],
            // Del 3 al 5 el legacy usa etiquetas fijas, no el nombre del sistema.
            'fijas' => [3 => 'CORPORATIVO', 4 => 'CONSOLIDADOR', 5 => 'ADMINISTRACION'],
        ],
    ],

    // La familia secontur no muestra bloque de imputación (factura3ro.php:365).
    'imputacion_oculta_familia_secontur' => true,

    // Adjunto: mundotour_sdg o familia secontur (factura3ro.php:339).
    'adjunto_licencias' => ['mundotour_sdg'],
    'adjunto_familia_secontur' => true,

    // Item de gasto obligatorio al guardar (scriptfactura3ro.js:324).
    'itemgasto_obligatorio' => ['mundotour_sdg'],

    /*
    |--------------------------------------------------------------------------
    | Proveedor BSP  (ajax.php:313)
    |--------------------------------------------------------------------------
    | Los boletos aéreos del BSP se agrupan por semana y se contabilizan contra
    | la cuenta de provisión. `familia_secontur` aplica a _t_l == 'secontur',
    | no sólo a la licencia witwan_secontur.
    */
    'proveedor_bsp' => [
        'familia_secontur' => 370,
        'por_licencia' => ['witwan_hayland' => 107],
        'config_item' => env('FP_PROVEEDOR_BSP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ventana de servicios pendientes  (ajax.php:246-253)
    |--------------------------------------------------------------------------
    | Antigüedad máxima de los servicios que se ofrecen para imputar. Se ignora
    | cuando la búsqueda viene con código de reserva.
    */
    'ventana_servicios' => [
        'default' => '-18 months',
        'witwan_morisan' => '2022-05-01',
        'witwan_hayland' => '-3 months',
    ],

    /*
    |--------------------------------------------------------------------------
    | Asiento contable: concepto => cuenta de sysconfig  (factura3ero.php:1169-1368)
    |--------------------------------------------------------------------------
    */
    'asiento' => [
        'netos' => [
            'exento' => 'fc3exento',
            'general' => 'fc3gral',
            'especial' => 'fc3especial',
            'nocomputable' => 'fc3nocomputable',
            'monto27' => 'fc3monto27',
            'monto25' => 'fc3monto25',
        ],
        'impuestos' => [
            // El IVA turismo se debita en negativo.
            'ivatur' => ['cuenta' => 'ctaivatur', 'signo' => -1],
            'retencioniva' => ['cuenta' => 'fc3retiva'],
            'retencioniibb' => ['cuenta' => 'fc3retiibb'],
            'retencionganancias' => ['cuenta' => 'fc3retganancias'],
            'percepcioniibb' => ['cuenta' => 'fc3perciibb'],
            'percepcioniva' => ['cuenta' => 'fc3perciva'],
            'percepcionganancias' => ['cuenta' => 'fc3perganancias'],
            'otrosimpuestos' => ['cuenta' => 'fc3otros'],
        ],
        // 'gasto' es la cuenta alternativa cuando tipomovimiento == 'Gasto'.
        'iva' => [
            'general' => ['normal' => 'fc3ivatotal', 'gasto' => 'fc3ivatotal_i'],
            'especial' => ['normal' => 'fc3ivaespecial', 'gasto' => 'fc3ivaespecial_i'],
            'monto27' => ['normal' => 'fc3iva27', 'gasto' => 'fc3iva27'],
            'monto25' => ['normal' => 'fc3iva25', 'gasto' => 'fc3iva25'],
        ],
        'credito' => [
            'default' => 'cuentaproveedor',
            'USD' => 'cuentaproveedorusd',
            'Gasto' => 'cuentaproveedorvarios',
            'Boleta' => 'cuentaproveedorvarios',
            'BSP' => 'provisionBSP',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SECONTUR: división multi-base
    |--------------------------------------------------------------------------
    | Sólo la licencia exacta `witwan_secontur` replica la factura prorrateada en
    | sus bases hermanas (factura3ero.php:1040). La FAMILIA secontur es otra cosa.
    */
    'split_bases' => [
        'witwan_secontur' => ['witwan_secontur1', 'witwan_secontur2', 'witwan_secontur3'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Interruptores de comportamiento
    |--------------------------------------------------------------------------
    */

    // Exige permiso de sección en todas las acciones. Con false la denegación
    // sólo se loguea (modo observación) y la acción sigue adelante.
    //
    // Arranca en false por evidencia, no por comodidad: el CI nunca chequeó
    // permisos en create/edit/save/save_after_edit, así que hoy cualquier rol con
    // 'acceso' puede cargar facturas. En el tenant relevado, de los 9 roles con
    // acceso a la sección 72 sólo 4 (ADP, APC, APD, Cta) tienen 'alta'/'edicion'/
    // 'borrado' en `permisogrupo`: ADF, ADM, JCF, JVS y VCO tienen únicamente
    // 'acceso'. Activarlo de entrada les cortaría el alta a 5 de 9 roles.
    // Pasar a true una vez revisada la matriz de permisos con el cliente.
    'permisos_estrictos' => env('FP_PERMISOS_ESTRICTOS', false),

    // Borra el `asientocontable` al eliminar la factura. El legacy lo deja
    // huérfano (_after_delete sólo limpia movimiento y la relación con servicios).
    'limpiar_asiento_huerfano' => env('FP_LIMPIAR_ASIENTO_HUERFANO', false),

    // Débito faltante por el IVA de la alícuota 2,5%: el legacy lo suma al crédito
    // del proveedor pero nunca lo debita, así que el asiento no cuadra cuando
    // monto25 != 0 (factura3ero.php:869 vs :1281-1339). Activarlo corrige el
    // asiento pero lo aparta de lo que sigue generando el CI: requiere OK contable.
    'iva_monto25_al_debe' => env('FP_IVA_MONTO25_AL_DEBE', false),
];
