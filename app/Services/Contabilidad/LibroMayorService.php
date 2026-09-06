<?php

namespace App\Services\Contabilidad;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Libro mayor (CI: administracion/libros::mayor). Para las cuentas elegidas
 * recorre todos sus movimientos (no auxiliares, sin órdenes/recibos anulados),
 * acumula lo anterior a "desde" como saldo anterior y lista lo que cae en el
 * rango con debe/haber y saldo acumulado del período (como el CI: el saldo de
 * la columna no incluye el anterior). Signo: ingreso si hay cuenta débito o
 * ambas, egreso si sólo hay crédito, y se invierte cuando el movimiento viene
 * de una orden (`fk_ordenadmin_id`). Conversión a la moneda pedida con la
 * cotización "relación" a la fecha, salvo que el movimiento traiga la suya.
 */
class LibroMayorService
{
    public function __construct(private CotizacionService $cotizaciones) {}

    /**
     * @param  list<int>  $cuentas
     * @return array{filas:list<array<string,mixed>>,anteriores:array<int,float>}
     */
    public function filas(array $cuentas, string $desde, string $hasta, string $acumulado, string $moneda): array
    {
        $cuentas = array_values(array_filter(array_map('intval', $cuentas)));
        if ($cuentas === []) {
            return ['filas' => [], 'anteriores' => []];
        }
        $q = DB::table('movimiento')
            ->leftJoin('recibo', 'recibo.recibo_id', '=', 'movimiento.fk_recibo_id')
            ->leftJoin('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->where(fn ($w) => $w->whereIn('movimiento.fk_plancuenta_id', $cuentas)->orWhere(fn ($w2) => $w2->whereIn('movimiento.cuenta_debito', $cuentas)->where('movimiento.fk_plancuenta_id', 0)))
            ->where('movimiento.monto', '<>', 0)->whereIn('movimiento.auxiliar', [0, 1])
            ->where(fn ($w) => $w->where('ordenadmin.status', '<>', 'AN')->orWhereNull('ordenadmin.status'))
            ->where(fn ($w) => $w->where('recibo.statusrecibo', '<>', 'AN')->orWhereNull('recibo.statusrecibo'))
            ->select('movimiento.*', 'ordenadmin.tipo as orden_tipo', 'ordenadmin.nropago as orden_nro', 'ordenadmin.observaciones as orden_obs', 'ordenadmin.fk_moneda_id as orden_moneda',
                'recibo.recibo_nro', 'recibo.observaciones as recibo_obs', 'recibo.fk_moneda_id as recibo_moneda')
            ->orderBy('movimiento.fecha')->orderBy('movimiento.fk_asientocontable_id')->orderBy('movimiento.movimiento_id');
        if ($acumulado !== '') {
            $q->where('movimiento.fecha', '>=', $acumulado);
        }

        $basica = $this->cotizaciones->monedaBasica();
        $dec = $moneda === 'CLP' ? 0 : 2;
        $nombres = DB::table('plancuenta')->whereIn('plancuenta_id', $cuentas)->pluck('plancuenta_nombre', 'plancuenta_id');
        $anteriores = array_fill_keys($cuentas, 0.0);
        $saldo = 0.0;
        $filas = [];

        foreach ($q->get() as $r) {
            $fecha = substr((string) $r->fecha, 0, 10);
            $rel = $this->relacion($r, $moneda, $basica);
            $importe = round((float) $r->monto * $rel, $dec);

            $ingreso = true;
            $ctaAcumula = (int) $r->fk_plancuenta_id;
            if ((int) $r->cuenta_credito !== 0 && (int) $r->cuenta_debito !== 0) {
                $ingreso = true;
            } elseif ((int) $r->cuenta_credito !== 0) {
                $ingreso = false;
                $ctaAcumula = (int) $r->cuenta_credito;
            } elseif ((int) $r->cuenta_debito !== 0) {
                $ctaAcumula = (int) $r->cuenta_debito;
            }
            if ((int) $r->fk_ordenadmin_id !== 0) {
                $ingreso = ! $ingreso;
            }
            $antes = $desde !== '' && $fecha < $desde;
            $despues = $hasta !== '' && $fecha > $hasta;
            $debe = 0.0;
            $haber = 0.0;
            if ($antes) {
                $anteriores[$ctaAcumula] = ($anteriores[$ctaAcumula] ?? 0.0) + ($ingreso ? $importe : -$importe);
            } else {
                if ($ingreso) {
                    $importe > 0 ? $debe = abs($importe) : $haber = abs($importe);
                    $saldo += $importe;
                } else {
                    $importe > 0 ? $haber = abs($importe) : $debe = abs($importe);
                    $saldo -= $importe;
                }
            }
            if ($antes || $despues) {
                continue;
            }

            [$comp, $link, $info, $monedaComp, $fileId] = $this->comprobante($r);
            $file = '';
            if ((int) $fileId !== 0 && (int) $fileId !== 1) {
                $file = (string) DB::table('reserva')->where('reserva_id', (int) $fileId)->selectRaw("CONCAT(tipocodigo, '-', codigo) AS c")->value('c');
            }
            if ($file === '99-1') {
                $file = 'ADMIN';
            }
            $cta = (int) $r->fk_plancuenta_id ?: ((int) $r->cuenta_debito ?: (int) $r->cuenta_credito);
            $filas[] = [
                'cuenta' => (string) ($nombres[$cta] ?? $cta),
                'cuenta_id' => $cta,
                'fecha' => $this->dmy($fecha),
                'comprobante' => $comp,
                'comprobante_link' => $link,
                'descripcion' => trim((string) $r->descripcion.' '.(string) $r->operacion.' '.(string) $r->banco.' '.$info),
                'asiento' => (int) $r->fk_asientocontable_id,
                'file' => $file,
                'moneda' => $monedaComp,
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'saldo' => round($saldo, 2),
            ];
        }

        return ['filas' => $filas, 'anteriores' => array_map(fn ($v) => round($v, 2), $anteriores)];
    }

    /** @return array{0:string,1:?string,2:string,3:string,4:int} */
    private function comprobante(object $r): array
    {
        $info = '';
        $comp = '';
        $link = null;
        $monedaComp = '';
        $fileId = (int) $r->fk_file_id;
        if ($r->fk_factura_id != 0 && $r->fk_notacredito_id == 0) {
            $f = DB::table('factura')->where('factura_id', (int) $r->fk_factura_id)->first(['factura_tipo', 'factura_nro', 'observaciones']);
            if ($f) {
                $comp = "FACTURA #{$f->factura_tipo} {$f->factura_nro}";
                $link = "/administracion/factura/imprimir/{$r->fk_factura_id}";
                $info = (string) $f->observaciones;
            }
        } elseif ($r->fk_facturaproveedor_id != 0) {
            $fp = DB::table('facturaproveedor')->where('facturaproveedor_id', (int) $r->fk_facturaproveedor_id)->first(['facturaproveedor_tipodocumento', 'facturaproveedor_nro', 'descripcion']);
            if ($fp) {
                $comp = "{$fp->facturaproveedor_tipodocumento} 3OS #{$fp->facturaproveedor_nro}";
                $link = "/administracion/factura3ero/imprimir/{$r->fk_facturaproveedor_id}";
                $info = (string) $fp->descripcion;
            }
            $rid = DB::table('servicio')->join('rel_facturaproveedorocupacion as rfo', 'rfo.fk_ocupacion_id', '=', 'servicio.servicio_id')
                ->where('rfo.fk_facturaproveedor_id', (int) $r->fk_facturaproveedor_id)->value('servicio.fk_reserva_id');
            if ($rid) {
                $fileId = (int) $rid;
            }
        } elseif ($r->fk_notadebito_id != 0) {
            $comp = 'ND #'.DB::table('notadebito')->where('notadebito_id', (int) $r->fk_notadebito_id)->value('notadebito_nro');
            $link = "/administracion/notadebito/imprimir/{$r->fk_notadebito_id}";
        } elseif ($r->fk_notacredito_id != 0) {
            $comp = 'NC #'.DB::table('notacredito')->where('notacredito_id', (int) $r->fk_notacredito_id)->value('notacredito_nro');
            $link = "/administracion/notacredito/imprimir/{$r->fk_notacredito_id}";
        }
        if ($r->fk_recibo_id != 0) {
            $comp = 'RECIBO #'.$r->recibo_nro;
            $link = "/administracion/recibo/imprimir/{$r->fk_recibo_id}";
            $info = (string) $r->recibo_obs;
            $monedaComp = (string) $r->recibo_moneda;
        }
        if ((int) $r->fk_ordenadmin_id !== 0) {
            $tipo = (string) $r->orden_tipo;
            [$comp, $link] = match ($tipo) {
                'A' => ['MOV. CONTABLE', "/administracion/asientocontable/imprimir/{$r->fk_ordenadmin_id}"],
                'M' => ['MOV. FONDOS', "/administracion/fondos/imprimir/{$r->fk_ordenadmin_id}"],
                'C' => ['ASIENTO CTA CTE', "/administracion/asientocta/imprimir/{$r->fk_ordenadmin_id}"],
                default => ['ORDEN', "/administracion/ordenpago/imprimir/{$r->fk_ordenadmin_id}"],
            };
            $comp .= '# '.$r->orden_nro;
            $info = (string) $r->orden_obs;
            $monedaComp = (string) $r->orden_moneda;
        }

        return [$comp, $link, $info, $monedaComp, $fileId];
    }

    private function relacion(object $r, string $moneda, string $basica): float
    {
        if ($moneda === (string) $r->fk_moneda_id) {
            return 1.0;
        }
        $fecha = substr((string) $r->fecha, 0, 10);
        $m1 = $moneda === $basica ? 1.0 : ($this->cotizaciones->aLaVenta($moneda, $fecha) ?: 1.0);
        $m2 = (string) $r->fk_moneda_id === $basica ? 1.0 : ($this->cotizaciones->aLaVenta((string) $r->fk_moneda_id, $fecha) ?: 1.0);
        $cm = (float) $r->cotizacion_moneda;
        if ($cm != 0 && $cm != 1 && (string) $r->fk_moneda_id === $basica) {
            $m1 = $cm;
            $m2 = 1.0;
        } elseif ($cm != 0) {
            $m2 = $cm;
        }

        return $m2 / $m1;
    }

    private function dmy(string $f): string
    {
        return $f === '' ? '' : substr($f, 8, 2).'/'.substr($f, 5, 2).'/'.substr($f, 0, 4);
    }
}
