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
