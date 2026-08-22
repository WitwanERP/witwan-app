<?php

namespace App\Support;

use App\Helpers\SysconfigHelper;

/**
 * Acceso centralizado a la identidad de la licencia (tenant) en runtime, fiel a
 * las constantes del CI legacy:
 *
 *   - Licencia::base()  ==  CI `LICENCIA`  (string `licencia_base`, ej. 'witwan_rays')
 *   - Licencia::pais()  ==  CI `LICPAIS`   (ISO de 2 letras: AR/CL/DO/US)
 *
 * OJO: en Laravel `app('tenant')->licencia` es el id NUMÉRICO de la licencia; el
 * equivalente al `LICENCIA` de CI es `->base`. Todas las ramas `LICENCIA=='...'`
 * del legacy deben compararse contra base(), no contra el id.
 *
 * El catálogo de ramas por licencia/sistema vive en config/reservas.php; este
 * helper resuelve los flags booleanos para la licencia actual.
 */
class Licencia
{
    /** Equivalente a CI `LICENCIA` (licencia_base). Cadena vacía si no hay tenant. */
    public static function base(): string
    {
        if (! app()->bound('tenant')) {
            return '';
        }

        return (string) (app('tenant')->base ?? '');
    }

    /** Equivalente a CI `LICPAIS` (licencia_pais ISO de 2 letras). */
    public static function pais(): string
    {
        if (! app()->bound('tenant')) {
            return '';
        }

        return (string) (app('tenant')->pais ?? '');
    }

    /** True si la licencia actual es alguna de las indicadas. */
    public static function es(string ...$bases): bool
    {
        return in_array(self::base(), $bases, true);
    }

    /**
     * Equivalente a CI `$this->_t_l == 'secontur'` (Admin_Controller.php:682):
     * la FAMILIA de licencias secontur, que agrupa varias bases distintas.
     *
     * OJO: no confundir con la licencia `witwan_secontur` a secas. La familia
     * decide cosas de presentación (qué columnas se muestran en los listados);
     * la división multi-base de facturas se gatilla sólo con la base exacta
     * (`factura3ero.php:1040`).
     */
    public static function esFamiliaSecontur(): bool
    {
        $base = self::base();

        foreach (['secon', 'maldivas', 'morisan', 'alternativasur'] as $fragmento) {
            if (str_contains($base, $fragmento)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resuelve un flag declarativo para la licencia actual. Soporta dos formas en
     * el catálogo:
     *   - lista de licencias        => true si base() está en la lista
     *   - mapa licencia => valor    => devuelve el valor para base() (o null)
     *
     * La clave sin punto se busca en config/reservas.php (comportamiento
     * histórico); con punto se usa tal cual, para que otros módulos tengan su
     * propio catálogo (p. ej. 'facturaproveedor.areas_imputacion').
     */
    public static function flag(string $clave, mixed $default = false): mixed
    {
        $cat = config(str_contains($clave, '.') ? $clave : "reservas.{$clave}");

        if ($cat === null) {
            return $default;
        }

        // Mapa asociativo licencia => valor.
        if (is_array($cat) && array_is_list($cat) === false) {
            return $cat[self::base()] ?? $default;
        }

        // Lista de licencias => booleano de pertenencia.
        if (is_array($cat)) {
            return in_array(self::base(), $cat, true);
        }

        return $cat;
    }

    /** Lee un valor de la tabla sysconfig del tenant (fileauditado, marcar_reprogramado, …). */
    public static function sysconfig(string $key, mixed $default = null): mixed
    {
        return SysconfigHelper::get($key, $default);
    }
}
