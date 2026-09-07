<?php

namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;

/** POST /app/reservas/{área}/nueva/cross-selling: el servicio recién agregado al carrito. */
class CrossSellingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => 'required|string|size:3',
            'producto_id' => 'nullable|integer|min:0',
            'ciudad_id' => 'required|integer|min:1',
            'cliente_id' => 'required|integer|min:1',
            'residente' => 'nullable|in:R,N',
            'from' => 'required|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'habitaciones' => 'nullable|array|max:6',
            'habitaciones.*.ad' => 'required|integer|between:1,9',
            'habitaciones.*.mn' => 'nullable|array|max:5',
            'habitaciones.*.mn.*' => 'integer|between:0,17',
            'ad' => 'nullable|integer|between:0,50',
            'mn' => 'nullable|array|max:20',
            'mn.*' => 'integer|between:0,17',
        ];
    }
}
