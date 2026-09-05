<?php

namespace App\Http\Requests\Productos;

use Illuminate\Foundation\Http\FormRequest;

/** Alta de cupo (allotment) sobre una categoría de un producto. */
class CupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fk_tarifacategoria_id' => 'required|integer|min:0',
            'vigencia_ini' => 'required|date_format:Y-m-d,d/m/Y',
            'vigencia_fin' => 'required|date_format:Y-m-d,d/m/Y',
            'cantidad' => 'required|integer|min:0|max:9999',
            'release' => 'nullable|integer|min:0|max:365',
            'bases' => 'nullable|array',
            'bases.*' => 'string|max:3',
            'fk_cliente_id' => 'nullable|integer|min:0',
            'fk_tarifario_id' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date_format' => 'El campo :attribute debe tener formato dd/mm/aaaa.',
            'cantidad.min' => 'La cantidad no puede ser negativa (0 = freesale).',
        ];
    }
}
