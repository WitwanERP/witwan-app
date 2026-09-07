<?php

namespace App\Services\Reservas\Busqueda;

/**
 * Presupuesto de tiempo de una búsqueda. Cotizar N productos × habitaciones
 * con el Tarifador es caro; cuando se agota, el buscador corta el loop y la
 * respuesta sale marcada como truncada para que el front sugiera acotar.
 */
final class Presupuesto
{
    private float $inicio;

    public function __construct(private int $limiteMs)
    {
        $this->inicio = microtime(true);
    }

    public function transcurridoMs(): int
    {
        return (int) round((microtime(true) - $this->inicio) * 1000);
    }

    public function agotado(): bool
    {
        return $this->limiteMs > 0 && $this->transcurridoMs() >= $this->limiteMs;
    }
}
