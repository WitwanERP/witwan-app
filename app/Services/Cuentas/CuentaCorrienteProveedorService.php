<?php

namespace App\Services\Cuentas;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta corriente de un proveedor: port de proveedor_model::getCuenta() del CI
 * (proveedor_model.php:17-495). Facturas de proveedor (haber; las notas de
 * crédito al debe), órdenes de pago (debe, netas de los créditos aplicados),
 * asientos en cuenta corriente y servicios PYC, en la moneda de reporte.
 *
 * En modo "sólo diferencias" el legacy agrupa por el número de imputación
 * (`imputacion.imputacion_orden` del asiento contable), no por file: se
 * conserva ese criterio. El saldo corrido se calcula en orden cronológico.
 */
class CuentaCorrienteProveedorService
{
    /** Cuenta hardcodeada en el CI para mostrar "(x Cred. Prov.)" en las OP. */
    private const CUENTA_CREDITO_PROVEEDOR = 1113004;

    public function __construct(private CotizacionService $cotizaciones) {}

    /**
     * @return array{anterior:float,grupos:list<array>,totales:array{d:float,h:float,saldo:float}}
     */
    public function cuenta(int $proveedorId, string $desde, string $hasta, string $tipo, string $moneda): array
    {
        $basicaReal = $this->cotizaciones->monedaBasica();
        $basica = $moneda !== '' ? $moneda : $basicaReal; // el legacy llama "monedabasica" a la de reporte
        $porImputacion = $tipo === 'D';
        $fromts = $desde !== '' ? strtotime($desde) : 0;
        $tots = $hasta !== '' ? strtotime($hasta) : PHP_INT_MAX;

        $anterior = 0.0;
        $grupos = [];
        $agregar = function (int|string $grupoId, ?string $nombre, array $item) use (&$grupos, $porImputacion) {
            $id = $porImputacion ? $grupoId : 0;
            $grupos[$id] ??= ['file_id' => $id, 'nombre' => null, 'orden' => $item['orden'], 'movimientos' => []];
            if ($grupos[$id]['orden'] > $item['orden'] || count($grupos[$id]['movimientos']) === 0) {
                $grupos[$id]['orden'] = $item['orden'];
            }
            if ($nombre !== null && $nombre !== '') {
                $grupos[$id]['nombre'] = $nombre;
            }
            $grupos[$id]['movimientos'][] = $item;
        };

        // A) Facturas de proveedor (y notas de crédito de proveedor).
        $facturas = DB::table('facturaproveedor as fp')
            ->leftJoin('movimiento as m', 'm.fk_facturaproveedor_id', '=', 'fp.facturaproveedor_id')
            ->where('fp.fk_proveedor_id', $proveedorId)
            ->groupBy('fp.facturaproveedor_id')
            ->orderBy('fp.fecha')
            ->get(['fp.*', DB::raw('MAX(m.fk_asientocontable_id) AS fk_asientocontable_id')]);
        foreach ($facturas as $row) {
            $imputado = $this->imputacion((string) $row->fk_asientocontable_id);
            $partes = [['texto' => "FC3 #{$row->facturaproveedor_tipodocumento} #{$row->facturaproveedor_nro} ({$row->fk_moneda_id})", 'href' => "/administracion/factura3ero/ver/{$row->facturaproveedor_id}"]];
            $nombre = null;
            $files = DB::table('reserva as r')
                ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
                ->join('rel_facturaproveedorocupacion as rf', 'rf.fk_ocupacion_id', '=', 's.servicio_id')
                ->where('rf.fk_facturaproveedor_id', $row->facturaproveedor_id)
                ->get(['r.reserva_id', 'r.tipocodigo', 'r.codigo']);
            foreach ($files as $f) {
                if ((int) $f->reserva_id !== 1) {
                    $partes[] = ['texto' => "{$f->tipocodigo}-{$f->codigo}", 'href' => "/reserva/editar/{$f->reserva_id}"];
                    $nombre = "{$f->tipocodigo}-{$f->codigo} // ".$partes[0]['texto'];
                }
            }
            $nombre ??= $partes[0]['texto'];

            $monto = $row->fk_moneda_id !== $basica
                ? $this->div((float) $row->montototal * (float) $row->cotizacion, $this->cotizaciones->aLaVenta($basica, (string) $row->fecha))
                : (float) $row->montototal;
            $esNc = $row->facturaproveedor_tipodocumento === 'Nota de Credito';
            $d = $esNc ? $monto : 0.0;
            $h = $esNc ? 0.0 : $monto;

            $fechats = strtotime((string) $row->fecha);
            if ($fechats < $fromts) {
                $anterior += $d - $h;

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $agregar($imputado, $nombre, $this->item($fechats, (string) $row->fecha, $partes, $d, $h, $imputado, (string) $row->fk_asientocontable_id));
        }

        // B) Órdenes de pago.
        $ordenes = DB::table('ordenadmin as o')
            ->leftJoin('movimiento as m', 'm.fk_ordenadmin_id', '=', 'o.ordenadmin_id')
            ->where('o.tipo', 'P')->where('o.status', '<>', 'AN')->where('o.fk_proveedor_id', $proveedorId)
            ->groupBy('o.ordenadmin_id')
            ->get(['o.*', DB::raw('MAX(m.fk_asientocontable_id) AS fk_asientocontable_id')]);
        foreach ($ordenes as $row) {
            $imputado = $this->imputacion((string) $row->fk_asientocontable_id);
            $op = ['texto' => "OP #{$row->nropago}", 'href' => "/administracion/ordenpago/imprimir/{$row->ordenadmin_id}"];
            $partes = [$op];
            $nombre = $op['texto'];
            $files = DB::table('reserva as r')
                ->join('servicio as s', 's.fk_reserva_id', '=', 'r.reserva_id')
                ->join('rel_ordenadminocupacion as ro', 'ro.fk_ocupacion_id', '=', 's.servicio_id')
                ->where('ro.fk_ordenadmin_id', $row->ordenadmin_id)
                ->get(['r.reserva_id', 'r.tipocodigo', 'r.codigo']);
            foreach ($files as $f) {
                if ((int) $f->reserva_id !== 1) {
                    $partes[] = ['texto' => "{$f->tipocodigo}-{$f->codigo}", 'href' => "/reserva/editar/{$f->reserva_id}"];
                    $nombre .= " // {$f->tipocodigo}-{$f->codigo}";
                }
            }

            // Créditos de proveedor aplicados en la orden (servicios CRE).
            $creditos = DB::table('rel_ordenadminocupacion as ro')
                ->join('servicio as s', 's.servicio_id', '=', 'ro.fk_ocupacion_id')
                ->where('ro.fk_ordenadmin_id', $row->ordenadmin_id)->where('s.fk_tipoproducto_id', 'CRE')
                ->get(['ro.monto', 's.servicio_nombre', 's.servicio_id']);
            $sumaCredito = (float) $creditos->sum('monto');
            $credProv = DB::table('movimiento')->where('fk_ordenadmin_id', $row->ordenadmin_id)->where('fk_plancuenta_id', self::CUENTA_CREDITO_PROVEEDOR)->sum('monto');
            $addcta = $credProv != 0 ? " ({$credProv} x Cred. Prov.)" : '';

            if ($row->fk_moneda_id !== $basica) {
                $cotOrden = (float) $row->cotizacion;
                $cotReporte = $this->cotizaciones->aLaVenta($basica, (string) $row->fecha);
                if ($row->fk_moneda_id === $basicaReal) {
                    $cotOrden = 1;
                    if ((float) $row->cotizacion != 1) {
                        $cotReporte = (float) $row->cotizacion;
                    }
                }
                $monto = $this->div((float) $row->monto * $cotOrden, $cotReporte);
            } else {
                $monto = (float) $row->monto;
            }
            $montoOriginal = $monto;
            $monto -= $sumaCredito;

            $fechats = strtotime((string) $row->fecha);
            if ($fechats < $fromts) {
                if ($fechats <= $tots) {
                    $anterior += $montoOriginal;
                }

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            if ($addcta !== '') {
                $partes[] = ['texto' => trim($addcta), 'href' => null];
            }
            $agregar($imputado, $nombre.$addcta, $this->item($fechats, (string) $row->fecha, $partes, $monto, 0, $imputado, (string) $row->fk_asientocontable_id));
            foreach ($creditos as $c) {
                $origen = $this->fileOrigenDelCredito((int) $c->servicio_id);
                $agregar($imputado, null, $this->item($fechats, (string) $row->fecha, [
                    ['texto' => "CREDITOS APLICADOS A {$op['texto']}", 'href' => $op['href']],
                    ['texto' => 'Origen: '.($origen ?: '-'), 'href' => null],
                ], 0, abs((float) $c->monto), $imputado, (string) $row->fk_asientocontable_id));
            }
        }

        // C) Asientos en cuenta corriente (ordenadmin tipo C).
        $asientos = DB::table('movimiento as m')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'm.fk_ordenadmin_id')
            ->where('oa.status', '<>', 'AN')->where('oa.tipo', 'C')->where('m.fk_proveedor_id', $proveedorId)
            ->get(['m.*', 'oa.nropago']);
        foreach ($asientos as $r) {
            $fechats = strtotime((string) $r->fecha);
            $coe = 1.0;
            if ($r->fk_moneda_id !== $basica) {
                $cotOrden = (float) $r->cotizacion_moneda;
                $cotReporte = $this->cotizaciones->aLaVenta($basica, (string) $r->fecha);
                if ($r->fk_moneda_id === $basicaReal) {
                    $cotOrden = 1;
                    if ((float) $r->cotizacion_moneda != 1 && (float) $r->cotizacion_moneda != 0) {
                        $cotReporte = (float) $r->cotizacion_moneda;
                    }
                }
                $coe = $this->div($cotOrden, $cotReporte);
            }
            // El legacy mira `movimiento.deha` ('D' => debe); las bases sin esa columna caen siempre al haber.
            $monto = (property_exists($r, 'deha') && $r->deha === 'D') ? (float) $r->monto * $coe : (float) $r->monto * $coe * -1;
            $imputado = $this->imputacion((string) $r->fk_asientocontable_id);
            $partes = [['texto' => "Asiento en CTA #{$r->nropago}", 'href' => "/administracion/asientocta/imprimir/{$r->fk_ordenadmin_id}"]];
            $nombre = null;
            if ((int) $r->fk_file_id !== 0) {
                $f = DB::table('reserva')->where('reserva_id', $r->fk_file_id)->first(['tipocodigo', 'codigo']);
                if ($f) {
                    $partes[] = ['texto' => "{$f->tipocodigo}-{$f->codigo}", 'href' => "/reserva/editar/{$r->fk_file_id}"];
                    $nombre = "{$f->tipocodigo}-{$f->codigo}";
                }
            }
            if ($fechats < $fromts) {
                $anterior += $monto;

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $agregar($imputado, $nombre, $this->item($fechats, (string) $r->fecha, $partes, $monto > 0 ? $monto : 0, $monto < 0 ? -$monto : 0, $imputado, (string) $r->fk_asientocontable_id));
        }

        // D) Servicios PYC (fecha fija del legacy, sin tope superior).
        $pyc = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->where('s.fk_tipoproducto_id', 'PYC')->where('s.status', '<>', 'CA')->where('s.costo', '<>', 0)->where('s.fk_proveedor_id', $proveedorId)
            ->get(['s.servicio_id', 's.fk_reserva_id', 's.costo', 'r.fecha_alta', 'r.titular_nombre', 'r.tipocodigo', 'r.codigo']);
        foreach ($pyc as $r) {
            $fechats = strtotime((string) $r->fecha_alta);
            $costo = (float) $r->costo;
            if ($fechats < $fromts) {
                $anterior += -$costo;

                continue;
            }
            $imputado = $this->imputacion('S'.$r->servicio_id);
            $agregar($imputado, "PYT #{$r->titular_nombre}", $this->item($fechats, '2017-08-06', [
                ['texto' => "PYT #{$r->titular_nombre}", 'href' => null],
                ['texto' => "{$r->tipocodigo}-{$r->codigo}", 'href' => "/reserva/editar/{$r->fk_reserva_id}"],
            ], $costo < 0 ? -$costo : 0, $costo > 0 ? $costo : 0, $imputado, 'S'.$r->servicio_id));
        }

        uasort($grupos, fn ($a, $b) => $a['orden'] <=> $b['orden']);
        $saldo = $anterior;
        $totD = 0.0;
        $totH = 0.0;
        $salida = [];
        foreach ($grupos as $g) {
            usort($g['movimientos'], fn ($a, $b) => $a['orden'] <=> $b['orden']);
            $g['total'] = round(array_sum(array_map(fn ($m) => $m['d'] - $m['h'], $g['movimientos'])), 2);
            if ($porImputacion && $g['file_id'] !== 0 && $g['total'] == 0) {
                continue;
            }
            foreach ($g['movimientos'] as &$m) {
                $saldo += $m['d'] - $m['h'];
                $totD += $m['d'];
                $totH += $m['h'];
                $m['saldo'] = round($saldo, 2);
                $m['d'] = round($m['d'], 2);
                $m['h'] = round($m['h'], 2);
            }
            unset($m);
            $g['fecha'] = date('d/m/Y', $g['orden']);
            $g['nombre'] = $g['nombre'] ?? ($g['file_id'] !== 0 ? "Imputación #{$g['file_id']}" : null);
            $salida[] = $g;
        }

        return [
            'anterior' => round($anterior, 2),
            'grupos' => $salida,
            'totales' => ['d' => round($totD, 2), 'h' => round($totH, 2), 'saldo' => round($saldo, 2)],
        ];
    }

    /** Número de imputación del asiento (imputacion.imputacion_orden, el último). */
    private function imputacion(string $asiento): int
    {
        if ($asiento === '' || $asiento === '0') {
            return 0;
        }

        return (int) DB::table('imputacion')->where('imputacion_movimiento1', $asiento)->orderByDesc('imputacion_orden')->value('imputacion_orden');
    }

    /** Réplica de proveedor_model::getCreP(): file de origen de un crédito de proveedor aplicado. */
    private function fileOrigenDelCredito(int $servicioId): ?string
    {
        $idCredito = (int) DB::table('servicio_extra')->where('fk_servicio_id', $servicioId)->where('extra_nombre', 'paquete')->orderByDesc('regdate')->value('extra_valor');
        if ($idCredito === 0) {
            return null;
        }
        $obs = DB::table('precompra')->where('precompra_id', $idCredito)->value('observaciones');
        if ($obs === null || $obs === '') {
            return null;
        }
        $reservaId = 0;
        foreach (DB::table('movimiento')->where('operacion', $obs)->where('operacion', '<>', '')->get(['fk_ordenadmin_id']) as $mv) {
            $rid = DB::table('servicio as s')->join('rel_ordenadminocupacion as ro', 'ro.fk_ocupacion_id', '=', 's.servicio_id')
                ->where('ro.fk_ordenadmin_id', $mv->fk_ordenadmin_id)->orderByDesc('s.servicio_id')->value('s.fk_reserva_id');
            if ($rid) {
                $reservaId = (int) $rid;
            }
        }
        if ($reservaId === 0) {
            return null;
        }
        $r = DB::table('reserva')->where('reserva_id', $reservaId)->first(['tipocodigo', 'codigo']);

        return $r ? "{$r->tipocodigo}-{$r->codigo}" : null;
    }

    private function item(int $orden, string $fecha, array $partes, float $d, float $h, int $imputado, string $asiento): array
    {
        return ['orden' => $orden, 'fecha' => $this->dmy(substr($fecha, 0, 10)), 'concepto' => $partes, 'd' => $d, 'h' => $h, 'imputado' => $imputado, 'asiento' => $asiento];
    }

    private function div(float $a, float $b): float
    {
        return $b == 0 ? 0.0 : $a / $b;
    }

    private function dmy(string $fecha): string
    {
        return substr($fecha, 8, 2).'/'.substr($fecha, 5, 2).'/'.substr($fecha, 0, 4);
    }
}
