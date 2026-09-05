<?php

namespace App\Http\Requests\Productos;

use Illuminate\Foundation\Http\FormRequest;

/** Alta de soldout por rango. Sin categoría (o `todos`) bloquea todas las habilitadas. */
class SoldoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fk_tarifacategoria_id' => 'nullable|integer|min:0',
            'todos' => 'nullable|boolean',
            'vigencia_ini' => 'required|date_format:Y-m-d,d/m/Y',
            'vigencia_fin' => 'required|date_format:Y-m-d,d/m/Y',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date_format' => 'El campo :attribute debe tener formato dd/mm/aaaa.',
        ];
    }
}
