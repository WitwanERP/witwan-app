<?php

namespace App\Services\Contabilidad;

use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;

/**
 * Balance de sumas y saldos (CI: administracion/libros::balance). Cada
 * movimiento pertenece a una cuenta (`fk_plancuenta_id`, o `cuenta_debito`
 * cuando aquella es 0) con signo: +1 si tiene débito o ambas, −1 si sólo
 * crédito o ninguna, invertido si viene de una orden; los movimientos en otra
 * moneda se llevan a la básica con su `cotizacion_moneda`. Lo anterior a
 * "desde" va a Acumulado; lo del rango a Debe/Haber; Saldo = debe − haber +
 * acumulado. Las cuentas hoja suman hacia sus totalizadoras (hasta 6 niveles).
 *
 * Diferencia con el CI: convierte siempre a la moneda básica (el CI comparaba
 * contra 'ARS' fijo, así que en tenants no argentinos no convertía). Sin
 * "hasta" el CI no devolvía nada útil (fecha >= NOW y <= NOW): acá "hasta"
 * vacío significa hoy.
 */
class BalanceService
{
    public function __construct(private CotizacionService $cotizaciones) {}

    /** @return list<array{cuenta_id:int,codigo:string,nombre:string,titulo:int,totalizadora:int,debe:float,haber:float,saldo:float,anterior:float,tiene_hijas:bool}> */
    public function filas(string $desde, string $hasta, string $acumulado): array
    {
        $hasta = $hasta !== '' ? $hasta : now()->toDateString();
        $basica = $this->cotizaciones->monedaBasica();
        $dec = $basica === 'CLP' ? 0 : 4;

        $q = DB::table('movimiento')
            ->leftJoin('recibo', 'recibo.recibo_id', '=', 'movimiento.fk_recibo_id')
            ->leftJoin('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->where(fn ($w) => $w->where('ordenadmin.status', '<>', 'AN')->orWhereNull('ordenadmin.status'))
            ->where(fn ($w) => $w->where('recibo.statusrecibo', '<>', 'AN')->orWhereNull('recibo.statusrecibo'))
            ->whereIn('movimiento.auxiliar', [0, 1])
            ->where('movimiento.fecha', '<=', $hasta)
            ->select([
                'movimiento.fecha',
                DB::raw('IF(movimiento.fk_plancuenta_id <> 0, movimiento.fk_plancuenta_id, movimiento.cuenta_debito) AS cuenta'),
                DB::raw("ROUND(movimiento.monto
                    * IF(movimiento.cuenta_credito <> 0 AND movimiento.cuenta_debito <> 0, 1, IF(movimiento.cuenta_credito <> 0, -1, IF(movimiento.cuenta_debito <> 0, 1, -1)))
                    * IF(movimiento.fk_ordenadmin_id <> 0, -1, 1)
                    * IF(movimiento.fk_moneda_id <> '{$basica}', CAST(movimiento.cotizacion_moneda AS DECIMAL(10,5)), 1), {$dec}) AS monton"),
            ]);
        if ($acumulado !== '') {
            $q->where('movimiento.fecha', '>=', $acumulado);
        }

        $acum = [];
        foreach ($q->cursor() as $r) {
            $c = (int) $r->cuenta;
            $acum[$c] ??= ['debe' => 0.0, 'haber' => 0.0, 'anterior' => 0.0];
            $fecha = substr((string) $r->fecha, 0, 10);
            if ($desde !== '' && $fecha < $desde) {
                $acum[$c]['anterior'] += (float) $r->monton;
            } elseif ((float) $r->monton < 0) {
                $acum[$c]['haber'] += abs((float) $r->monton);
            } else {
                $acum[$c]['debe'] += abs((float) $r->monton);
            }
        }

        $cuentas = DB::table('plancuenta')->orderByRaw("TRIM(CONCAT(plancuenta_codigo, ' '))")->get(['plancuenta_id', 'plancuenta_nombre', 'plancuenta_codigo', 'fk_plancuenta_id', 'plancuenta_titulo']);
        $bl = [];
        foreach ($cuentas as $c) {
            $id = (int) $c->plancuenta_id;
            $a = $acum[$id] ?? ['debe' => 0.0, 'haber' => 0.0, 'anterior' => 0.0];
            $bl[$id] = [
                'cuenta_id' => $id, 'codigo' => (string) $c->plancuenta_codigo, 'nombre' => (string) $c->plancuenta_nombre, 'titulo' => (int) $c->plancuenta_titulo,
                'totalizadora' => (int) $c->fk_plancuenta_id, 'debe' => $a['debe'], 'haber' => $a['haber'], 'anterior' => $a['anterior'], 'saldo' => $a['debe'] - $a['haber'] + $a['anterior'], 'tiene_hijas' => false,
            ];
        }
        foreach ($bl as $id => $ct) {
            if ($ct['totalizadora'] !== 0 && isset($bl[$ct['totalizadora']])) {
                $bl[$ct['totalizadora']]['tiene_hijas'] = true;
            }
        }
        $propios = $bl;
        foreach ($propios as $id => $ct) {
            $ttz = $ct['totalizadora'];
            if ($ttz === 0 || ! isset($bl[$ttz]) || $ct['tiene_hijas']) {
                continue;
            }
            $xx = 0;
            while ($ttz !== 0 && isset($bl[$ttz]) && $xx < 6) {
                foreach (['debe', 'haber', 'saldo', 'anterior'] as $k) {
                    $bl[$ttz][$k] += $ct[$k];
                }
                $ttz = $bl[$ttz]['totalizadora'];
                $xx++;
            }
        }

        return array_values(array_map(fn ($c) => array_merge($c, ['debe' => round($c['debe'], 2), 'haber' => round($c['haber'], 2), 'saldo' => round($c['saldo'], 2), 'anterior' => round($c['anterior'], 2)]), $bl));
    }
}
