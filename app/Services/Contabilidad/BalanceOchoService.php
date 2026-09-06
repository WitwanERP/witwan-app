<?php

namespace App\Services\Contabilidad;

use App\Helpers\SysconfigHelper;
use App\Services\CotizacionService;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Balance de 8 columnas (CI: administracion/Balance). Suma Debe/Haber por
 * cuenta en el período (mismo signo que el balance de sumas y saldos, con las
 * órdenes tipo X sólo si son de otro año que el "hasta"), recorre el plan de
 * cuentas como árbol desde las raíces y, para cada cuenta con movimientos,
 * reparte el saldo en Deudor/Acreedor y en la columna patrimonial de su raíz
 * (`sysconfig` activo/pasivo/ganancia/perdida), cruzándola si el signo es el
 * contrario. Cierra con SUMAS y RESULTADO.
 */
class BalanceOchoService
{
    public const COLUMNAS = ['debe', 'haber', 'deudor', 'acreedor', 'activo', 'pasivo', 'perdida', 'ganancia'];

    public function __construct(private CotizacionService $cotizaciones) {}

    /** @return list<array<string,mixed>> */
    public function filas(string $desde, string $hasta): array
    {
        $basica = $this->cotizaciones->monedaBasica();
        $dec = Licencia::pais() === 'CL' ? 0 : 2;
        $hasta = $hasta !== '' ? $hasta : now()->toDateString();

        // MySQL no permite alias de expresión en el mismo nivel: se calcula el monto firmado en una subconsulta.
        $sub = "(movimiento.monto
            * IF(movimiento.cuenta_credito <> 0 AND movimiento.cuenta_debito <> 0, 1, IF(movimiento.cuenta_credito <> 0, -1, IF(movimiento.cuenta_debito <> 0, 1, -1)))
            * IF(movimiento.fk_ordenadmin_id <> 0, -1, 1)
            * IF(movimiento.fk_moneda_id <> '{$basica}', movimiento.cotizacion_moneda, 1))";
        $q = DB::table('movimiento')
            ->leftJoin('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->leftJoin('recibo', 'recibo.recibo_id', '=', 'movimiento.fk_recibo_id')
            ->whereIn('movimiento.auxiliar', [0, 1])
            ->where(fn ($w) => $w->whereNull('ordenadmin.ordenadmin_id')->orWhere('ordenadmin.status', '<>', 'AN'))
            ->where(fn ($w) => $w->whereNull('recibo.statusrecibo')->orWhere('recibo.statusrecibo', '<>', 'AN'))
            ->where(fn ($w) => $w->whereNull('ordenadmin.tipo')->orWhereIn('ordenadmin.tipo', ['O', 'A', 'C', 'P', 'M'])
                ->orWhere(fn ($w2) => $w2->where('ordenadmin.tipo', 'X')->whereRaw('YEAR(movimiento.fecha) <> ?', [(int) substr($hasta, 0, 4)])))
            ->where('movimiento.fecha', '>=', $desde)->where('movimiento.fecha', '<=', $hasta)
            ->where('movimiento.fk_plancuenta_id', '<>', 0)
            ->groupBy('movimiento.fk_plancuenta_id')
            ->select(['movimiento.fk_plancuenta_id as cuenta', DB::raw("SUM(IF({$sub} > 0, ABS({$sub}), 0)) AS debe"), DB::raw("SUM(IF({$sub} > 0, 0, ABS({$sub}))) AS haber")]);
        $sumas = [];
        foreach ($q->get() as $r) {
            $sumas[(int) $r->cuenta] = ['debe' => round((float) $r->debe, $dec), 'haber' => round((float) $r->haber, $dec)];
        }

        $tipos = [];
        foreach (['activo', 'pasivo', 'ganancia', 'perdida'] as $t) {
            $id = (int) SysconfigHelper::get($t, 0);
            if ($id !== 0) {
                $tipos[$id] = $t;
            }
        }
        $cuentas = DB::table('plancuenta')->orderBy('plancuenta_codigo')->get(['plancuenta_id', 'plancuenta_nombre', 'plancuenta_codigo', 'fk_plancuenta_id']);
        $hijos = [];
        foreach ($cuentas as $c) {
            $hijos[(int) $c->fk_plancuenta_id][] = $c;
        }

        $filas = [];
        $tot = array_fill_keys(self::COLUMNAS, 0.0);
        $recorrer = function (int $padre, int $nivel, string $tipo) use (&$recorrer, &$filas, &$tot, $hijos, $sumas, $tipos) {
            foreach ($hijos[$padre] ?? [] as $c) {
                $id = (int) $c->plancuenta_id;
                $tipoCta = $nivel === 0 ? ($tipos[$id] ?? '') : $tipo;
                $fila = ['cuenta_id' => $id, 'nivel' => $nivel, 'codigo' => (string) $c->plancuenta_codigo, 'nombre' => str_repeat('  ', $nivel).$c->plancuenta_nombre, 'tipo' => $tipoCta] + array_fill_keys(self::COLUMNAS, null);
                if (isset($sumas[$id]) && $nivel > 0) {
                    $debe = $sumas[$id]['debe'];
                    $haber = $sumas[$id]['haber'];
                    $saldo = $debe - $haber;
                    $fila['debe'] = $debe;
                    $fila['haber'] = $haber;
                    $saldo > 0 ? $fila['deudor'] = abs($saldo) : $fila['acreedor'] = abs($saldo);
                    $col = match (true) {
                        $tipoCta === 'activo' && $saldo < 0 => 'pasivo',
                        $tipoCta === 'pasivo' && $saldo > 0 => 'activo',
                        $tipoCta === 'ganancia' && $saldo < 0 => 'perdida',
                        $tipoCta === 'perdida' && $saldo > 0 => 'ganancia',
                        default => $tipoCta,
                    };
                    if ($col !== '') {
                        $fila[$col] = abs($saldo);
                    }
                    foreach (self::COLUMNAS as $k) {
                        $tot[$k] += (float) ($fila[$k] ?? 0);
                    }
                }
                $filas[] = $fila;
                $recorrer($id, $nivel + 1, $tipoCta);
            }
        };
        $recorrer(0, 0, '');

        $filas[] = ['cuenta_id' => 0, 'nivel' => 0, 'codigo' => '', 'nombre' => 'SUMAS', 'tipo' => ''] + array_map(fn ($v) => round($v, $dec), $tot);
        $ra = abs($tot['activo']) - abs($tot['pasivo']);
        $rg = abs($tot['perdida']) - abs($tot['ganancia']);
        $filas[] = ['cuenta_id' => 0, 'nivel' => 0, 'codigo' => '', 'nombre' => 'RESULTADO', 'tipo' => '', 'debe' => null, 'haber' => null, 'deudor' => null, 'acreedor' => null,
            'activo' => $ra > 0 ? round(abs($ra), $dec) : null, 'pasivo' => $ra <= 0 ? round(abs($ra), $dec) : null,
            'perdida' => $rg < 0 ? round(abs($rg), $dec) : null, 'ganancia' => $rg >= 0 ? round(abs($rg), $dec) : null];

        return $filas;
    }
}
