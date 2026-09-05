<?php

/**
 * Catálogo declarativo del módulo de Productos / Vigencias / Tarifas.
 *
 * Reemplaza a los 18 controladores de `application/controllers/productos/*.php`
 * del CI (copias literales que sólo cambian `tpro`) y al array de
 * `Producto_model::get_estructura()` (producto_model.php:22-599), que define el
 * formulario por tipo. Todo lo que acá dice `tabla => producto_extra` viaja al
 * EAV `producto_extra` con la MISMA clave que usa el CI: `byid()` las mapea al
 * ítem y el tarifador las lee (edad_*, zona, politica_*, aparece_excel...).
 *
 * Contrato de compatibilidad completo: docs/PRODUCTOS_VIGENCIAS_TARIFAS.md §4.6.
 */
$edades = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17];
$edadesSenior = [50, 55, 60, 65, 70, 75, 80, 85];

// Bloques de campos que get_estructura() repite en varios tipos.
$politicaOcupacion = function (string $titulo) {
    return [
        ['campo' => '!politica_ocupacion', 'label' => $titulo, 'tipo' => 'titulo'],
        ['campo' => 'politica_ocupacion', 'label' => 'Español', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
        ['campo' => 'politica_ocupacionen', 'label' => 'Inglés', 'tipo' => 'textarea', 'tabla' => 'producto_extra', 'sistemas' => [1, 2]],
        ['campo' => 'politica_ocupacionpt', 'label' => 'Portugués', 'tipo' => 'textarea', 'tabla' => 'producto_extra', 'sistemas' => [1, 2]],
    ];
};

$edadesMenores = function (array $cuales) use ($edades, $edadesSenior) {
    $todas = [
        'edad_infoa' => ['campo' => 'edad_infoa', 'label' => 'Infante', 'tipo' => 'select', 'tabla' => 'producto_extra', 'lista' => $edades],
        'edad_menor1' => ['campo' => 'edad_menor1', 'label' => 'Menor 1', 'tipo' => 'select', 'tabla' => 'producto_extra', 'lista' => $edades],
        'edad_menor2' => ['campo' => 'edad_menor2', 'label' => 'Menor 2', 'tipo' => 'select', 'tabla' => 'producto_extra', 'lista' => $edades],
        'edad_junior' => ['campo' => 'edad_junior', 'label' => 'Junior', 'tipo' => 'select', 'tabla' => 'producto_extra', 'lista' => $edades],
        'edad_senior' => ['campo' => 'edad_senior', 'label' => 'Senior (edad mínima)', 'tipo' => 'select', 'tabla' => 'producto_extra', 'lista' => $edadesSenior],
    ];

    $bloque = [['campo' => '!edadesmenores', 'label' => 'Edades máximas menores', 'tipo' => 'titulo']];
    foreach ($cuales as $c) {
        $bloque[] = $todas[$c];
    }

    return $bloque;
};

$modoTarifa = fn (array $lista) => ['campo' => 'modotarifa', 'label' => 'Modo tarifa', 'tipo' => 'radio', 'lista' => $lista, 'required' => true];
$disponibilidad = ['campo' => 'disponibilidad', 'label' => 'Disponibilidad', 'tipo' => 'radio', 'lista' => ['CI' => 'Disponibilidad inmediata', 'RQ' => 'A requerir']];
$bases = ['campo' => 'bases', 'label' => 'Bases', 'tipo' => 'bases', 'tabla' => 'rel_productobase'];
$facilidades = ['campo' => 'facilidades', 'label' => 'Facilidades', 'tipo' => 'facilidades', 'tabla' => 'rel_productoalojamientofacilidad', 'opciones' => 'facilidades'];
$destinos = fn (string $label = 'Destinos') => ['campo' => 'destinos', 'label' => $label, 'tipo' => 'destinos', 'tabla' => 'rel_productociudad', 'opciones' => 'ciudades'];
$ciudadUnica = ['campo' => 'destino', 'label' => 'Ciudad', 'tipo' => 'ciudad', 'opciones' => 'ciudades', 'required' => true];
$origen = ['campo' => 'origen', 'label' => 'Origen', 'tipo' => 'select', 'opciones' => 'ciudades'];

// Bloque de hotelería (HOT/MSC): categoría, tipo, ciudad única, datos de contacto.
$hoteleria = [
    $bases,
    $modoTarifa(['P' => 'Por persona', 'H' => 'Por habitación']),
    ['campo' => 'fk_hotelcategoria_id', 'label' => 'Categoría', 'tipo' => 'radio', 'tabla' => 'producto_extra', 'lista' => [1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']],
    ['campo' => 'fk_alojamientotipo_id', 'label' => 'Tipo de alojamiento', 'tipo' => 'select', 'tabla' => 'producto_extra', 'opciones' => 'alojamientotipos'],
    $ciudadUnica,
    ['campo' => 'zona', 'label' => 'Zona', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ['campo' => 'telefono', 'label' => 'Teléfono', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ['campo' => 'ubicacion', 'label' => 'Dirección', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ['campo' => 'alojamiento_web', 'label' => 'Sitio web', 'tipo' => 'url', 'tabla' => 'producto_extra'],
    ['campo' => 'gmaps', 'label' => 'Google Maps', 'tipo' => 'textarea', 'ayuda' => 'Copiar desde "Incorporar mapa" el enlace <iframe...>'],
    ['campo' => 'alojamiento_checkin', 'label' => 'Horario de check-in', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ['campo' => 'alojamiento_checkout', 'label' => 'Horario de check-out', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    $facilidades,
    ...$edadesMenores(['edad_infoa', 'edad_menor1', 'edad_menor2', 'edad_junior', 'edad_senior']),
    ...$politicaOcupacion('Política de ocupación'),
];

// Bloque de paquetes/trenes (PAQ/TRL/TRE).
$paquete = [
    $modoTarifa(['P' => 'Por persona', 'S' => 'Por servicio']),
    $bases,
    $disponibilidad,
    $destinos(),
    ['campo' => 'fk_region_id', 'label' => 'Región', 'tipo' => 'select', 'tabla' => 'producto_extra', 'opciones' => 'regiones'],
    ['campo' => 'minimo_pax', 'label' => 'Mínimo de pax', 'tipo' => 'number', 'tabla' => 'producto_extra'],
    ...$edadesMenores(['edad_infoa', 'edad_menor1', 'edad_junior', 'edad_senior']),
    ['campo' => 'circuito', 'label' => '¿Es circuito?', 'tipo' => 'boolean', 'tabla' => 'producto_extra'],
    ['campo' => 'itinerario', 'label' => 'Itinerario', 'tipo' => 'itinerario', 'tabla' => 'producto_extra'],
    ['campo' => '!textos', 'label' => 'Textos adicionales', 'tipo' => 'titulo'],
    ['campo' => 'recorrida', 'label' => 'Recorrida', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
    ['campo' => 'salida', 'label' => 'Salida', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
    ['campo' => 'paquete_incluye', 'label' => 'Paquete incluye', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
    ['campo' => 'paquete_noincluye', 'label' => 'Paquete no incluye', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
    ['campo' => 'paquete_sugeridos', 'label' => 'Hoteles sugeridos', 'tipo' => 'textarea', 'tabla' => 'producto_extra'],
    ...$politicaOcupacion('Política de menores'),
];

// Bloque de excursiones/guías (EXC/GUI).
$excursion = [
    $disponibilidad,
    $origen,
    $destinos(),
    $modoTarifa(['P' => 'Por persona (regular)', 'S' => 'Por servicio (privado)']),
    ...$edadesMenores(['edad_infoa', 'edad_menor1']),
];

// Bloque de autos/motorhomes (AUT/MOT).
$vehiculo = [
    ['campo' => 'alojamiento_checkin', 'label' => 'Horario pickup', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    $modoTarifa(['P' => 'Por persona', 'S' => 'Por servicio']),
    $destinos('Ciudad pickup'),
    ['campo' => 'alojamiento_checkout', 'label' => 'Horario dropoff', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ['campo' => 'ciudaddropoff', 'label' => 'Ciudad dropoff', 'tipo' => 'select', 'tabla' => 'producto_extra', 'opciones' => 'ciudades'],
    $facilidades,
];

// Bloque de aéreos (AEL).
$aereo = [
    $bases,
    $modoTarifa(['P' => 'Por persona', 'H' => 'Por habitación']),
    $ciudadUnica,
    ['campo' => 'alojamiento_checkin', 'label' => 'Horario de vuelo', 'tipo' => 'text', 'tabla' => 'producto_extra'],
    ...$politicaOcupacion('Política de ocupación'),
    ...$edadesMenores(['edad_infoa', 'edad_menor1']),
];

return [

    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    | Igual que facturaproveedor: arranca en observación (loguea, no corta) hasta
    | medir a qué roles les falta el permiso en `permisogrupo`. En el CI ninguno
    | de los 18 controladores llamaba a `_check_perm()`.
    */
    'permisos_estrictos' => (bool) env('PRODUCTOS_PERMISOS_ESTRICTOS', false),

    'per_page' => 50,

    /*
    |--------------------------------------------------------------------------
    | Sistemas
    |--------------------------------------------------------------------------
    | Slug de URL => producto.fk_sistema_id (hotel.php:25-43, vigencia.php:449).
    */
    'sistemas' => [
        'receptivo' => 1,
        'mayorista' => 2,
        'minorista' => 3,
        'consolidador' => 4,
        'nacional' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipos de producto
    |--------------------------------------------------------------------------
    | Clave = producto.fk_tipoproducto_id (tabla `submodulo`). `slug` es el
    | segmento de URL del CI (/productos/{slug}/...), que se conserva para que
    | el menú y los links del legacy sigan resolviendo.
    |
    |   alojamiento  => la grilla de tarifas es categorías × bases (vigencia.php:276).
    |                   Si es false, es tramos de pax × tipos de pax.
    |   habitaciones => tiene alojamientohabitacion editable en el form.
    |   ciudad       => 'unica' (producto.destino + una fila en rel_productociudad,
    |                   hotel.php:280) o 'multiple' (destinos con tipo D/O).
    |   modotarifa   => valores admitidos de producto.modotarifa para el tipo.
    |   tipos_pax    => columnas de la grilla de tramos (vigencia.php:393-395).
    |   seccion      => URI del CI para resolver el fk_seccion_id de permisos.
    */
    'tipos' => [
        'HOT' => ['nombre' => 'Hoteles', 'slug' => 'hotel', 'alojamiento' => true, 'habitaciones' => true, 'ciudad' => 'unica', 'modotarifa' => ['P', 'H'], 'campos' => $hoteleria],
        'MSC' => ['nombre' => 'Misceláneos', 'slug' => 'misc', 'alojamiento' => true, 'habitaciones' => true, 'ciudad' => 'unica', 'modotarifa' => ['P', 'H'], 'campos' => $hoteleria],
        'AEL' => ['nombre' => 'Aéreos', 'slug' => 'aereo', 'alojamiento' => true, 'habitaciones' => true, 'ciudad' => 'unica', 'modotarifa' => ['P', 'H'], 'campos' => $aereo],
        'MOT' => ['nombre' => 'Motorhomes', 'slug' => 'motorhome', 'alojamiento' => true, 'habitaciones' => true, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $vehiculo],
        'AUT' => ['nombre' => 'Autos', 'slug' => 'auto', 'alojamiento' => false, 'habitaciones' => true, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $vehiculo, 'tipos_pax' => ['ADU', 'CHD', 'INF']],
        'PAQ' => ['nombre' => 'Circuitos', 'slug' => 'circuito', 'alojamiento' => true, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $paquete, 'max_child' => 1],
        'TRL' => ['nombre' => 'Trenes de lujo', 'slug' => 'treneslujo', 'alojamiento' => true, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $paquete],
        'TRE' => ['nombre' => 'Trenes', 'slug' => 'trenes', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => [$origen, ...$paquete], 'tipos_pax' => ['ADU', 'CHD', 'INF', 'JNR', 'SNR']],
        'EXC' => ['nombre' => 'Excursiones', 'slug' => 'excursion', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $excursion, 'tipos_pax' => ['ADU', 'CHD', 'INF']],
        'GUI' => ['nombre' => 'Guías', 'slug' => 'guia', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => $excursion, 'tipos_pax' => ['ADU', 'CHD', 'INF']],
        'TRN' => ['nombre' => 'Traslados', 'slug' => 'traslado', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => [$disponibilidad, $origen, $destinos(), $modoTarifa(['P' => 'Por persona', 'S' => 'Por servicio'])], 'tipos_pax' => ['ADU', 'CHD', 'INF']],
        'CRU' => ['nombre' => 'Cruceros', 'slug' => 'crucero', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => [$disponibilidad, $destinos(), $modoTarifa(['P' => 'Por persona', 'S' => 'Por servicio'])], 'tipos_pax' => ['ADU', 'CHD', 'INF']],
        'ASV' => ['nombre' => 'Asistencia al viajero', 'slug' => 'asistencia', 'alojamiento' => false, 'habitaciones' => false, 'ciudad' => 'multiple', 'modotarifa' => ['P', 'S'], 'campos' => [$destinos(), $disponibilidad], 'tipos_pax' => ['<70', '>70']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campos comunes a todos los tipos
    |--------------------------------------------------------------------------
    | `antes` va al principio del form y `despues` al final (política de
    | cancelación), tal como los arma get_estructura().
    */
    'campos_comunes' => [
        'antes' => [
            ['campo' => 'producto_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 255],
            ['campo' => 'nombre_descriptivo', 'label' => 'Nombre para itinerario', 'tipo' => 'text', 'tabla' => 'producto_extra'],
            ['campo' => 'producto_nombre_en', 'label' => 'Nombre (inglés)', 'tipo' => 'text', 'max' => 255],
            ['campo' => 'producto_nombre_pt', 'label' => 'Nombre (portugués)', 'tipo' => 'text', 'max' => 255, 'sistemas' => [1, 2]],
            ['campo' => 'producto_codigo', 'label' => 'Código', 'tipo' => 'text', 'max' => 50],
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => 'proveedores', 'required' => true],
            ['campo' => 'fk_prestador_id', 'label' => 'Prestador', 'tipo' => 'select', 'opciones' => 'proveedores'],
            ['campo' => 'producto_descripcion', 'label' => 'Descripción', 'tipo' => 'textarea'],
            ['campo' => 'producto_descripcion_en', 'label' => 'Descripción (inglés)', 'tipo' => 'textarea', 'sistemas' => [1, 2]],
            ['campo' => 'producto_descripcion_pt', 'label' => 'Descripción (portugués)', 'tipo' => 'textarea', 'sistemas' => [1, 2]],
            ['campo' => 'habilitar', 'label' => 'Habilitar producto', 'tipo' => 'boolean', 'default' => 1],
            ['campo' => 'aparece_tarifario', 'label' => 'Disponible para usuarios externos en el proceso de reserva', 'tipo' => 'boolean', 'default' => 1],
            ['campo' => 'aparece_excel', 'label' => 'Mostrar en Tarifarios > Vista tarifarios', 'tipo' => 'boolean', 'tabla' => 'producto_extra'],
        ],
        'despues' => [
            ['campo' => '!politica_cancelacion', 'label' => 'Política de cancelación', 'tipo' => 'titulo'],
            ['campo' => 'politica_cancelacion', 'label' => 'Español', 'tipo' => 'textarea'],
            ['campo' => 'politica_cancelacionen', 'label' => 'Inglés', 'tipo' => 'textarea', 'tabla' => 'producto_extra', 'sistemas' => [1, 2]],
            ['campo' => 'politica_cancelacionpt', 'label' => 'Portugués', 'tipo' => 'textarea', 'tabla' => 'producto_extra', 'sistemas' => [1, 2]],
        ],
    ],

    // Campos que get_estructura() agrega sólo para una licencia (producto_model.php:92-103).
    'campos_por_licencia' => [
        'witwan_expert' => [
            ['campo' => 'propina', 'label' => '% de propina', 'tipo' => 'number', 'tabla' => 'producto_extra'],
        ],
        'witwan_tower' => [
            ['campo' => 'iibbprovincial', 'label' => '% de IIBB', 'tipo' => 'number', 'tabla' => 'producto_extra'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vigencias y tarifas
    |--------------------------------------------------------------------------
    */

    // producto.bases cuando el producto no tiene rel_productobase ni extra `bases` (vigencia.php:279).
    'bases_default' => ['1', '2', '3'],

    // Pseudo-bases de menores que deriva vigencia.php:286-320 (ver BasesResolver).
    'bases_menores' => ['INF', 'MN', 'M2', 'JNR'],

    // Todos los tipos de pax que admite tarifa.fk_tipopax_id (vigencia.php:393).
    'tipos_pax' => ['ADU', 'CHD', 'INF', 'SNR', 'JNR', '<70', '>70'],

    // tarifa.redondear (views/productos/vigencia.php:274-277).
    'redondeo' => [' ' => 'Sin redondeo', 'R' => 'Por aproximación', 'C' => 'Siempre para arriba'],

    // vigencia.residente (views/productos/vigencia.php:205-211).
    'residente' => ['' => 'Todos', 'R' => 'Residentes', 'O' => 'No residentes'],

    // vigencia.modo_nochesminimas (views/productos/vigencia.php:139-143).
    'modo_nochesminimas' => ['X' => 'En toda la estadía', 'T' => 'En esta vigencia'],

    // Días de semana: fk_dia_id de rel_vigenciadia (1 = lunes ... 7 = domingo).
    'dias' => [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'],

    // Máximo de vencimientos (views/productos/vigencia.php:262-266).
    'vencimiento_max' => 120,

    /*
    |--------------------------------------------------------------------------
    | Secciones del CI (permisos)
    |--------------------------------------------------------------------------
    | URIs que usa App\Support\Secciones para resolver el fk_seccion_id.
    | Productos se gatea por tipo: 'productos/{slug}'. Vigencias, tarifarios y
    | cupos tienen sección propia.
    */
    'secciones' => [
        'vigencia' => 'vigencia',
        'tarifario' => 'tarifario',
        'cupos' => 'producto/vercupo',
    ],
];
