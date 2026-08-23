<?php

namespace App\Http\Requests\Contable;

use App\Support\Contable\TipoAsiento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del alta de un asiento de administración.
 *
 * El legacy no validaba NADA del lado del servidor: la grilla la controlaba el
 * JS y el `guardar()` concatenaba el POST directo en el SQL
 * (asientocontable.php:139-142, fondos.php:206). Un POST armado a mano grababa
 * un asiento descuadrado, sin líneas o con la fecha en cualquier formato.
 *
 * Lo que valida esta clase es la FORMA del payload; las reglas contables
 * (balance, líneas con cuenta pero sin importe, cuentas repetidas en fondos)
 * viven en PlanAsiento, porque son de negocio y las comparte la API.
 */
class AsientoRequest extends FormRequest
{
    /** La autorización se resuelve en el controller con App\Support\Permisos. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipo = TipoAsiento::desde((string) $this->route('tipo'));

        $reglas = [
            'fecha' => 'required|date_format:Y-m-d,d/m/Y',
            'fk_moneda_id' => 'required|string|max:3',
            'observaciones' => 'nullable|string|max:2000',

            'lineas' => 'required|array|min:1|max:'.(int) config('asientos.max_lineas', 500),
        ];

        if ($tipo->esDebeHaber()) {
            $reglas += [
                'cotizacion' => 'required|numeric|min:0',
                'lineas.*.cuenta' => 'nullable|integer|min:0',
                'lineas.*.descripcion' => 'nullable|string|max:500',
                'lineas.*.debe' => 'nullable|numeric',
                'lineas.*.haber' => 'nullable|numeric',
                'lineas.*.cliente' => 'nullable|integer|min:0',
                'lineas.*.proveedor' => 'nullable|integer|min:0',
                'lineas.*.file' => 'nullable|integer|min:0',
            ];

            if ($tipo->usaArqueo()) {
                $reglas['arqueo'] = 'nullable|boolean';
            }
            if ($tipo->usaAfectaCobranza()) {
                $reglas['afecta_cobranza'] = 'nullable|boolean';
            }
        } else {
            $reglas += [
                'descripcion' => 'nullable|string|max:500',
                'lineas.*.monto' => 'nullable|numeric',
                'lineas.*.ingreso' => 'nullable|integer|min:0',
                'lineas.*.egreso' => 'nullable|integer|min:0',
            ];
        }

        if ($tipo->usaProyecto()) {
            $reglas['fk_proyecto_id'] = 'nullable|integer|min:0';
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'lineas.required' => 'El asiento no tiene ninguna línea cargada.',
            'fecha.date_format' => 'La fecha tiene que estar en formato dd/mm/aaaa.',
        ];
    }
}
