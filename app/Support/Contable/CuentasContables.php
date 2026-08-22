<?php

namespace App\Support\Contable;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Helpers\SysconfigHelper;

/**
 * Resolución de las cuentas contables configurables de la licencia.
 *
 * En el CI cada clave de `sysconfig` se publica como constante PHP global
 * (`Admin_Controller.php:600-621` hace `define($key, $value)`), así que el código
 * contable escribe `fc3exento` a secas. Acá se resuelven por nombre, en un solo
 * acceso a `sysconfig` (cacheado por tenant) en vez de una consulta por cuenta.
 *
 * Diferencia deliberada con el legacy: una cuenta no configurada era `0` y el
 * movimiento se grababa igual, apuntando a una cuenta inexistente sin ningún
 * aviso. Acá revienta con un mensaje que nombra la clave que falta.
 *
 * Es instanciable (no estática) para poder inyectar un mapa fijo en los tests.
 */
final class CuentasContables
{
    /** @param  array<string,mixed>  $valores  clave de sysconfig => id de plancuenta */
    public function __construct(private readonly array $valores) {}

    /** Instancia con la configuración de la licencia activa. */
    public static function paraLicenciaActual(): self
    {
        return new self(SysconfigHelper::all() ?: []);
    }

    /**
     * Id de plan de cuentas para una clave.
     *
     * @throws FacturaproveedorException si no está configurada.
     */
    public function id(string $clave): int
    {
        $id = $this->idONull($clave);

        if ($id === null) {
            throw FacturaproveedorException::cuentaNoConfigurada($clave);
        }

        return $id;
    }

    /** Igual que id() pero devuelve null en vez de fallar. */
    public function idONull(string $clave): ?int
    {
        $valor = $this->valores[$clave] ?? null;

        if ($valor === null || $valor === '' || (int) $valor === 0) {
            return null;
        }

        return (int) $valor;
    }

    /**
     * Resuelve varias claves de una vez.
     *
     * @param  list<string>  $claves
     * @return array<string,int>
     */
    public function todas(array $claves): array
    {
        $out = [];

        foreach ($claves as $clave) {
            $out[$clave] = $this->id($clave);
        }

        return $out;
    }

    /**
     * Claves sin configurar, para validar la licencia antes de dejar cargar una
     * factura en vez de descubrirlo a mitad del asiento.
     *
     * @param  list<string>  $claves
     * @return list<string>
     */
    public function faltantes(array $claves): array
    {
        return array_values(array_filter($claves, fn ($c) => $this->idONull($c) === null));
    }
}
