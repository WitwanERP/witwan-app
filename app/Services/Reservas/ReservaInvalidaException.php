<?php

namespace App\Services\Reservas;

class ReservaInvalidaException extends \DomainException
{
    /** @param array<string,string> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('La reserva no pasó las validaciones: '.implode(' ', $errores));
    }
}
