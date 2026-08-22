<?php

namespace App\Exceptions\Documentos;

use RuntimeException;

/**
 * Errores de negocio del módulo de Facturas de Tercero.
 *
 * Reemplaza los `die("...")` del CI legacy (que cortaban la respuesta a mitad,
 * dejando al usuario en una página en blanco) y los `response()->json()`
 * sembrados dentro del controller. Cada caso trae su código HTTP, así que la API
 * responde el status correcto y el front Inertia puede mostrar el mensaje tal
 * cual: son textos pensados para el usuario final.
 */
class FacturaproveedorException extends RuntimeException
{
    private function __construct(string $mensaje, private readonly int $codigoHttp)
    {
        parent::__construct($mensaje);
    }

    public function codigoHttp(): int
    {
        return $this->codigoHttp;
    }

    /** factura3ero.php:1474-1477 / :1527-1531 — cierrecaja posterior a la fecha contable. */
    public static function periodoCerrado(): self
    {
        return new self('Fecha fuera del periodo contable abierto.', 422);
    }

    /** factura3ero.php:1535-1538 — existe una orden de pago no anulada. */
    public static function yaPagada(): self
    {
        return new self('No es posible eliminar una factura con pago realizado.', 422);
    }

    /** factura3ero.php:864-867 — mismo número, proveedor y tipo de documento. */
    public static function duplicada(string $nro): self
    {
        return new self("Ya existe una factura con el número {$nro} para ese proveedor y tipo de documento.", 409);
    }

    /** El legacy no lo validaba en el servidor: sólo el JS impedía guardar en 0. */
    public static function totalCero(): self
    {
        return new self('Debe ingresar al menos un monto: el total de la factura no puede ser 0.', 422);
    }

    /**
     * Cuenta contable sin configurar en `sysconfig`. El legacy la resolvía a 0 y
     * generaba el movimiento igual, dejando el asiento apuntando a una cuenta
     * inexistente sin avisar a nadie.
     */
    public static function cuentaNoConfigurada(string $clave): self
    {
        return new self("La cuenta contable '{$clave}' no está configurada para esta licencia.", 422);
    }

    /** La imputación por área debe sumar 100 (o 100/200 en Chile). */
    public static function imputacionInvalida(float $suma, array $validas): self
    {
        $esperado = implode(' o ', array_map(fn ($v) => (string) $v, $validas));

        return new self("La imputación por área suma {$suma}%: debe sumar {$esperado}%.", 422);
    }

    /** Gasto y Boleta generan su propio servicio contable; no admiten ocupaciones. */
    public static function noAdmiteOcupaciones(string $tipoMovimiento): self
    {
        return new self("Las facturas de tipo {$tipoMovimiento} no admiten servicios: el servicio se genera automáticamente.", 422);
    }

    /**
     * Falta una base hermana del split SECONTUR. El port original la salteaba en
     * silencio, con lo que la factura se contabilizaba sólo en parte.
     */
    public static function baseDeSplitAusente(string $conexion): self
    {
        return new self("La conexión '{$conexion}' no está configurada: no se puede dividir la factura.", 500);
    }
}
