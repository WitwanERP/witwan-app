<?php

namespace App\Http\Controllers\Web\Reportes;

use Illuminate\Support\Facades\DB;

/** Administración > Reportes > Gastos de reserva (CI: reportes/reportegastosreserva): files cerrados con gastos. */
class GastosReservaController extends ReporteController
{
    protected string $titulo = 'Gastos de reserva';

    protected string $ruta = 'admin/reportes/gastos-reserva';

    protected function filtros(): array
    {
        return [
            ['campo' => 'fecha_alta', 'label' => 'Fecha de alta', 'tipo' => 'rango'],
            ['campo' => 'fecha_in', 'label' => 'Fecha in', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'File'],
            ['campo' => 'fecha_alta', 'label' => 'Fecha alta'],
            ['campo' => 'inicio', 'label' => 'Fecha in'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'gastos', 'label' => 'Gastos', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        // El legacy sólo consulta si vino alguna fecha "desde".
        if ($f['fecha_alta'] === '' && $f['fecha_in'] === '') {
            return [];
        }
        $q = DB::table('reserva as r')
            ->join('cliente as c', 'c.cliente_id', '=', 'r.fk_cliente_id')
            ->join('filestatus as fs', 'fs.filestatus_id', '=', 'r.fk_filestatus_id')
            ->join('reservain as ri', 'ri.fk_reserva_id', '=', 'r.reserva_id')
            ->where('r.gastos', '<>', 0)->where('r.fk_filestatus_id', 'CL')
            ->groupBy('r.reserva_id')
            ->select('r.reserva_id', 'r.tipocodigo', 'r.codigo', 'r.fecha_alta', 'fs.filestatus_nombre', 'r.fk_moneda_id', 'r.gastos', 'c.cliente_nombre', DB::raw('MIN(ri.inicio) AS inicio'));
        $this->rango($q, 'r.fecha_alta', $f, 'fecha_alta', true);
        $this->rango($q, 'ri.inicio', $f, 'fecha_in');

        return $q->get()->map(fn ($r) => [
            'codigo' => "{$r->tipocodigo}-{$r->codigo}",
            'fecha_alta' => $this->dmy((string) $r->fecha_alta),
            'inicio' => $this->dmy((string) $r->inicio),
            'status' => $r->filestatus_nombre,
            'cliente' => $r->cliente_nombre,
            'moneda' => $r->fk_moneda_id,
            'gastos' => (float) $r->gastos,
        ])->all();
    }
}
