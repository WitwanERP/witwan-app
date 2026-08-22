<?php

namespace App\Services\Documentos;

/**
 * Un tramo de la división de una factura: qué proporción va a qué base y contra
 * qué factura se contabiliza.
 *
 * Reemplaza a la tupla posicional `[$prc, $conexion, $idFactura]` del port
 * original: con tres valores del mismo tipo genérico, invertir dos en un
 * destructuring no rompe nada visible pero contabiliza mal.
 */
final readonly class TramoSplit
{
    public function __construct(
        /** Proporción de la factura (1.0 = completa). Negativa en notas de crédito. */
        public float $proporcion,
        /** Conexión de la base hermana, o null para la base del tenant. */
        public ?string $conexion,
        /** Id de la factura contra la que se genera el asiento en esa base. */
        public int $facturaId,
    ) {}

    public function conSigno(bool $esNotaCredito): self
    {
        if (! $esNotaCredito) {
            return $this;
        }

        return new self(abs($this->proporcion) * -1, $this->conexion, $this->facturaId);
    }
}
