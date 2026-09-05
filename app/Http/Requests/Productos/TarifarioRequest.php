<?php

namespace App\Http\Requests\Productos;

use Illuminate\Foundation\Http\FormRequest;

/** Forma del payload de un tarifario con sus filas de markup/comisión. */
class TarifarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fecha = 'nullable|date_format:Y-m-d,d/m/Y';

        return [
            'tarifario_nombre' => 'required|string|max:100',
            'fk_moneda_id' => 'required|string|max:3',
            'cotizacion' => 'nullable|numeric|min:0',
            'interno' => 'nullable|boolean',
            'orden' => 'nullable|integer',

            'comisiones' => 'nullable|array',
            'comisiones.*.tarifariocomision_id' => 'nullable|integer|min:0',
            'comisiones.*.fk_submodulo_id' => 'nullable|string|max:3',
            'comisiones.*.fk_pais_id' => 'nullable|integer|min:0',
            'comisiones.*.fk_ciudad_id' => 'nullable|integer|min:0',
            'comisiones.*.fk_producto_id' => 'nullable|integer|min:0',
            'comisiones.*.origen' => 'nullable|string|max:3',
            'comisiones.*.divisor_markup' => 'required_with:comisiones|numeric|gt:0',
            'comisiones.*.porcentaje_comision' => 'nullable|numeric|min:0|max:100',
            'comisiones.*.vigencia_ini' => $fecha,
            'comisiones.*.vigencia_fin' => $fecha,
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'comisiones.*.divisor_markup.gt' => 'El divisor de markup debe ser mayor que 0.',
        ];
    }
}
