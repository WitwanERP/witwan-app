<?php

namespace App\Exceptions\Documentos;

use RuntimeException;

/**
 * Envuelve un error de negocio ocurrido en una fila concreta de la carga
 * múltiple, para que el mensaje diga cuál corregir.
 */
class FacturaproveedorExceptionEnFila extends RuntimeException
{
    public function __construct(
        public readonly int $fila,
        private readonly FacturaproveedorException $original,
    ) {
        parent::__construct("Documento {$fila}: {$original->getMessage()}", 0, $original);
    }

    public function codigoHttp(): int
    {
        return $this->original->codigoHttp();
    }
}
