<?php

namespace App\Services\Contabilidad;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Libro diario (CI: administracion/libros::diario). Recorre `movimiento` (no
 * auxiliar, monto ≠ 0, sin órdenes/recibos anulados) y arma un renglón por
 * movimiento con cuenta, debe/haber y comprobante según el documento que lo
 * originó, convertido a la moneda pedida con la cotización "relación" vigente
 * a la fecha del movimiento (o `cotizacion_moneda` del propio movimiento).
 * Agrupa por asiento (`fk_asientocontable_id`, o el documento cuando no hay).
 *
 * Sólo la base del tenant (el CI unía las tres de secontur).
 */
class LibroDiarioService
{
    public function __construct(private CotizacionService $cotizaciones, private CuentasRecibosService $ctasRecibos) {}

    /**
     * @return list<array{asiento_clave:string,asiento:string,fecha:string,comprobante:string,cuenta:string,file:string,debe:float,haber:float,descripcion:string}>
     */
    public function filas(string $desde, string $hasta, int $nroAsiento, string $moneda, bool $soloFacturas): array
    {
        $q = DB::table('movimiento')
            ->leftJoin('recibo', 'recibo.recibo_id', '=', 'movimiento.fk_recibo_id')
            ->leftJoin('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->where('movimiento.movimiento_id', '<>', 0)->where('movimiento.monto', '<>', 0)->whereIn('movimiento.auxiliar', [0, 1])
            ->where(fn ($w) => $w->where('ordenadmin.status', '<>', 'AN')->orWhereNull('ordenadmin.status'))
            ->where(fn ($w) => $w->where('recibo.statusrecibo', '<>', 'AN')->orWhereNull('recibo.statusrecibo'))
            ->select('movimiento.*')
            ->orderBy('movimiento.fecha')->orderByDesc('movimiento.cuenta_debito')->orderBy('movimiento.movimiento_id');
        if ($hasta !== '') {
            $q->where('movimiento.fecha', '<=', $hasta);
        }
        if ($desde !== '') {
            $q->where('movimiento.fecha', '>=', $desde);
        }
        if ($soloFacturas) {
            $q->where('movimiento.fk_factura_id', '<>', 0);
        }
        if ($nroAsiento !== 0) {
            $q->where('movimiento.fk_asientocontable_id', $nroAsiento);
        }

        $basica = $this->cotizaciones->monedaBasica();
        $ctasRecibos = $this->ctasRecibos->ids();
        $nombres = DB::table('plancuenta')->pluck('plancuenta_nombre', 'plancuenta_id');
        $grupos = [];

        foreach ($q->get() as $r) {
            $rel = $this->relacion($r, $moneda, $basica);
            $monto = (float) $r->monto * $rel;
            $file = $r->fk_file_id != 0 ? (string) DB::table('reserva')->where('reserva_id', (int) $r->fk_file_id)->selectRaw("CONCAT(tipocodigo, '-', codigo) AS c")->value('c') : '';
            [$key, $comp, $cta, $debe, $haber] = $this->clasificar($r, $monto, $ctasRecibos);
            if ($debe < 0) {
                $haber = abs($debe);
                $debe = 0;
            }
            if ($haber < 0) {
                $debe = abs($haber);
                $haber = 0;
            }
            $nkey = (int) $r->fk_asientocontable_id !== 0 ? (string) $r->fk_asientocontable_id : $key;
            $clave = strtotime((string) $r->fecha).'-'.$nkey;
            $grupos[$clave][] = [
                'asiento_clave' => $clave,
                'asiento' => $nkey,
                'fecha' => $this->dmy((string) $r->fecha),
                'comprobante' => $comp,
                'cuenta' => (string) ($nombres[$cta] ?? $cta),
                'file' => $file,
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'descripcion' => trim((string) $r->descripcion.' '.(string) $r->operacion),
            ];
        }
        ksort($grupos, SORT_NATURAL);

        return array_merge(...array_values($grupos) ?: [[]]);
    }

    /** @return array{0:string,1:string,2:int,3:float,4:float} [clave doc, comprobante, cuenta, debe, haber] */
    private function clasificar(object $r, float $monto, array $ctasRecibos): array
    {
        $debe = 0.0;
        $haber = 0.0;
        if ($r->fk_recibo_id != 0) {
            $key = 'R'.$r->fk_recibo_id;
            $comp = 'RECIBO #'.DB::table('recibo')->where('recibo_id', (int) $r->fk_recibo_id)->value('recibo_nro');
            if (in_array((int) $r->cuenta_debito, $ctasRecibos, true) && (int) $r->fk_plancuenta_id !== 0) {
                $debe = $monto;
                $cta = (int) $r->fk_plancuenta_id;
            } else {
                $haber = $monto;
                $cta = (int) $r->cuenta_debito !== 0 ? (int) $r->cuenta_debito : (int) $r->fk_plancuenta_id;
            }
        } elseif ($r->fk_notadebito_id != 0) {
            $key = 'ND'.$r->fk_notadebito_id;
            $comp = 'ND #'.DB::table('notadebito')->where('notadebito_id', (int) $r->fk_notadebito_id)->value('notadebito_nro');
            [$cta, $debe, $haber] = (int) $r->cuenta_debito !== 0 ? [(int) $r->cuenta_debito, $monto, 0.0] : [(int) $r->fk_plancuenta_id, 0.0, $monto];
        } elseif ($r->fk_notacredito_id != 0) {
            $key = 'NC'.$r->fk_notacredito_id;
            $comp = 'NC #'.DB::table('notacredito')->where('notacredito_id', (int) $r->fk_notacredito_id)->value('notacredito_nro');
            [$cta, $debe, $haber] = (int) $r->cuenta_debito !== 0 ? [(int) $r->cuenta_debito, $monto, 0.0] : [(int) $r->fk_plancuenta_id, 0.0, $monto];
        } elseif ($r->fk_factura_id != 0) {
            $key = 'F'.$r->fk_factura_id;
            $comp = 'FACTURA #'.DB::table('factura')->where('factura_id', (int) $r->fk_factura_id)->value('factura_nro');
            [$cta, $debe, $haber] = (int) $r->cuenta_debito !== 0 ? [(int) $r->cuenta_debito, $monto, 0.0] : [(int) $r->cuenta_credito, 0.0, $monto];
        } elseif ($r->fk_ordenadmin_id != 0) {
            $key = 'O'.$r->fk_ordenadmin_id;
            $oa = DB::table('ordenadmin')->where('ordenadmin_id', (int) $r->fk_ordenadmin_id)->first(['tipo', 'nropago']);
            $tipo = (string) ($oa->tipo ?? '');
            $comp = (in_array($tipo, ['M', 'A'], true) ? 'MOVIMIENTO' : ($tipo === 'C' ? 'CC' : 'ORDEN')).'# '.($oa->nropago ?? '');
            $cta = (int) $r->fk_plancuenta_id;
            if ((int) $r->cuenta_debito === 0) {
                $debe = $monto;
            } else {
                if (in_array($tipo, ['M', 'A'], true)) {
                    $cta = (int) $r->cuenta_debito;
                }
                $haber = $monto;
            }
        } elseif ($r->fk_facturaproveedor_id != 0) {
            $key = 'O'.$r->fk_facturaproveedor_id;
            $comp = 'FC3# '.DB::table('facturaproveedor')->where('facturaproveedor_id', (int) $r->fk_facturaproveedor_id)->value('facturaproveedor_nro');
            $cta = (int) $r->fk_plancuenta_id;
            (int) $r->cuenta_debito === 0 ? $haber = $monto : $debe = $monto;
        } else {
            $key = 'M'.$r->movimiento_id;
            $comp = 'ASIENTO';
            [$cta, $debe, $haber] = (int) $r->cuenta_debito !== 0 ? [(int) $r->cuenta_debito, $monto, 0.0] : [(int) ($r->fk_plancuenta_id ?: $r->cuenta_credito), 0.0, $monto];
        }

        return [$key, $comp, $cta, $debe, $haber];
    }

    /** Factor para llevar el monto del movimiento a $moneda (diario del CI). */
    private function relacion(object $r, string $moneda, string $basica): float
    {
        if ($moneda === (string) $r->fk_moneda_id) {
            return 1.0;
        }
        $fecha = substr((string) $r->fecha, 0, 10);
        $m1 = $moneda === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($moneda, $fecha) ?: 1.0);
        $m2 = (string) $r->fk_moneda_id === $basica ? 1.0 : ($this->cotizaciones->aLaVenta((string) $r->fk_moneda_id, $fecha) ?: 1.0);
        if ((float) $r->cotizacion_moneda != 0) {
            $m2 = (float) $r->cotizacion_moneda;
        }

        return $m2 / $m1;
    }

    private function dmy(string $f): string
    {
        return $f === '' ? '' : substr($f, 8, 2).'/'.substr($f, 5, 2).'/'.substr($f, 0, 4);
    }
}
