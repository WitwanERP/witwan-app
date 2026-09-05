<?php

namespace App\Exceptions\Productos;

use RuntimeException;

/**
 * Error de negocio del módulo de productos. Lleva opcionalmente los errores por
 * campo para que el controller los devuelva como errores de validación.
 */
class ProductoException extends RuntimeException
{
    /** @var array<string,string> */
    private array $errores = [];

    /** @param array<string,string> $errores */
    public static function porCampos(array $errores, string $mensaje = 'Hay errores en los datos cargados.'): static
    {
        $e = new static($mensaje);
        $e->errores = $errores;

        return $e;
    }

    /** @return array<string,string> */
    public function errores(): array
    {
        return $this->errores;
    }
}
