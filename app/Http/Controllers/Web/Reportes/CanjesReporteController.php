<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Canjes por período (CI: reportes/canjes).
 * El legacy declaraba los filtros pero nunca los aplicaba (siempre últimos 30
 * días); acá se aplican y el rango de 30 días queda como default.
 */
class CanjesReporteController extends ReporteController
{
    protected string $titulo = 'Canjes por período';

    protected string $ruta = 'admin/reportes/canjes';

    protected bool $requiereFiltros = false;

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        $cadenas = DB::table('cadenahotelera')->orderBy('cadenahotelera_nombre')->get(['cadenahotelera_id', 'cadenahotelera_nombre'])
            ->map(fn ($c) => ['value' => (int) $c->cadenahotelera_id, 'label' => $c->cadenahotelera_nombre])->all();

        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'fk_cadenahotelera_id', 'label' => 'Cadena hotelera', 'tipo' => 'select', 'opciones' => $cadenas],
            ['campo' => 'fk_prestador_id', 'label' => 'Prestador', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
            ['campo' => 'vigencia_ini', 'label' => 'Fecha in (sin fecha: últimos 30 días)', 'tipo' => 'rango'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'codigo', 'label' => 'File'],
            ['campo' => 'titular', 'label' => 'Titular'],
            ['campo' => 'servicio', 'label' => 'Servicio'],
            ['campo' => 'fecha_in', 'label' => 'Fecha in'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'total', 'label' => 'Total', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->where('s.fk_tipoproducto_id', 'CAN')
            ->select('s.servicio_id', 's.servicio_nombre', 's.vigencia_ini', 's.fk_moneda_id', 's.total', 'r.tipocodigo', 'r.codigo', 'r.titular_apellido', 'r.titular_nombre', 'p.proveedor_nombre');

        if ($f['fk_proveedor_id'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }
        if ($f['fk_prestador_id'] !== '') {
            $q->where('s.fk_prestador_id', (int) $f['fk_prestador_id']);
        }
        if ($f['fk_cadenahotelera_id'] !== '') {
            $q->where('p.fk_cadenahotelera_id', (int) $f['fk_cadenahotelera_id']);
        }
        if ($f['vigencia_ini'] === '' && $f['vigencia_ini_to'] === '') {
            $q->whereBetween('s.vigencia_ini', [now()->subDays(30)->toDateString(), now()->toDateString()]);
        } else {
            $this->rango($q, 's.vigencia_ini', $f, 'vigencia_ini');
        }

        return $q->orderBy('s.fk_moneda_id')->orderBy('s.vigencia_ini')->get()->map(fn ($x) => [
            'proveedor' => $x->proveedor_nombre,
            'codigo' => "{$x->tipocodigo}-{$x->codigo}",
            'titular' => trim("{$x->titular_apellido} {$x->titular_nombre}"),
            'servicio' => $x->servicio_nombre,
            'fecha_in' => $this->dmy($x->vigencia_ini),
            'moneda' => $x->fk_moneda_id,
            'total' => (float) $x->total,
        ])->all();
    }
}
