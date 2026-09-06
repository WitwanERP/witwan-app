<?php

namespace App\Http\Controllers\Web\Caja;

use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Administración > Caja > Arqueo del día (CI: administracion/caja/arqueos).
 * Para cada cuenta con `plancuenta.arqueo=1` (y `plancuenta_g=1` en secontur
 * y essential): saldo inicial (todo lo anterior al día), movimientos del día
 * con debe/haber y saldo final. Mismo signo que el mayor (ingreso si hay
 * débito o ambas, egreso si sólo crédito, invertido si viene de una orden),
 * sólo movimientos con `statusmovimiento='OK'`, sin anulados. El cierre de
 * arqueo (`caja/arqueo`) sigue en el legacy.
 */
class ArqueoController extends ReporteController
{
    protected string $titulo = 'Arqueo de caja';

    protected string $ruta = 'caja/arqueo';

    protected string $grupo = 'Caja';

    protected ?string $agruparPor = 'cuenta';

    protected bool $requiereFiltros = false;

    protected function filtros(): array
    {
        return [['campo' => 'fecha', 'label' => 'Día', 'tipo' => 'date', 'default' => now()->toDateString()]];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'comprobante', 'label' => 'Comprobante'],
            ['campo' => 'descripcion', 'label' => 'Descripción'],
            ['campo' => 'file', 'label' => 'File'],
            ['campo' => 'moneda', 'label' => 'Moneda'],
            ['campo' => 'debe', 'label' => 'Ingresos', 'tipo' => 'num', 'total' => true],
            ['campo' => 'haber', 'label' => 'Egresos', 'tipo' => 'num', 'total' => true],
            ['campo' => 'saldo', 'label' => 'Saldo', 'tipo' => 'num'],
        ];
    }

    protected function consultar(array $f): array
    {
        $dia = $f['fecha'] !== '' ? $f['fecha'] : now()->toDateString();
        $q = DB::table('plancuenta')->where('arqueo', 1)->orderByRaw('TRIM(plancuenta_nombre)');
        if (Licencia::esFamiliaSecontur() || str_contains(Licencia::base(), 'essential')) {
            $q->where('plancuenta_g', 1);
        }
        $out = [];
        foreach ($q->get(['plancuenta_id', 'plancuenta_nombre', 'fk_moneda_id']) as $c) {
            $cta = (int) $c->plancuenta_id;
            $nombre = $c->plancuenta_nombre.((string) $c->fk_moneda_id !== '' ? " ({$c->fk_moneda_id})" : '');
            $movs = DB::table('movimiento')
                ->leftJoin('recibo', 'recibo.recibo_id', '=', 'movimiento.fk_recibo_id')
                ->leftJoin('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
                ->where(fn ($w) => $w->where('movimiento.fk_plancuenta_id', $cta)->orWhere(fn ($w2) => $w2->where('movimiento.cuenta_debito', $cta)->where('movimiento.fk_plancuenta_id', 0)))
                ->where(fn ($w) => $w->where('ordenadmin.status', '<>', 'AN')->orWhereNull('ordenadmin.status'))
                ->where(fn ($w) => $w->where('recibo.statusrecibo', '<>', 'AN')->orWhereNull('recibo.statusrecibo'))
                ->where('movimiento.statusmovimiento', 'OK')
                ->where('movimiento.fecha', '<=', $dia)
                ->select('movimiento.*', 'ordenadmin.tipo as orden_tipo', 'ordenadmin.nropago as orden_nro', 'ordenadmin.observaciones as orden_obs', 'recibo.recibo_nro', 'recibo.observaciones as recibo_obs')
                ->orderBy('movimiento.fecha')->orderBy('movimiento.movimiento_id')->get();

            $inicial = 0.0;
            $saldo = 0.0;
            $filas = [];
            foreach ($movs as $r) {
                $ingreso = ! ((int) $r->cuenta_credito !== 0 && (int) $r->cuenta_debito === 0);
                if ((int) $r->fk_ordenadmin_id !== 0) {
                    $ingreso = ! $ingreso;
                }
                $monto = round((float) $r->monto, 2);
                if (substr((string) $r->fecha, 0, 10) < $dia) {
                    $inicial += $ingreso ? $monto : -$monto;

                    continue;
                }
                $saldo += $ingreso ? $monto : -$monto;
                $comp = '';
                $info = '';
                if ($r->fk_factura_id != 0 && $r->fk_notacredito_id == 0) {
                    $fc = DB::table('factura')->where('factura_id', (int) $r->fk_factura_id)->first(['factura_nro', 'observaciones']);
                    $comp = 'FACTURA #'.($fc->factura_nro ?? '');
                    $info = (string) ($fc->observaciones ?? '');
                }
                if ($r->fk_facturaproveedor_id != 0) {
                    $fp = DB::table('facturaproveedor')->where('facturaproveedor_id', (int) $r->fk_facturaproveedor_id)->first(['facturaproveedor_nro', 'descripcion']);
                    $comp = 'FACTURA 3OS #'.($fp->facturaproveedor_nro ?? '');
                    $info = (string) ($fp->descripcion ?? '');
                }
                if ($r->fk_notacredito_id != 0) {
                    $comp = 'NC #'.DB::table('notacredito')->where('notacredito_id', (int) $r->fk_notacredito_id)->value('notacredito_nro');
                }
                if ($r->fk_recibo_id != 0) {
                    $comp = 'RECIBO #'.$r->recibo_nro;
                    $info = (string) $r->recibo_obs;
                }
                if ((int) $r->fk_ordenadmin_id !== 0) {
                    $comp = match ((string) $r->orden_tipo) {
                        'A' => 'ASIENTO', 'M' => 'MOVIMIENTO', default => 'ORDEN'
                    }.'# '.$r->orden_nro;
                    $info = (string) $r->orden_obs;
                }
                $file = (int) $r->fk_file_id !== 0 ? (string) DB::table('reserva')->where('reserva_id', (int) $r->fk_file_id)->selectRaw("CONCAT(tipocodigo, '-', codigo) AS c")->value('c') : '';
                if (in_array($file, ['99-1', 'AD-1'], true)) {
                    $file = 'ADMIN';
                }
                $filas[] = ['cuenta' => $nombre, 'comprobante' => $comp, 'descripcion' => trim((string) $r->descripcion.' '.(string) $r->operacion.' '.(string) $r->banco.' '.$info), 'file' => $file,
                    'moneda' => (string) $r->fk_moneda_id, 'debe' => $ingreso ? $monto : 0.0, 'haber' => $ingreso ? 0.0 : $monto, 'saldo' => round($saldo, 2)];
            }
            $out[] = ['cuenta' => $nombre, 'comprobante' => 'SALDO INICIAL', 'descripcion' => 'Anterior al '.date('d/m/Y', strtotime($dia)), 'file' => '', 'moneda' => (string) $c->fk_moneda_id, 'debe' => 0.0, 'haber' => 0.0, 'saldo' => round($inicial, 2)];
            $out = array_merge($out, $filas);
            $out[] = ['cuenta' => $nombre, 'comprobante' => 'SALDO FINAL', 'descripcion' => 'Inicial + movimientos del día', 'file' => '', 'moneda' => (string) $c->fk_moneda_id, 'debe' => 0.0, 'haber' => 0.0, 'saldo' => round($inicial + $saldo, 2)];
        }

        return $out;
    }
}
