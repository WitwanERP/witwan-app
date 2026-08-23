<?php

namespace App\Exceptions\Contable;

use RuntimeException;

/**
 * Errores de negocio de los asientos de administración (contable, cuenta
 * corriente y movimientos de fondo).
 *
 * Reemplaza los `die("...")` y los `redirect("/dashboard/errormov")` del CI
 * (asientocontable.php:135 y :238, asientocta.php:242, fondos.php:198), que
 * dejaban al usuario en una página en blanco o en un error genérico, y en el
 * caso del alta le hacían perder todo lo cargado.
 *
 * Los mensajes están escritos para el usuario final: el front los muestra tal
 * cual.
 */
class AsientoException extends RuntimeException
{
    private function __construct(string $mensaje, private readonly int $codigoHttp)
    {
        parent::__construct($mensaje);
    }

    public function codigoHttp(): int
    {
        return $this->codigoHttp;
    }

    /** Cierre de caja igual o posterior a la fecha del asiento. */
    public static function periodoCerrado(string $fecha, string $cierre): self
    {
        return new self(
            "El período contable está cerrado para el {$fecha}: hay un cierre de caja del {$cierre}. ".
            'Elegí una fecha posterior al último cierre.',
            422,
        );
    }

    /**
     * El legacy no lo validaba en el servidor: el balance lo controlaba sólo el
     * JS de la grilla, así que un POST directo grababa un asiento descuadrado y
     * la contabilidad quedaba rota sin que nadie se enterara.
     */
    public static function noBalancea(float $debe, float $haber): self
    {
        $dif = number_format(abs($debe - $haber), 2, ',', '.');

        return new self(
            'El asiento no balancea: debe '.number_format($debe, 2, ',', '.').
            ' contra haber '.number_format($haber, 2, ',', '.').
            " (diferencia {$dif}).",
            422,
        );
    }

    /** Tampoco lo validaba el servidor: se podía grabar un asiento sin una sola línea. */
    public static function sinLineas(): self
    {
        return new self('El asiento no tiene ninguna línea con importe.', 422);
    }

    /** Una línea con cuenta pero sin importe, o con importe en las dos columnas. */
    public static function lineaInvalida(int $nro, string $motivo): self
    {
        return new self("Línea {$nro}: {$motivo}", 422);
    }

    /** asientocontable.php:239-243 — movimiento conciliado con el banco. */
    public static function conciliado(): self
    {
        return new self('Alguno de los movimientos del asiento está conciliado con el banco.', 422);
    }

    public static function yaAnulado(): self
    {
        return new self('El asiento ya está anulado.', 422);
    }

    public static function noEncontrado(int $id): self
    {
        return new self("No existe el asiento #{$id}.", 404);
    }
}
