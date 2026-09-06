<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Services\CatalogosService;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Reportes > Facturas impagas (CI: reportes/facturasaldo).
 * Réplica del criterio del legacy (pensado para CL: total = exento + gravado
 * * 1.19, recibos en CLP): facturas no anuladas cuyo cobrado imputado no
 * cubre el total, con los recibos del file que aún tienen saldo.
 */
class FacturasImpagasController extends ReporteController
{
    protected string $titulo = 'Facturas impagas';

    protected string $ruta = 'admin/reportes/facturas-impagas';

    protected ?string $agruparPor = null;

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtros(): array
    {
        $tipos = DB::table('reserva')->where('tipocodigo', '<>', '')->whereNotIn('tipocodigo', ['DV', 'CM'])->distinct()->orderBy('tipocodigo')->pluck('tipocodigo')
            ->map(fn ($t) => ['value' => $t, 'label' => $t])->values()->all();

        return [
            ['campo' => 'cliente', 'label' => 'Cliente', 'tipo' => 'select', 'opciones' => $this->catalogos->clientes()],
            ['campo' => 'vendedor', 'label' => 'Vendedor', 'tipo' => 'select', 'opciones' => $this->catalogos->usuariosInternos()],
            ['campo' => 'factura_nro', 'label' => 'N° factura', 'tipo' => 'text'],
            ['campo' => 'fecha', 'label' => 'Fecha de factura', 'tipo' => 'rango'],
            ['campo' => 'vencimiento_pago', 'label' => 'Vencimiento de pago', 'tipo' => 'rango'],
            ['campo' => 'tiporeserva', 'label' => 'Tipo de file', 'tipo' => 'multi', 'opciones' => $tipos],
            ['campo' => 'conrecibos', 'label' => 'Con recibos pendientes', 'tipo' => 'bool'],
            ['campo' => 'precision', 'label' => 'Saldo mínimo', 'tipo' => 'text', 'default' => '1'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'factura', 'label' => 'Factura'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'vendedor', 'label' => 'Vendedor'],
            ['campo' => 'cliente', 'label' => 'Cliente'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'vencimiento', 'label' => 'Vencimiento'],
            ['campo' => 'monto', 'label' => 'Monto', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num', 'total' => true],
            ['campo' => 'recibos', 'label' => 'Recibos con saldo', 'tipo' => 'pre'],
        ];
    }

    protected function consultar(array $f): array
    {
        $precision = is_numeric($f['precision']) ? (float) $f['precision'] : 1;
        $cobradoSub = '(SELECT SUM(rfr.monto) FROM rel_facturarecibo rfr WHERE rfr.fk_factura_id = fc.factura_id)';
        $totalExpr = 'ROUND(fc.factura_conceptos_exentos + fc.factura_conceptos_gravados * 1.19)';

        $q = DB::table('factura as fc')
            ->join('cliente as c', 'c.cliente_id', '=', 'fc.fk_cliente_id')
            ->join('rel_filefactura as rff', 'rff.fk_factura_id', '=', 'fc.factura_id')
            ->join('reserva as r', 'r.reserva_id', '=', 'rff.fk_file_id')
            ->join('usuario as u', 'u.usuario_id', '=', 'r.agente')
            ->leftJoin('rel_facturarecibo as rfr0', 'rfr0.fk_factura_id', '=', 'fc.factura_id')
            ->where('fc.factura_id', '<>', 0)
            ->whereNotIn('fc.statusfactura', ['AN', 'NU'])
            ->whereRaw("((ISNULL(rfr0.monto) AND r.total > ?) OR ({$totalExpr} - IFNULL({$cobradoSub}, 0)) > ?)", [$precision, $precision])
            ->groupBy('fc.factura_id')
            ->orderBy('fc.factura_fecha')
            ->select('fc.factura_id', 'fc.factura_fecha', 'fc.factura_nro', 'fc.vencimiento_pago', 'c.cliente_nombre', 'r.reserva_id', 'r.tipocodigo', 'r.codigo',
                DB::raw("{$totalExpr} AS total_factura"), DB::raw("{$cobradoSub} AS cobrado"),
                DB::raw("CONCAT(u.usuario_nombre, ' ', u.usuario_apellido) AS vendedor"));

        if ($f['cliente'] !== '') {
            $q->where('c.cliente_id', (int) $f['cliente']);
        }
        if ($f['vendedor'] !== '') {
            $q->where('r.agente', (int) $f['vendedor']);
        }
        if ($f['factura_nro'] !== '') {
            $q->where('fc.factura_nro', (int) $f['factura_nro']);
        }
        if ($f['fecha'] !== '') {
            $q->where('fc.factura_fecha', '>=', $f['fecha'].' 00:00:00');
        }
        if ($f['fecha_to'] !== '') {
            $q->where('fc.factura_fecha', '<=', $f['fecha_to'].' 23:59:59');
        }
        if ($f['vencimiento_pago'] !== '') {
            $q->where('fc.vencimiento_pago', '>=', $f['vencimiento_pago'].' 00:00:00');
        }
        if ($f['vencimiento_pago_to'] !== '') {
            $q->where('fc.vencimiento_pago', '<=', $f['vencimiento_pago_to'].' 23:59:59');
        }
        if ($f['tiporeserva'] !== [] && $f['tiporeserva'] !== '') {
            $q->whereIn('r.tipocodigo', (array) $f['tiporeserva']);
        }
        if (Licencia::es('witwan_rays')) {
            $q->where('fc.factura_tipo', '<>', 'X');
        }

        $filas = [];
        foreach ($q->get() as $x) {
            $recibos = $this->recibosConSaldo((int) $x->reserva_id);
            if ($f['conrecibos'] === '1' && $recibos === []) {
                continue;
            }
            if ($f['conrecibos'] === '0' && $recibos !== []) {
                continue;
            }
            $total = (float) $x->total_factura;
            $filas[] = [
                'factura' => $x->factura_nro,
                'file' => "{$x->tipocodigo}-{$x->codigo}",
                'vendedor' => $x->vendedor,
                'cliente' => $x->cliente_nombre,
                'fecha' => $this->dmy(substr((string) $x->factura_fecha, 0, 10)),
                'vencimiento' => $this->dmy(substr((string) $x->vencimiento_pago, 0, 10)),
                'monto' => $total,
                'saldo' => round($total - (float) $x->cobrado, 2),
                'recibos' => implode("\n", array_map(fn ($r) => "{$r['nro']} (saldo {$r['saldo']})", $recibos)),
            ];
        }

        return $filas;
    }

    /** @return list<array{nro:string,saldo:float}> */
    private function recibosConSaldo(int $reservaId): array
    {
        $imputado = '(SELECT ROUND(IFNULL(SUM(x.monto), 0)) FROM rel_facturarecibo x WHERE x.fk_recibo_id = rc.recibo_id)';
        $imputadoAbs = '(SELECT ROUND(SUM(ABS(x.monto))) FROM rel_facturarecibo x WHERE x.fk_recibo_id = rc.recibo_id)';
        $enClp = "IF(m.fk_moneda_id <> 'CLP', m.cotizacion_moneda, 1)";

        return DB::table('recibo as rc')
            ->join('rel_filerecibo as rfr', 'rfr.fk_recibo_id', '=', 'rc.recibo_id')
            ->join('movimiento as m', 'm.fk_recibo_id', '=', 'rc.recibo_id')
            ->leftJoin('rel_facturarecibo as rfa', 'rfa.fk_recibo_id', '=', 'rc.recibo_id')
            ->where('rc.statusrecibo', '<>', 'AN')
            ->where('rfr.fk_file_id', $reservaId)
            ->where('rc.monto', '<>', 0)
            ->whereRaw("(ISNULL(rfa.fk_recibo_id) OR (ROUND(ABS(rc.monto) * {$enClp}) - {$imputadoAbs}) <> 0)")
            ->groupBy('rc.recibo_id')
            ->select('rc.recibo_id', 'rc.recibo_nro', DB::raw("(ROUND(rc.monto * {$enClp}) - {$imputado}) AS saldo"))
            ->get()
            ->map(fn ($r) => ['nro' => (string) $r->recibo_nro, 'saldo' => (float) $r->saldo])
            ->all();
    }
}
