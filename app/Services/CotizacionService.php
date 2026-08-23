<?php

namespace App\Services;

use App\Models\Moneda;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cotización de monedas a una fecha.
 *
 * Port de `ajax.php::cotizarmoneda2()` (application/controllers/ajax.php:216-237):
 * toma la última cotización con fecha menor o igual a la pedida y devuelve
 * `cotizacion_costo`, cayendo a `cotizacion_relacion` cuando la primera es 0.
 * Esa es justo la diferencia con `cotizarmoneda()` (que usa la de venta) y es
 * fácil de perder al portar: las facturas de proveedor se valúan al costo.
 *
 * Vive fuera del namespace de Documentos porque lo va a necesitar cualquier otro
 * comprobante que se migre.
 */
class CotizacionService
{
    /** Cotización al costo. 1 si la moneda es la básica del tenant. */
    public function alCosto(string $moneda, ?string $fecha = null): float
    {
        if ($moneda === '' || $moneda === $this->monedaBasica()) {
            return 1.0;
        }

        $fila = DB::table('cotizacion')
            ->where('cotizacion_moneda', $moneda)
            ->where('cotizacion_fecha', '<=', $this->fecha($fecha))
            ->orderByDesc('cotizacion_fecha')
            ->first(['cotizacion_costo', 'cotizacion_relacion']);

        if ($fila === null) {
            return 0.0;
        }

        return (float) ($fila->cotizacion_costo != 0 ? $fila->cotizacion_costo : $fila->cotizacion_relacion);
    }

    /**
     * Cotización de relación ("de venta").
     *
     * Port de `cotizarmoneda()` (application/helpers/admin_helper.php:140-159),
     * la hermana de `cotizarmoneda2()`: misma búsqueda pero devuelve
     * `cotizacion_relacion`. Es la que usa el Admin_Controller para armar el mapa
     * `_ctz` con el que se valúan los movimientos de fondo (fondos.php:229).
     *
     * Dos matices frente al helper del CI, los mismos que ya aplica alCosto():
     *
     *  - La moneda básica devuelve 1 sin consultar. El legacy la consulta igual y
     *    suele devolver 0 (no hay fila de cotización de la moneda contra sí
     *    misma), con lo que el movimiento queda valuado en 0.
     *  - Para el resto, si no hay cotización cargada devuelve 0, igual que el
     *    legacy: forzar un 1 escondería que a esa moneda le falta la cotización.
     *    El front avisa cuando la sugerida viene en 0.
     */
    public function aLaVenta(string $moneda, ?string $fecha = null): float
    {
        if ($moneda === '' || $moneda === $this->monedaBasica()) {
            return 1.0;
        }

        $valor = DB::table('cotizacion')
            ->where('cotizacion_moneda', $moneda)
            ->where('cotizacion_fecha', '<=', $this->fecha($fecha))
            ->orderByDesc('cotizacion_fecha')
            ->value('cotizacion_relacion');

        return (float) ($valor ?? 0);
    }

    /**
     * Cotizaciones del día para el header (equivalente al dropdown `fa-usd` del
     * CI, que lista la fecha y la relación de cada moneda de la licencia).
     *
     * Devuelve la básica en 1 y omite las monedas sin cotización cargada, para
     * no mostrar un 0 que se leería como "vale cero".
     */
    public function ultimas(?string $fecha = null): array
    {
        $dia = $this->fecha($fecha);
        $basica = $this->monedaBasica();

        $ultima = DB::table('cotizacion as c')
            ->select('c.cotizacion_relacion')
            ->whereColumn('c.cotizacion_moneda', 'm.moneda_id')
            ->where('c.cotizacion_fecha', '<=', $dia)
            ->orderByDesc('c.cotizacion_fecha')
            ->limit(1);

        $monedas = DB::table('moneda as m')
            ->select('m.moneda_id')
            ->selectSub($ultima, 'valor')
            ->orderBy('m.orden')
            ->get();

        $lista = [];
        foreach ($monedas as $fila) {
            $moneda = (string) $fila->moneda_id;
            $valor = $moneda === $basica ? 1.0 : (float) ($fila->valor ?? 0);

            if ($valor <= 0) {
                continue;
            }

            $lista[] = ['moneda' => $moneda, 'valor' => number_format($valor, 4, '.', '')];
        }

        return [
            'fecha' => Carbon::parse($dia)->format('d/m/Y'),
            'monedas' => $lista,
        ];
    }

    /** Moneda básica del tenant (columna 'Y'/'N', igual que Admin_Controller.php:642). */
    public function monedaBasica(): string
    {
        return (string) (Moneda::query()->where('moneda_basica', 'Y')->value('moneda_id') ?: 'ARS');
    }

    /** Acepta ISO y dd/mm/YYYY; sin fecha, hoy. */
    private function fecha(?string $fecha): string
    {
        if ($fecha === null || $fecha === '' || $fecha === 'undefined') {
            return Carbon::today()->format('Y-m-d');
        }

        if (preg_match('#^(\d{2})[/-](\d{2})[/-](\d{4})$#', $fecha, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($fecha, 0, 10);
    }
}
