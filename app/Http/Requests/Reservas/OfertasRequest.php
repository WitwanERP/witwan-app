<?php

namespace App\Http\Requests\Reservas;

/**
 * POST /app/reservas/{área}/nueva/ofertas: los mismos parámetros de la búsqueda
 * más el producto elegido, la opción por habitación y el total que está viendo
 * el vendedor (para calcular diferencias).
 */
class OfertasRequest extends BusquedaRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'producto_id' => 'required|integer|min:1',
            'ciudad_id' => 'nullable|integer|min:0',
            'elegidas' => 'nullable|array|max:6',
            'elegidas.*.categoria' => 'nullable|integer|min:0',
            'elegidas.*.regimen' => 'nullable|integer|min:0',
            'total_elegido' => 'nullable|numeric|min:0',
            'moneda' => 'nullable|string|max:3',
            'que' => 'nullable|array',
            'que.*' => 'in:fechas,promociones,historial',
        ]);
    }
}
