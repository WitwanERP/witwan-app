<?php

namespace App\Services\Reservas\Busqueda;

/**
 * Búsqueda y cotización de productos de una familia de tipos para el
 * generador de reservas. Cada implementación conoce la lógica particular
 * (hotel por noches y habitaciones, traslado por origen/destino, excursión
 * por día, asistencia por cobertura…) y devuelve filas con la misma forma
 * (ver NormalizadorFila), así el front y el carrito no distinguen tipos.
 *
 * Se registran en config/reservas_busqueda.php → tipos.{TIPO}.buscador. Para
 * sumar PKD/CAE/CTK o interfases XML alcanza con otra implementación.
 */
interface BuscadorTipo
{
    /** Reglas de validación extra del formulario para ese tipo (se suman a las base de BusquedaRequest). */
    public function reglas(string $tipo): array;

    /**
     * @param  array  $p  parámetros validados: from, to, ciudad, origen, destino, nombre, producto_id, producto_ids[], stars[], habitaciones[{ad, mn[]}], ad, mn[], mayores70
     * @param  array  $ctx  ['idsistema','sistema_productos','tarifario_id','cliente_id','residente','interno','hoy','fecha_minima']
     * @return array{resultados: list<array>, truncado: bool, candidatos: int}
     */
    public function buscar(string $tipo, array $p, array $ctx, Presupuesto $presupuesto): array;
}
