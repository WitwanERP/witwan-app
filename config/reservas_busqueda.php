<?php

use App\Services\Reservas\Busqueda\BuscadorAlojamiento;
use App\Services\Reservas\Busqueda\BuscadorAsistencia;
use App\Services\Reservas\Busqueda\BuscadorPaquete;
use App\Services\Reservas\Busqueda\BuscadorTramos;

/*
|--------------------------------------------------------------------------
| Generador de reservas — búsqueda por tipo de producto
|--------------------------------------------------------------------------
| Réplica declarativa de las pestañas de reserva/nueva del CI
| (views/reserva/inc/filtro_*.php + reserva.php::resultado() 3146-4183):
| qué formulario muestra cada tipo, qué campos exige y qué clase lo cotiza.
| Cada buscador implementa App\Services\Reservas\Busqueda\BuscadorTipo; para
| sumar PKD/CAE/CTK o interfases XML alcanza con registrar otra clase acá.
|
| campos: from, to, ciudad, origen, destino, nombre, producto, stars,
|         habitaciones (ad + edades de menores por habitación), ad, mn, mayores70.
| requiere: obligatorios; "a|b" = al menos uno de los dos.
*/

return [

    // Productos a cotizar por búsqueda (más allá se marca `truncado`).
    'max_candidatos' => (int) env('RESERVAS_BUSQUEDA_MAX', 60),

    // Tiempo máximo cotizando, en ms. Se corta el loop y se devuelve lo que hay.
    'presupuesto_ms' => (int) env('RESERVAS_BUSQUEDA_MS', 8000),

    // Cache de la respuesta completa (mismos parámetros, tarifario y día).
    'cache_store' => env('RESERVAS_BUSQUEDA_CACHE', env('CACHE_STORE', 'file')),
    'cache_ttl' => 120,

    // Tipos cuya fila del generador lleva pick-up / drop-off (reserva.php:2717-2724).
    'con_pickup' => ['EXC', 'TRN', 'GUI'],

    'ofertas' => [
        'dias' => 3,             // fechas cercanas: ±N días
        'historial_meses' => 12,
        'cache_ttl' => 600,
    ],

    'cross_selling' => [
        // Qué tipos se ofrecen al agregar un servicio de cada tipo ("completar el viaje").
        'complementarios' => [
            'HOT' => ['TRN', 'EXC', 'ASV', 'GUI'], 'MSC' => ['TRN', 'EXC', 'ASV'], 'AEL' => ['TRN', 'ASV'], 'MOT' => ['ASV', 'EXC'],
            'PAQ' => ['ASV', 'TRN'], 'TRL' => ['ASV', 'TRN'], 'EXC' => ['TRN', 'ASV', 'EXC'], 'TRN' => ['EXC', 'ASV'],
            'TRE' => ['ASV', 'TRN'], 'AUT' => ['ASV'], 'CRU' => ['ASV', 'TRN'], 'GUI' => ['EXC', 'TRN'], 'ASV' => [],
        ],
        'max_por_tipo' => 4,
        'ventana_meses' => 24,   // historial de co-ocurrencia
        'cache_ttl' => 21600,
    ],

    'tipos' => [
        'HOT' => ['buscador' => BuscadorAlojamiento::class, 'orden' => 10, 'campos' => ['from', 'to', 'habitaciones', 'ciudad', 'nombre', 'stars'], 'requiere' => ['from', 'to', 'habitaciones', 'ciudad|nombre|producto']],
        'MSC' => ['buscador' => BuscadorAlojamiento::class, 'orden' => 15, 'campos' => ['from', 'to', 'habitaciones', 'ciudad', 'nombre'], 'requiere' => ['from', 'to', 'ciudad|nombre|producto']],
        'AEL' => ['buscador' => BuscadorAlojamiento::class, 'orden' => 16, 'campos' => ['from', 'to', 'habitaciones', 'ciudad', 'nombre'], 'requiere' => ['from', 'ciudad|nombre|producto']],
        'MOT' => ['buscador' => BuscadorAlojamiento::class, 'orden' => 40, 'campos' => ['from', 'to', 'habitaciones', 'ciudad', 'nombre'], 'requiere' => ['from', 'to', 'ciudad|nombre|producto']],
        'PAQ' => ['buscador' => BuscadorPaquete::class, 'orden' => 20, 'campos' => ['from', 'habitaciones', 'ciudad', 'nombre'], 'requiere' => ['from', 'ciudad|nombre|producto'], 'solo_circuito' => true],
        'TRL' => ['buscador' => BuscadorPaquete::class, 'orden' => 21, 'campos' => ['from', 'habitaciones', 'ciudad', 'nombre'], 'requiere' => ['from', 'ciudad|nombre|producto']],
        'TRE' => ['buscador' => BuscadorTramos::class, 'orden' => 22, 'campos' => ['from', 'ciudad', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'ciudad|nombre|producto']],
        'EXC' => ['buscador' => BuscadorTramos::class, 'orden' => 30, 'campos' => ['from', 'ciudad', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'ciudad|nombre|producto']],
        'GUI' => ['buscador' => BuscadorTramos::class, 'orden' => 31, 'campos' => ['from', 'ciudad', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'ciudad|nombre|producto']],
        'TRN' => ['buscador' => BuscadorTramos::class, 'orden' => 32, 'campos' => ['from', 'origen', 'destino', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'origen|producto']],
        'AUT' => ['buscador' => BuscadorTramos::class, 'orden' => 41, 'campos' => ['from', 'to', 'ciudad', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'to', 'ciudad|nombre|producto']],
        'CRU' => ['buscador' => BuscadorTramos::class, 'orden' => 42, 'campos' => ['from', 'to', 'ciudad', 'nombre', 'ad', 'mn'], 'requiere' => ['from', 'to', 'ciudad|nombre|producto']],
        'ASV' => ['buscador' => BuscadorAsistencia::class, 'orden' => 50, 'campos' => ['from', 'to', 'nombre', 'ad', 'mn', 'mayores70'], 'requiere' => ['from', 'to']],
    ],
];
