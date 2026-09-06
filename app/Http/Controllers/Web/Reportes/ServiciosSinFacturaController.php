<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Servicios pagados sin factura de proveedor
 * (CI: reportes/serviciosinfactura): servicios con orden de pago que no
 * tienen factura de proveedor imputada (excluye aéreos con PNR).
 */
class ServiciosSinFacturaController extends ReporteController
{
    protected string $titulo = 'Servicios pagados sin factura';

    protected string $ruta = 'admin/reportes/servicios-sin-factura';

    protected bool $requiereFiltros = false;

    protected int $limite = 1000;

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        return [
            ['campo' => 'fk_proveedor_id', 'label' => 'Proveedor', 'tipo' => 'select', 'opciones' => $this->catalogos->proveedores()],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'codigo', 'label' => 'File'],
            ['campo' => 'alta', 'label' => 'Alta'],
            ['campo' => 'servicio', 'label' => 'Servicio'],
            ['campo' => 'op', 'label' => 'OP'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'proveedor', 'label' => 'Proveedor'],
            ['campo' => 'in', 'label' => 'In'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'costo', 'label' => 'Costo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'iva_costo', 'label' => 'IVA costo', 'tipo' => 'num', 'total' => true],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('servicio as s')
            ->join('proveedor as p', 'p.proveedor_id', '=', 's.fk_proveedor_id')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->join('usuario as v', 'v.usuario_id', '=', 'r.agente')
            ->join('rel_ordenadminocupacion as ro', 'ro.fk_ocupacion_id', '=', 's.servicio_id')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'ro.fk_ordenadmin_id')
            ->whereNotIn('s.status', ['CA', 'RQ'])
            ->whereNotIn('s.servicio_id', DB::table('pnraereo')->select('fk_ocupacion_id'))
            ->whereNotIn('s.servicio_id', DB::table('rel_facturaproveedorocupacion')->select('fk_ocupacion_id')->distinct())
            ->where('oa.tipo', 'P')
            ->orderBy('p.proveedor_nombre')->orderBy('s.vigencia_ini')
            ->select('s.fk_reserva_id', 'p.proveedor_nombre', 'r.fecha_alta', 'r.tipocodigo', 'r.codigo', 's.vigencia_ini', 's.moneda_costo', 's.costo', 's.iva_costo', 's.servicio_nombre', 'oa.nropago',
                DB::raw("CONCAT(v.usuario_nombre, ' ', v.usuario_apellido) AS vendedor"));
        if ($f['fk_proveedor_id'] !== '') {
            $q->where('s.fk_proveedor_id', (int) $f['fk_proveedor_id']);
        }
        $this->limitar($q);

        return $q->get()->map(fn ($r) => [
            'codigo' => "{$r->tipocodigo}-{$r->codigo}",
            'alta' => $this->dmy($r->fecha_alta),
            'servicio' => $r->servicio_nombre,
            'op' => $r->nropago,
            'vendedor' => trim((string) $r->vendedor),
            'proveedor' => $r->proveedor_nombre,
            'in' => $this->dmy($r->vigencia_ini),
            'moneda' => $r->moneda_costo,
            'costo' => (float) $r->costo,
            'iva_costo' => (float) $r->iva_costo,
        ])->all();
    }
}
