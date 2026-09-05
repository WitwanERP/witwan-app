<?php

namespace App\Services\Cupos;

use App\Exceptions\Productos\CupoException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cupos (allotment) y soldout (bloqueos) por producto/categoría.
 *
 * Port de producto.php: guardarcupo() (:602), guardarsoldout() (:542),
 * bloqueo() (:628), vercupo() (:667) y los borrados (:502-540).
 *
 * Contrato con el CI (no cambiar): el soldout es UNA FILA POR DÍA
 * (vigencia_ini = vigencia_fin = día), porque tarifar() lo consulta con
 * BETWEEN por fecha (tarifa_model.php:2766); un cupo con cantidad 0 es
 * freesale. Diferencias: el alta por rango va en transacción, el calendario
 * mensual sale de tres queries en vez de tres por día (~90 por mes), y los
 * borrados masivos validan ids enteros (el CI interpolaba el POST: bug B10).
 */
class CupoService
{
    /**
     * @param  array{fk_producto_id:int, fk_tarifacategoria_id:int, cantidad:int, bases?:list<string>, vigencia_ini:string, vigencia_fin:string, release?:int, fk_cliente_id?:int, fk_tarifario_id?:int}  $datos
     */
    public function crearCupo(array $datos, int $usuarioId): int
    {
        [$ini, $fin] = $this->rango($datos);
        $cantidad = (int) ($datos['cantidad'] ?? 0);
        if ($cantidad < 0) {
            throw CupoException::porCampos(['cantidad' => 'La cantidad no puede ser negativa.']);
        }

        return (int) DB::table('cupo')->insertGetId([
            'fk_producto_id' => (int) $datos['fk_producto_id'],
            'fk_tarifacategoria_id' => (int) ($datos['fk_tarifacategoria_id'] ?? 0),
            'cantidad' => $cantidad,
            'bases' => implode(',', array_map('strval', (array) ($datos['bases'] ?? []))),
            'vigencia_ini' => $ini,
            'vigencia_fin' => $fin,
            'freesale' => $cantidad === 0 ? 1 : 0,
            'release' => max(0, (int) ($datos['release'] ?? 0)),
            'fk_cliente_id' => (int) ($datos['fk_cliente_id'] ?? 0),
            'fk_tarifario_id' => (int) ($datos['fk_tarifario_id'] ?? 0),
            'fk_usuario_id' => $usuarioId,
            'fecha_alta' => now()->format('Y-m-d'),
            'subcategoria' => 0,
            'base' => '',
        ]);
    }

    /**
     * Soldout por rango: una fila por día y por categoría. Con categoría null
     * bloquea todas las habilitadas del producto (`todos` del CI). Los días ya
     * bloqueados no se duplican. Devuelve la cantidad de filas insertadas.
     */
    public function crearSoldout(int $productoId, ?int $categoriaId, string $desde, string $hasta, int $usuarioId): int
    {
        [$ini, $fin] = $this->rango(['vigencia_ini' => $desde, 'vigencia_fin' => $hasta]);

        $categorias = $categoriaId !== null
            ? [$categoriaId]
            : DB::table('alojamientohabitacion')->where('fk_producto_id', $productoId)->where('habilitar', 1)->pluck('fk_tarifacategoria_id')->map(fn ($c) => (int) $c)->unique()->values()->all();

        if ($categorias === []) {
            throw CupoException::porCampos(['fk_tarifacategoria_id' => 'El producto no tiene categorías habilitadas.']);
        }

        return DB::transaction(function () use ($productoId, $categorias, $ini, $fin, $usuarioId) {
            $n = 0;
            $ya = DB::table('soldout')
                ->where('fk_producto_id', $productoId)
                ->whereIn('fk_tarifacategoria_id', $categorias)
                ->whereBetween('vigencia_ini', [$ini, $fin])
                ->get(['fk_tarifacategoria_id', 'vigencia_ini'])
                ->map(fn ($s) => $s->fk_tarifacategoria_id.'|'.substr($s->vigencia_ini, 0, 10))
                ->flip()
                ->all();

            foreach ($categorias as $cat) {
                foreach ($this->dias($ini, $fin) as $d) {
                    if (isset($ya["{$cat}|{$d}"])) {
                        continue;
                    }
                    DB::table('soldout')->insert([
                        'fk_producto_id' => $productoId,
                        'fk_tarifacategoria_id' => $cat,
                        'vigencia_ini' => $d,
                        'vigencia_fin' => $d,
                        'fk_usuario_id' => $usuarioId,
                        'fecha_alta' => now()->format('Y-m-d H:i:s'),
                        'fk_subcategoria_id' => 0,
                    ]);
                    $n++;
                }
            }

            return $n;
        });
    }

