<?php

namespace App\Services\Operaciones;

use Illuminate\Support\Facades\DB;

/**
 * Lectura/escritura de `servicio_extra` (EAV por servicio: pickup, dropoff,
 * idioma, ocultaritinerario, pickup_nvuelo…). Réplica de
 * Servicio_model::set_extra(): idempotente (borra la clave y reinserta) y no
 * guarda vacíos; '0' sí es un valor válido.
 */
class ServicioExtraService
{
    /**
     * Extras de varios servicios: [servicio_id => [nombre => valor]]. Si una
     * clave está repetida (el legacy insertaba sin borrar), gana la última.
     *
     * @param  list<int>  $servicioIds
     * @return array<int,array<string,string>>
     */
    public function deServicios(array $servicioIds): array
    {
        if ($servicioIds === []) {
            return [];
        }

        $out = [];
        $filas = DB::table('servicio_extra')
            ->whereIn('fk_servicio_id', $servicioIds)
            ->orderBy('regdate')
            ->get(['fk_servicio_id', 'extra_nombre', 'extra_valor']);
        foreach ($filas as $f) {
            $out[(int) $f->fk_servicio_id][(string) $f->extra_nombre] = (string) $f->extra_valor;
        }

        return $out;
    }

    public function set(int $servicioId, string $nombre, mixed $valor): void
    {
        if ($servicioId <= 0 || $nombre === '') {
            return;
        }

        DB::table('servicio_extra')->where('fk_servicio_id', $servicioId)->where('extra_nombre', $nombre)->delete();

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return;
        }

        DB::table('servicio_extra')->insert([
            'fk_servicio_id' => $servicioId,
            'extra_nombre' => $nombre,
            'extra_valor' => (string) $valor,
            'regdate' => now()->toDateTimeString(),
        ]);
    }

    public function borrar(int $servicioId, string $nombre): void
    {
        DB::table('servicio_extra')->where('fk_servicio_id', $servicioId)->where('extra_nombre', $nombre)->delete();
    }
}
