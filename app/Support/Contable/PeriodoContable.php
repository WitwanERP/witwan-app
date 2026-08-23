<?php

namespace App\Support\Contable;

use App\Exceptions\Contable\AsientoException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Control del período contable abierto (tabla `cierrecaja`).
 *
 * Regla del legacy, repetida literal en una decena de controllers
 * (asientocontable.php:135, asientocta.php:135, fondos.php:198, 269, …):
 * si existe algún cierre de caja con fecha MAYOR O IGUAL a la del comprobante,
 * el período ya está cerrado y no se puede grabar ni anular en él.
 *
 * Dos cosas que el legacy hacía mal y acá no:
 *
 *  1. Al grabar, redirigía a `/dashboard/errormov`: una pantalla genérica que no
 *     dice qué pasó ni con qué fecha, y que además pierde lo cargado. Acá se
 *     lanza una excepción con la fecha del cierre que bloquea, y el formulario
 *     vuelve con los datos puestos.
 *  2. Al anular hacía `die("Fecha fuera del periodo contable abierto.")`, o sea
 *     una página en blanco con una línea de texto.
 */
final class PeriodoContable
{
    /** Fecha del cierre de caja más reciente, o null si nunca se cerró. */
    public function ultimoCierre(): ?string
    {
        $fecha = DB::table('cierrecaja')->max('cierrecaja_fecha');

        return $fecha ? substr((string) $fecha, 0, 10) : null;
    }

    /** ¿La fecha cae dentro del período abierto? */
    public function estaAbierto(string $fecha): bool
    {
        return $this->cierreQueBloquea($fecha) === null;
    }

    /**
     * Fecha del cierre que bloquea a `$fecha`, o null si no hay ninguno.
     *
     * Se devuelve el cierre MÁS ANTIGUO de los que bloquean, que es el que
     * explica el corte: es el primer día ya cerrado a partir de la fecha pedida.
     */
    public function cierreQueBloquea(string $fecha): ?string
    {
        $iso = self::normalizar($fecha);

        $cierre = DB::table('cierrecaja')
            ->where('cierrecaja_fecha', '>=', $iso)
            ->min('cierrecaja_fecha');

        return $cierre ? substr((string) $cierre, 0, 10) : null;
    }

    /** @throws AsientoException si el período está cerrado para esa fecha. */
    public function exigirAbierto(string $fecha): void
    {
        $cierre = $this->cierreQueBloquea($fecha);

        if ($cierre !== null) {
            throw AsientoException::periodoCerrado(self::normalizar($fecha), $cierre);
        }
    }

    /**
     * Primera fecha operable: el día siguiente al último cierre. La usa el front
     * como `min` del campo fecha, así el usuario ve el límite antes de cargar
     * todo el asiento y recién ahí enterarse.
     */
    public function primeraFechaOperable(): ?string
    {
        $cierre = $this->ultimoCierre();

        return $cierre ? Carbon::parse($cierre)->addDay()->format('Y-m-d') : null;
    }

    /** Acepta ISO y dd/mm/YYYY, como el resto del módulo. */
    public static function normalizar(string $fecha): string
    {
        if (preg_match('#^(\d{2})[/-](\d{2})[/-](\d{4})$#', $fecha, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($fecha, 0, 10);
    }
}
