<?php

namespace App\Services\Reservas;

use Illuminate\Support\Facades\DB;

/**
 * Recalcula el monto cobrado de una reserva, fiel a reserva_model.php::cobrado()
 * (línea 2517). El listado usa este valor (no el campo crudo reserva.cobrado)
 * para mostrar Cobrado/Saldo.
 *
 * Suma los movimientos de recibos imputados al file (y a sus files agrupados),
 * filtrando por las cuentas de recibos del tenant (`ctasrecibos`) y convirtiendo
 * cada importe a la moneda de la reserva vía la tabla `cotizacion`.
 *
 * Dependencias del legacy replicadas:
 *  - monedabasica: moneda con moneda_basica='Y' (default ARS). Admin_Controller:642.
 *  - ctasrecibos:  ids de cuenta en sysconfig (cuentarecibos[usd], anticiporecibos[usd],
 *    auxrecibos); si no hay, [54]. Admin_Controller:606-621.
 */
class ReservaCobradoService
{
    private ?string $monedaBasica = null;

    /** @var list<int>|null */
    private ?array $ctasRecibos = null;

    /** @var array<int,list<int>> cache de hijos por reserva */
    private array $hijosCache = [];

    /** Total cobrado de la reserva expresado en su moneda. */
    public function total(int $reservaId, ?string $moneda): float
    {
        $moneda = (string) $moneda;
        $files = $this->hijos($reservaId);
        $ctas = $this->ctasRecibos();
        $monedabasica = $this->monedaBasica();

        if (empty($files) || empty($ctas)) {
            return 0.0;
        }

        $rows = DB::table('rel_filerecibo')
            ->join('movimiento', 'movimiento.fk_recibo_id', '=', 'rel_filerecibo.fk_recibo_id')
            ->join('recibo', 'recibo.recibo_id', '=', 'rel_filerecibo.fk_recibo_id')
            ->whereIn('movimiento.cuenta_debito', $ctas)
            ->whereIn('rel_filerecibo.fk_file_id', $files)
            ->where('recibo.statusrecibo', '!=', 'AN')
            ->groupBy('rel_filerecibo.fk_file_id', 'rel_filerecibo.fk_recibo_id')
            ->get([
                'rel_filerecibo.monto',
                'rel_filerecibo.fk_moneda_id',
                'movimiento.cotizacion_moneda',
                DB::raw('DATE(movimiento.fecha) as fecha_iso'),
            ]);

        $total = 0.0;
        foreach ($rows as $r) {
            $cotizacion = 1.0;
            $cotizacionppal = 0.0;

            if ($moneda !== (string) $r->fk_moneda_id) {
                $cotizacionppal = (float) (DB::table('cotizacion')
                    ->where('cotizacion_moneda', $moneda)
                    ->where('cotizacion_fecha', '<=', $r->fecha_iso)
                    ->orderByDesc('cotizacion_fecha')
                    ->value('cotizacion_relacion') ?? 0);

                if ((string) $r->fk_moneda_id !== $monedabasica) {
                    $cotizacion = (float) $r->cotizacion_moneda;
                } else {
                    $cotizacion = 1.0;
                    if ((float) $r->cotizacion_moneda !== 1.0) {
                        $cotizacionppal = (float) $r->cotizacion_moneda;
                    }
                }
            } else {
                $cotizacionppal = 1.0;
                $cotizacion = 1.0;
            }

            if ($cotizacion == 0.0) {
                $cotizacion = 1.0;
            }
            if ($cotizacionppal == 0.0) {
                $cotizacionppal = 1.0;
            }

            $relacion = $cotizacion / $cotizacionppal;
            $total += (float) $r->monto * $relacion;
        }

        return $total;
    }

    /** Reserva + descendientes agrupados (fk_agrupado_id), recursivo. CI buscarhijos(). */
    private function hijos(int $id): array
    {
        if (isset($this->hijosCache[$id])) {
            return $this->hijosCache[$id];
        }

        $acc = [$id];
        $hijos = DB::table('reserva')->where('fk_agrupado_id', $id)->pluck('reserva_id');
        foreach ($hijos as $h) {
            $h = (int) $h;
            foreach ($this->hijos($h) as $nieto) {
                if (! in_array($nieto, $acc, true)) {
                    $acc[] = $nieto;
                }
            }
        }

        return $this->hijosCache[$id] = $acc;
    }

    private function monedaBasica(): string
    {
        if ($this->monedaBasica === null) {
            $this->monedaBasica = (string) (DB::table('moneda')->where('moneda_basica', 'Y')->value('moneda_id') ?? 'ARS');
        }

        return $this->monedaBasica;
    }

    /** @return list<int> */
    private function ctasRecibos(): array
    {
        if ($this->ctasRecibos !== null) {
            return $this->ctasRecibos;
        }

        $keys = ['cuentarecibos', 'cuentarecibosusd', 'anticiporecibos', 'anticiporecibosusd', 'auxrecibos'];
        $valores = DB::table('sysconfig')->whereIn('sysconfig_key', $keys)->pluck('sysconfig_value');

        $ctas = [];
        foreach ($valores as $v) {
            if ((int) $v !== 0) {
                $ctas[(int) $v] = (int) $v;
            }
        }
        if (empty($ctas)) {
            $ctas[54] = 54;
        }

        return $this->ctasRecibos = array_values($ctas);
    }
}
