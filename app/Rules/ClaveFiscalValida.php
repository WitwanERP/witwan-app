<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida el número de clave fiscal según el país de la licencia y el tipo de
 * clave fiscal elegido:
 *
 *   - Licencia AR + tipo CUIT/CUIL  → 11 dígitos con dígito verificador (módulo 11).
 *   - Licencia CL + tipo RUT        → RUT chileno (cuerpo + DV módulo 11, DV 0-9/K).
 *
 * Para cualquier otra combinación (otro país, otro tipo, o tipo desconocido)
 * no se valida el formato: la clave puede ser un pasaporte, DNI, NIF, RUC, etc.
 *
 * El tipo se evalúa por NOMBRE (no por id): el `tipoclavefiscal_id` no es
 * consistente entre tenants (p. ej. el id 9 es CUIL en AR y RUT en CL).
 */
class ClaveFiscalValida implements ValidationRule
{
    public function __construct(
        private ?string $paisLicencia,
        private ?string $tipoNombre,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // El form ya limpia separadores; reforzamos por las dudas (dejamos K para RUT).
        $valor = preg_replace('/[^0-9kK]/', '', (string) $value);

        if ($valor === '') {
            return; // vacío lo maneja la regla 'required'
        }

        $pais = strtoupper(trim((string) $this->paisLicencia));
        $tipo = strtoupper((string) $this->tipoNombre);

        if ($pais === 'AR' && (str_contains($tipo, 'CUIT') || str_contains($tipo, 'CUIL'))) {
            if (! self::cuitValido($valor)) {
                $fail('El CUIT/CUIL no es válido (dígito verificador incorrecto).');
            }

            return;
        }

        if ($pais === 'CL' && str_contains($tipo, 'RUT')) {
            if (! self::rutValido($valor)) {
                $fail('El RUT no es válido (dígito verificador incorrecto).');
            }
        }
    }

    /** CUIT/CUIL argentino: 11 dígitos con DV por módulo 11. */
    public static function cuitValido(string $valor): bool
    {
        $digitos = preg_replace('/\D/', '', $valor);

        if (strlen($digitos) !== 11) {
            return false;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += ((int) $digitos[$i]) * $pesos[$i];
        }

        $dv = 11 - ($suma % 11);
        if ($dv === 11) {
            $dv = 0;
        } elseif ($dv === 10) {
            return false; // combinación no asignable
        }

        return $dv === (int) $digitos[10];
    }

    /** RUT chileno: cuerpo numérico + DV (0-9 o K) por módulo 11. */
    public static function rutValido(string $valor): bool
    {
        $valor = strtoupper($valor);

        if (strlen($valor) < 2) {
            return false;
        }

        $dv = substr($valor, -1);
        $cuerpo = substr($valor, 0, -1);

        if (! ctype_digit($cuerpo)) {
            return false;
        }

        $suma = 0;
        $factor = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += ((int) $cuerpo[$i]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $dvEsperado = match (11 - ($suma % 11)) {
            11 => '0',
            10 => 'K',
            default => (string) (11 - ($suma % 11)),
        };

        return $dv === $dvEsperado;
    }
}
