<?php

namespace App\Services\Cuentas;

use App\Helpers\SysconfigHelper;
use App\Services\CotizacionService;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta corriente de un cliente: port de cliente_model::getCuenta() del CI
 * (cliente_model.php:16-405). Junta, en la moneda de reporte, los asientos en
 * cuenta corriente, los servicios PYT, las facturas, las notas de crédito y
 * débito y los recibos del cliente, separando lo anterior al rango (saldo
 * inicial) de los movimientos del período.
 *
 * Réplica fiel de las conversiones y de los criterios del legacy, incluidas
 * sus asimetrías (p. ej. las notas de débito restan del "anterior" pero se
 * muestran en el debe). Lo único que cambia es que el saldo corrido se calcula
 * en orden cronológico sobre las filas ya ordenadas, que es lo que la vista
 * del CI terminaba mostrando.
 *
 * Los conceptos se devuelven como partes {texto, href} en vez de HTML.
 */
class CuentaCorrienteClienteService
{
    public function __construct(private CotizacionService $cotizaciones) {}

    /**
     * @param  string  $tipo  'C' completo | 'D' sólo diferencias (agrupado por file)
     * @return array{anterior:float,grupos:list<array>,totales:array{d:float,h:float,saldo:float}}
     */
    public function cuenta(int $clienteId, string $desde, string $hasta, string $tipo, string $moneda): array
    {
        $basica = $this->cotizaciones->monedaBasica();
        $moneda = $moneda !== '' ? $moneda : $basica;
        $porFile = $tipo === 'D';
        $fromts = $desde !== '' ? strtotime("{$desde} 00:00:00") : 0;
        $tots = $hasta !== '' ? strtotime("{$hasta} 23:59:59") : 100000000000;
        $coef = $this->coef();
        $dec = Licencia::pais() === 'CL' ? 0 : 2;

        $anterior = 0.0;
        $grupos = [];
        $agregar = function (int $fileId, ?string $nombre, array $item) use (&$grupos, $porFile) {
            $id = $porFile ? $fileId : 0;
            $grupos[$id] ??= ['file_id' => $id, 'nombre' => null, 'orden' => $item['orden'], 'movimientos' => []];
            if ($grupos[$id]['orden'] > $item['orden'] || count($grupos[$id]['movimientos']) === 0) {
                $grupos[$id]['orden'] = $item['orden'];
            }
            if ($nombre !== null && $nombre !== '') {
                $grupos[$id]['nombre'] = $nombre;
            }
            $grupos[$id]['movimientos'][] = $item;
        };

        // 1) Asientos en cuenta corriente.
        $asientos = DB::table('movimiento as m')
            ->join('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'm.fk_ordenadmin_id')
            ->where('oa.status', '<>', 'AN')
            ->where('m.fk_cliente_id', $clienteId)
            ->get(['m.*', 'oa.nropago']);
        foreach ($asientos as $r) {
            $fechats = strtotime((string) $r->fecha);
            $cot = $r->fk_moneda_id === $moneda ? 1 : $this->div((float) $r->cotizacion_moneda, $this->cotizaciones->aLaVenta($moneda, (string) $r->fecha));
            $monto = ((int) $r->cuenta_credito !== 0 && (int) $r->cuenta_debito === 0) ? (float) $r->monto * $cot : (float) $r->monto * $cot * -1;
            if ($fechats < $fromts) {
                $anterior += $monto;

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $codigo = $this->codigoFile((int) $r->fk_file_id);
            $agregar((int) $r->fk_file_id, $codigo, $this->item($fechats, (string) $r->fecha,
                [['texto' => "Asiento en CTA #{$r->nropago}", 'href' => "/administracion/asientocta/imprimir/{$r->fk_ordenadmin_id}"]],
                $monto > 0 ? $monto : 0, $monto < 0 ? -$monto : 0));
        }

        // 2) Servicios PYT (fecha fija del legacy, sin tope superior).
        $pyt = DB::table('servicio as s')
            ->join('reserva as r', 'r.reserva_id', '=', 's.fk_reserva_id')
            ->where('s.fk_tipoproducto_id', 'PYT')->where('s.status', '<>', 'CA')->where('s.total', '<>', 0)
            ->where('r.fk_cliente_id', $clienteId)
            ->get(['s.servicio_id', 's.fk_reserva_id', 's.total', 'r.titular_nombre']);
        foreach ($pyt as $r) {
            $fechats = strtotime('2017-08-06');
            if ($fechats < $fromts) {
                $anterior += (float) $r->total;

                continue;
            }
            $agregar((int) $r->fk_reserva_id, "PYT #{$r->titular_nombre}", $this->item($fechats, '2017-08-06',
                [['texto' => "PYT #{$r->titular_nombre}", 'href' => "/reserva/editar/{$r->fk_reserva_id}"]],
                (float) $r->total > 0 ? (float) $r->total : 0, (float) $r->total < 0 ? -(float) $r->total : 0));
        }

        // 3) Facturas.
        $facturas = DB::table('factura')
            ->where('fk_cliente_id', $clienteId)->where('statusfactura', '<>', 'NU')
            ->select('*', DB::raw("ROUND((factura_conceptos_gravados * {$coef} + factura_conceptos_gravadosespecial * 1.105 + factura_conceptos_exentos + factura_conceptos_nogravados + factura_impuesto1 + factura_impuesto2 + IF(factura_fecha > '2015-12-17', factura_rgterrestres, 0) - CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(factura.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2))), {$dec}) AS total"))
            ->get();
        foreach ($facturas as $r) {
            $fechats = strtotime((string) $r->factura_fecha);
            $roe = 1.0;
            if ($r->fk_moneda_id !== $moneda) {
                $roe = $this->div($r->fk_moneda_id === $basica ? 1 : (float) $r->factura_tipo_cambio, $this->cotizaciones->aLaVenta($moneda, substr((string) $r->factura_fecha, 0, 10)));
            }
            $monto = (float) $r->total * $roe;
            if ($fechats < $fromts) {
                $anterior += $monto;

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $file = DB::table('reserva as r')->join('rel_filefactura as rf', 'rf.fk_file_id', '=', 'r.reserva_id')
                ->where('rf.fk_factura_id', $r->factura_id)->where('r.fk_cliente_id', $clienteId)->where('r.reserva_id', '<>', 1)
                ->first(['r.reserva_id', 'r.tipocodigo', 'r.codigo']);
            $partes = [['texto' => "FC #{$r->factura_tipo} {$r->factura_nro} ({$r->fk_moneda_id})", 'href' => "/administracion/factura/imprimir/{$r->factura_id}"]];
            $nombre = $partes[0]['texto'];
            if ($file) {
                $partes[] = ['texto' => "FILE: {$file->tipocodigo}-{$file->codigo}", 'href' => "/reserva/editar/{$file->reserva_id}"];
                $nombre .= " // FILE: {$file->tipocodigo}-{$file->codigo}";
            }
            $agregar($file ? (int) $file->reserva_id : 0, $nombre, $this->item($fechats, substr((string) $r->factura_fecha, 0, 10), $partes, $monto, 0));
        }

        // 4) Notas de crédito.
        $ncs = DB::table('notacredito')
            ->where('fk_cliente_id', $clienteId)->where('statusfactura', '<>', 'NU')
            ->select('*', DB::raw("ROUND(notacredito_conceptos_gravados * {$coef} + notacredito_conceptos_gravadosespecial * 1.105 + notacredito_conceptos_exentos + notacredito_conceptos_nogravados + notacredito_rgterrestres + notacredito_impuesto1 + notacredito_impuesto2 + CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(notacredito.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)), {$dec}) AS total"))
            ->get();
        foreach ($ncs as $r) {
            $fechats = strtotime((string) $r->notacredito_fecha);
            $roe = 1.0;
            if ($r->fk_moneda_id !== $moneda) {
                $roe = $this->div(($r->fk_moneda_id === $basica || $r->fk_moneda_id === '') ? 1 : (float) $r->notacredito_tipo_cambio, $this->cotizaciones->aLaVenta($moneda, substr((string) $r->notacredito_fecha, 0, 10)));
            }
            $monto = (float) $r->total * $roe;
            if ($fechats < $fromts) {
                if ($fechats <= $tots) {
                    $anterior -= $monto;
                }

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $fileId = (int) $r->fk_file_id;
            if ($fileId === 0 && (int) $r->fk_factura_id !== 0) {
                $fileId = (int) DB::table('factura')->where('factura_id', $r->fk_factura_id)->value('fk_file_id');
            }
            $file = $fileId !== 0 && $fileId !== 1 ? DB::table('reserva')->where('reserva_id', $fileId)->first(['reserva_id', 'tipocodigo', 'codigo']) : null;
            $partes = [['texto' => "NC #{$r->notacredito_tipo} {$r->notacredito_nro} ({$r->fk_moneda_id})", 'href' => "/administracion/notacredito/imprimir/{$r->notacredito_id}"]];
            if ($file) {
                $partes[] = ['texto' => "FILE: {$file->tipocodigo}-{$file->codigo}", 'href' => "/reserva/editar/{$file->reserva_id}"];
            }
            $agregar($file ? (int) $file->reserva_id : 0, $file ? "{$file->tipocodigo}-{$file->codigo}" : null, $this->item($fechats, substr((string) $r->notacredito_fecha, 0, 10), $partes, 0, $monto));
        }

        // 5) Notas de débito (el legacy las resta del anterior pero las muestra en el debe).
        $nds = DB::table('notadebito')
            ->where('fk_cliente_id', $clienteId)->where('statusfactura', '<>', 'NU')
            ->select('*', DB::raw("ROUND(notadebito_conceptos_gravados * {$coef} + notadebito_conceptos_gravadosespecial * 1.105 + notadebito_conceptos_exentos + notadebito_conceptos_nogravados + notadebito_rgterrestres + notadebito_impuesto1 + notadebito_impuesto2, {$dec}) * IF(notadebito.fk_moneda_id <> '' AND notadebito.fk_moneda_id <> '{$basica}', notadebito.notadebito_tipo_cambio, 1) AS total"))
            ->get();
        foreach ($nds as $r) {
            $fechats = strtotime((string) $r->notadebito_fecha);
            $monto = (float) $r->total;
            if ($fechats < $fromts) {
                if ($fechats <= $tots) {
                    $anterior -= $monto;
                }

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $file = null;
            if ((int) $r->fk_notacredito_id !== 0) {
                $file = DB::table('reserva as r')->join('notacredito as nc', 'nc.fk_file_id', '=', 'r.reserva_id')
                    ->where('nc.notacredito_id', $r->fk_notacredito_id)->first(['r.reserva_id', 'r.tipocodigo', 'r.codigo'])
                    ?? DB::table('reserva as r')->join('factura as fc', 'fc.fk_file_id', '=', 'r.reserva_id')->join('notacredito as nc', 'nc.fk_factura_id', '=', 'fc.factura_id')
                        ->where('nc.notacredito_id', $r->fk_notacredito_id)->first(['r.reserva_id', 'r.tipocodigo', 'r.codigo']);
            }
            $partes = [['texto' => "ND #{$r->notadebito_tipo} {$r->notadebito_nro} ({$r->fk_moneda_id})", 'href' => "/administracion/notadebito/imprimir/{$r->notadebito_id}"]];
            $agregar($file ? (int) $file->reserva_id : 0, $file ? "{$file->tipocodigo}-{$file->codigo}" : null, $this->item($fechats, substr((string) $r->notadebito_fecha, 0, 10), $partes, $monto, 0));
        }

        // 6) Recibos.
        $recibos = DB::table('recibo')->where('statusrecibo', '<>', 'AN')->where('fk_cliente_id', $clienteId)->get();
        foreach ($recibos as $r) {
            $fechats = strtotime((string) $r->fecha);
            $cot = $this->cotizaciones->aLaVenta((string) $r->fk_moneda_id);
            $mov = DB::table('movimiento')->where('fk_recibo_id', $r->recibo_id)->where('cuenta_credito', '<>', 0)->where('cuenta_debito', '<>', 0)
                ->orderByDesc('cotizacion_moneda')->first(['cotizacion_moneda', 'fk_moneda_id']);
            if ($mov) {
                $cotiza = $mov->fk_moneda_id === $basica ? 1 : (float) $mov->cotizacion_moneda;
                $cot = ($mov->fk_moneda_id === $basica && (float) $mov->cotizacion_moneda != 1)
                    ? $this->div(1, (float) $mov->cotizacion_moneda)
                    : $this->div($cotiza, $this->cotizaciones->aLaVenta($moneda, (string) $r->fecha));
            }
            if ($r->fk_moneda_id === $moneda) {
                $cot = 1;
            }
            $coe = $r->statusrecibo === 'DV' ? -1 : 1;
            $total = (float) $r->monto;
            if ($fechats < $fromts) {
                if ($fechats <= $tots) {
                    $anterior -= $total * $cot;
                }

                continue;
            }
            if ($fechats > $tots) {
                continue;
            }
            $files = DB::table('reserva as r')->join('rel_filerecibo as rf', 'rf.fk_file_id', '=', 'r.reserva_id')
                ->where('rf.fk_recibo_id', $r->recibo_id)->get(['r.reserva_id', 'r.tipocodigo', 'r.codigo', 'rf.monto']);
            $partes = [['texto' => "RC #{$r->recibo_nro}", 'href' => "/administracion/recibo/imprimir/{$r->recibo_id}"]];
            $nombre = '';
            foreach ($files as $f) {
                $partes[] = ['texto' => "FILE: {$f->tipocodigo}-{$f->codigo}", 'href' => "/reserva/editar/{$f->reserva_id}"];
                $nombre .= ($nombre !== '' ? ' // ' : '')."FILE: {$f->tipocodigo}-{$f->codigo}";
            }
            if ($porFile) {
                foreach ($files as $f) {
                    $m = (float) $f->monto * $coe;
                    $agregar((int) $f->reserva_id, $nombre, $this->item($fechats, (string) $r->fecha, $partes, $m < 0 ? abs((float) $f->monto) * $cot * $coe : 0, $m > 0 ? abs((float) $f->monto) * $cot * $coe : 0));
                }
            } else {
                $m = $total * $coe;
                $agregar(0, $nombre, $this->item($fechats, (string) $r->fecha, $partes, $m < 0 ? abs($total) * $cot * $coe : 0, $m > 0 ? abs($total) * $cot * $coe : 0));
            }
        }

        // Orden cronológico y saldo corrido (lo que la vista del CI terminaba mostrando).
        uasort($grupos, fn ($a, $b) => $a['orden'] <=> $b['orden']);
        $saldo = $anterior;
        $totD = 0.0;
        $totH = 0.0;
        $salida = [];
        foreach ($grupos as $g) {
            usort($g['movimientos'], fn ($a, $b) => $a['orden'] <=> $b['orden']);
            $g['total'] = round(array_sum(array_map(fn ($m) => $m['d'] - $m['h'], $g['movimientos'])), 2);
            // Modo diferencias: sólo files con saldo distinto de cero (el grupo 0 siempre va).
            if ($porFile && $g['file_id'] !== 0 && $g['total'] == 0) {
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
            $salida[] = $g;
        }

        return [
            'anterior' => round($anterior, 2),
            'grupos' => $salida,
            'totales' => ['d' => round($totD, 2), 'h' => round($totH, 2), 'saldo' => round($saldo, 2)],
        ];
    }

    private function item(int $orden, string $fecha, array $partes, float $d, float $h): array
    {
        return ['orden' => $orden, 'fecha' => $this->dmy($fecha), 'concepto' => $partes, 'd' => $d, 'h' => $h];
    }

    private function codigoFile(int $id): ?string
    {
        if ($id === 0) {
            return null;
        }
        $r = DB::table('reserva')->where('reserva_id', $id)->first(['tipocodigo', 'codigo']);

        return $r ? "{$r->tipocodigo}-{$r->codigo}" : null;
    }

    private function coef(): float
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 21);

        return 1 + ($tasa < 1 ? $tasa : $tasa / 100);
    }

    /** División protegida: sin cotización cargada el legacy dividía por cero (INF); acá queda 0. */
    private function div(float $a, float $b): float
    {
        return $b == 0 ? 0.0 : $a / $b;
    }

    private function dmy(string $fecha): string
    {
        return substr($fecha, 8, 2).'/'.substr($fecha, 5, 2).'/'.substr($fecha, 0, 4);
    }
}
