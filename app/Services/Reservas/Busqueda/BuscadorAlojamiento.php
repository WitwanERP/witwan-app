<?php

namespace App\Services\Reservas\Busqueda;

use App\Services\Pricing\Tarifador;
use App\Services\Productos\ProductoService;

/**
 * Hoteles, misceláneos, aéreos en lista y motorhomes: estadía from→to y una
 * cotización por habitación (adultos + edades de menores), como el loop de
 * reserva.php:3919-3936. La fila entra sólo si TODAS las habitaciones cotizan
 * (3969-3971): un hotel que no tiene tarifa para la segunda habitación no sirve
 * para esa venta.
 */
class BuscadorAlojamiento implements BuscadorTipo
{
    public function __construct(
        protected CandidatosQuery $candidatos,
        protected ProductoService $productos,
        protected Tarifador $tarifador,
        protected NormalizadorFila $normalizador,
    ) {}

    public function reglas(string $tipo): array
    {
        return ['habitaciones' => 'required|array|min:1|max:6'];
    }

    public function buscar(string $tipo, array $p, array $ctx, Presupuesto $presupuesto): array
    {
        $habitaciones = $this->habitaciones($p);
        $to = $this->fechaFin($tipo, $p);

        $c = $this->candidatos->buscar($tipo, $this->filtrosCandidatos($tipo, $p, $to), $ctx);

        $resultados = [];
        $truncado = $c['truncado'];
        foreach ($c['productos'] as $cand) {
            if ($presupuesto->agotado()) {
                $truncado = true;
                break;
            }
            $producto = $this->productos->cargar((int) $cand->producto_id);
            if ($producto === null) {
                continue;
            }
            $cotizadas = [];
            $completa = true;
            foreach ($habitaciones as $h) {
                $cot = $this->tarifador->cotizar([
                    'producto' => $producto,
                    'producto_id' => (int) $cand->producto_id,
                    'fecha_ini' => $p['from'],
                    'fecha_fin' => $to,
                    'adultos' => $h['ad'],
                    'menores' => $h['mn'],
                    'residente' => $ctx['residente'],
                    'tarifario_id' => (int) $ctx['tarifario_id'],
                    'cliente_id' => (int) $ctx['cliente_id'],
                    'hoy' => $ctx['hoy'],
                ]);
                if (empty($cot['ok'])) {
                    $completa = false;
                    break;
                }
                $cotizadas[] = ['ad' => $h['ad'], 'mn' => $h['mn'], 'cot' => $cot];
            }
            if (! $completa) {
                continue;
            }
            $resultados[] = $this->normalizador->fila($producto, $tipo, $cotizadas, $this->extra($cand, $producto, $cotizadas, $p));
        }

        return ['resultados' => $resultados, 'truncado' => $truncado, 'candidatos' => count($c['productos'])];
    }

    /** @return list<array{ad:int, mn:list<int>}> */
    protected function habitaciones(array $p): array
    {
        $out = [];
        foreach ((array) ($p['habitaciones'] ?? [['ad' => (int) ($p['ad'] ?? 2), 'mn' => $p['mn'] ?? []]]) as $h) {
            $out[] = ['ad' => max(1, (int) ($h['ad'] ?? 1)), 'mn' => array_values(array_map('intval', (array) ($h['mn'] ?? [])))];
        }

        return $out ?: [['ad' => 2, 'mn' => []]];
    }

    /** Filtros para CandidatosQuery a partir del formulario. */
    protected function filtrosCandidatos(string $tipo, array $p, ?string $to): array
    {
        $cfg = (array) config("reservas_busqueda.tipos.{$tipo}", []);

        return [
            'ciudades' => array_filter([(int) ($p['ciudad'] ?? 0)]),
            'nombre' => $p['nombre'] ?? '',
            'producto_ids' => $p['producto_ids'] ?? array_filter([(int) ($p['producto_id'] ?? 0)]),
            'stars' => $p['stars'] ?? [],
            'from' => $p['from'],
            'to' => $to,
            'solo_circuito' => (bool) ($cfg['solo_circuito'] ?? false),
        ];
    }

    protected function fechaFin(string $tipo, array $p): ?string
    {
        return ! empty($p['to']) ? (string) $p['to'] : null;
    }

    protected function extra(object $cand, array $producto, array $cotizadas, array $p): array
    {
        return [
            'estrellas' => $cand->estrellas,
            'proveedor_nombre' => $cand->proveedor_nombre,
            'ciudad' => $cand->ciudad,
            'pickup' => false,
            'disponibilidad_producto' => (string) $cand->disponibilidad,
        ];
    }
}