    /** Toggle de un día desde el calendario (bloqueo()). Devuelve true si quedó bloqueado. */
    public function alternarBloqueo(int $productoId, int $categoriaId, string $fecha, int $usuarioId): bool
    {
        $fecha = $this->fecha($fecha);
        $q = DB::table('soldout')->where('fk_producto_id', $productoId)->where('fk_tarifacategoria_id', $categoriaId)->where('vigencia_ini', $fecha)->where('vigencia_fin', $fecha);

        if ($q->exists()) {
            $q->delete();

            return false;
        }

        DB::table('soldout')->insert([
            'fk_producto_id' => $productoId, 'fk_tarifacategoria_id' => $categoriaId,
            'vigencia_ini' => $fecha, 'vigencia_fin' => $fecha, 'fk_usuario_id' => $usuarioId,
            'fecha_alta' => now()->format('Y-m-d H:i:s'), 'fk_subcategoria_id' => 0,
        ]);

        return true;
    }

    /**
     * Calendario mensual: por día, cupo total, freesale, soldout, servicios
     * asignados y los files. Tres queries (cupo, soldout, servicio) para todo
     * el mes; el reparto por día se hace en PHP.
     */
    public function calendario(int $productoId, int $categoriaId, int $mes, int $anio): array
    {
        $primero = Carbon::create($anio, $mes, 1)->startOfDay();
        $ini = $primero->format('Y-m-d');
        $fin = $primero->copy()->endOfMonth()->format('Y-m-d');
        $dias = $this->dias($ini, $fin);

        $porDia = [];
        foreach ($dias as $d) {
            $porDia[$d] = ['total' => 0, 'freesale' => false, 'soldout' => false, 'asignado' => 0, 'files' => []];
        }

        $solapa = fn ($q) => $q->where('vigencia_ini', '<=', $fin)->where('vigencia_fin', '>=', $ini);

        foreach ($solapa(DB::table('cupo')->where('fk_producto_id', $productoId)->where('fk_tarifacategoria_id', $categoriaId))->get() as $c) {
            foreach ($this->interseccion($c->vigencia_ini, $c->vigencia_fin, $dias) as $d) {
                if ((int) $c->cantidad === 0) {
                    $porDia[$d]['freesale'] = true;
                    $porDia[$d]['total'] += 1;
                } else {
                    $porDia[$d]['total'] += (int) $c->cantidad;
                }
            }
        }

        foreach ($solapa(DB::table('soldout')->where('fk_producto_id', $productoId)->where('fk_tarifacategoria_id', $categoriaId))->get() as $s) {
            foreach ($this->interseccion($s->vigencia_ini, $s->vigencia_fin, $dias) as $d) {
                $porDia[$d]['soldout'] = true;
            }
        }

        $servicios = $solapa(DB::table('servicio')
            ->join('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->where('servicio.fk_producto_id', $productoId)
            ->where('servicio.fk_tarifacategoria_id', $categoriaId)
            ->whereIn('servicio.status', ['RQ', 'CO', 'CL']))
            ->get(['servicio.servicio_id', 'servicio.vigencia_ini', 'servicio.vigencia_fin', 'reserva.reserva_id', 'reserva.fk_filestatus_id', 'reserva.tipocodigo', 'reserva.codigo']);

        foreach ($servicios as $s) {
            foreach ($this->interseccion($s->vigencia_ini, $s->vigencia_fin, $dias) as $d) {
                $porDia[$d]['asignado'] += 1;
                $porDia[$d]['files'][] = ['reserva_id' => (int) $s->reserva_id, 'status' => $s->fk_filestatus_id, 'codigo' => $s->tipocodigo.'-'.$s->codigo];
            }
        }

        $maximo = 0;
        foreach ($porDia as $d) {
            $maximo = max($maximo, $d['total'], $d['asignado']);
        }

        return [
            'mes' => $mes,
            'anio' => $anio,
            'anterior' => ['mes' => (int) $primero->copy()->subMonth()->format('n'), 'anio' => (int) $primero->copy()->subMonth()->format('Y')],
            'siguiente' => ['mes' => (int) $primero->copy()->addMonth()->format('n'), 'anio' => (int) $primero->copy()->addMonth()->format('Y')],
            'maximo' => $maximo,
            'dias' => $porDia,
        ];
    }

    /** Cupos vigentes (vigencia_fin >= hoy), como vercupo() :783. */
    public function cuposVigentes(int $productoId, int $categoriaId): array
    {
        return DB::table('cupo')
            ->where('fk_producto_id', $productoId)->where('fk_tarifacategoria_id', $categoriaId)
            ->where('vigencia_fin', '>=', now()->format('Y-m-d'))
            ->orderByDesc('vigencia_fin')
            ->get()->map(fn ($c) => (array) $c)->all();
    }

    /** Soldouts de los últimos 3 meses en adelante, como vercupo() :791. */
    public function soldoutsRecientes(int $productoId, int $categoriaId): array
    {
        return DB::table('soldout')
            ->where('fk_producto_id', $productoId)->where('fk_tarifacategoria_id', $categoriaId)
            ->where('vigencia_fin', '>=', now()->subMonths(3)->format('Y-m-d'))
            ->orderByDesc('vigencia_fin')
            ->get()->map(fn ($s) => (array) $s)->all();
    }

    /** @param list<int|string> $ids */
    public function eliminarCupos(array $ids): int
    {
        return DB::table('cupo')->whereIn('cupo_id', $this->ids($ids))->delete();
    }

    /** @param list<int|string> $ids */
    public function eliminarSoldouts(array $ids): int
    {
        return DB::table('soldout')->whereIn('soldout_id', $this->ids($ids))->delete();
    }

    // ------------------------------------------------------------------

    /** B10: sólo enteros positivos llegan al IN (...). */
    private function ids(array $ids): array
    {
        $ok = array_values(array_unique(array_filter(array_map(fn ($i) => ctype_digit((string) $i) ? (int) $i : 0, $ids), fn ($i) => $i > 0)));
        if ($ok === []) {
            throw CupoException::porCampos(['ids' => 'No se indicó ningún id válido.']);
        }

        return $ok;
    }

    /** @return array{0:string,1:string} */
    private function rango(array $datos): array
    {
        $ini = $this->fecha($datos['vigencia_ini'] ?? '');
        $fin = $this->fecha($datos['vigencia_fin'] ?? '');
        $errores = [];
        if ($ini === '') {
            $errores['vigencia_ini'] = 'La fecha de inicio es obligatoria.';
        }
        if ($fin === '') {
            $errores['vigencia_fin'] = 'La fecha de fin es obligatoria.';
        }
        if ($ini !== '' && $fin !== '' && $fin < $ini) {
            $errores['vigencia_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }
        if ($errores !== []) {
            throw CupoException::porCampos($errores);
        }

        return [$ini, $fin];
    }

    private function fecha(mixed $v): string
    {
        $v = trim((string) $v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            $v = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $v = substr($v, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return '';
        }
        $c = Carbon::createFromFormat('Y-m-d', $v);

        return $c && $c->format('Y-m-d') === $v ? $v : '';
    }

    /** @return list<string> */
    private function dias(string $ini, string $fin): array
    {
        $out = [];
        $c = Carbon::createFromFormat('Y-m-d', $ini);
        $f = Carbon::createFromFormat('Y-m-d', $fin);
        while ($c->lte($f)) {
            $out[] = $c->format('Y-m-d');
            $c->addDay();
        }

        return $out;
    }

    /** @return list<string> */
    private function interseccion(string $ini, string $fin, array $dias): array
    {
        $ini = substr($ini, 0, 10);
        $fin = substr($fin, 0, 10);

        return array_values(array_filter($dias, fn ($d) => $d >= $ini && $d <= $fin));
    }
}
