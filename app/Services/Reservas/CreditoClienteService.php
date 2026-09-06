<?php

namespace App\Services\Reservas;

use App\Helpers\SysconfigHelper;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Crédito utilizado por un cliente, en moneda básica: facturas no anuladas
 * (total menos lo aplicado por recibos) más los files CO/CL con servicios sin
 * facturar (total menos cobrado, convertido). Mismo cálculo que
 * reportes/controlcredito del CI, reutilizado por el generador de reservas.
 */
class CreditoClienteService
{
    public function __construct(private CotizacionService $cotizaciones) {}

    public function utilizado(int $clienteId): float
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 19);
        $coef = 1 + ($tasa < 1 ? $tasa : $tasa / 100);
        $usado = 0.0;

        $facturas = DB::table('factura')->where('fk_cliente_id', $clienteId)->whereNotIn('statusfactura', ['AN', 'NU'])
            ->select([
                DB::raw("ROUND(factura_conceptos_gravados * {$coef} + factura_conceptos_gravadosespecial * 1.105 + factura_conceptos_exentos + factura_conceptos_nogravados + factura_impuesto1, 0)
                    + IF(factura_fecha > '2015-12-17', factura_rgterrestres, 0)
                    - CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(factura.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)) AS total"),
                DB::raw('(SELECT COALESCE(SUM(rf.monto), 0) FROM rel_facturarecibo rf WHERE rf.fk_factura_id = factura.factura_id) AS aplicado'),
            ])->get();
        foreach ($facturas as $fc) {
            $usado += (float) $fc->total - (float) $fc->aplicado;
        }

        $basica = $this->cotizaciones->monedaBasica();
        $ctz = [];
        $files = DB::table('reserva as r')
            ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
            ->where('r.fk_cliente_id', $clienteId)->whereIn('r.fk_filestatus_id', ['CL', 'CO'])
            ->whereColumn('r.total', '<>', 'r.cobrado')
            ->where('s.status', '<>', 'CA')
            ->whereNotIn('s.servicio_id', fn ($sub) => $sub->selectRaw('DISTINCT fk_servicio_id')->from('rel_serviciofactura'))
            ->groupBy('r.reserva_id')
            ->get(['r.reserva_id', 'r.total', 'r.cobrado', 'r.fk_moneda_id']);
        foreach ($files as $fl) {
            $m = (string) $fl->fk_moneda_id;
            $ctz[$m] ??= $m === $basica ? 1.0 : $this->cotizaciones->aLaVenta($m);
            $usado += ((float) $fl->total - (float) $fl->cobrado) * $ctz[$m];
        }

        return round($usado, 2);
    }
}
