<?php

namespace App\Http\Requests\Productos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forma del payload de una vigencia: cabecera + grilla.
 *
 * La grilla llega en una de dos formas según el tipo de producto:
 *   tarifas: [{categoria, base, costo, venta: {tarifarioId: valor}}]   (alojamiento)
 *   tramos:  [{min, max, costos: {ADU: ..}, venta: {tarifarioId: {ADU: ..}}}] (resto)
 *
 * Las reglas de negocio (fechas coherentes, días, solapes, celdas dentro de
 * la grilla del producto) las aplica VigenciaService con VigenciaReglas.
 */
class VigenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fecha = 'nullable|date_format:Y-m-d,d/m/Y';
        $max = (int) config('productos.vencimiento_max', 120);

        return [
            'vigencia_ini' => 'required|date_format:Y-m-d,d/m/Y',
            'vigencia_fin' => 'required|date_format:Y-m-d,d/m/Y',
            'vigencia_ventaini' => $fecha,
            'vigencia_ventafin' => $fecha,
            'vencimiento_promocion' => $fecha,
            'vigencia_descripcion' => 'nullable|string|max:2000',
            'comentarios' => 'nullable|string',
            'nota_promocion' => 'nullable|string',
            'residente' => 'nullable|string|in:,R,O',
            'vigencia_prioridad' => 'nullable|integer|min:0|max:99',
            'vigencia_promocional' => 'nullable|boolean',
            'acumulable' => 'nullable|boolean',
            'web' => 'nullable|boolean',
            'cargamanual' => 'nullable|boolean',
            'promo_noches' => 'nullable|integer|min:0|max:99',
            'promo_pornoches' => 'nullable|integer|min:0|max:99',
            'vencimiento_reserva' => "nullable|integer|min:0|max:{$max}",
            'vencimiento_checkin' => "nullable|integer|min:0|max:{$max}",
            'noches_minimas' => 'nullable|integer|min:0|max:99',
            'modo_nochesminimas' => 'nullable|string|in:,X,T',
            'noches' => 'nullable|integer|min:0|max:99',
            'dias' => 'nullable|integer|min:0|max:99',
            'fk_regimen_id' => 'nullable|integer|min:0',
            'fk_tarifacategoria_id' => 'nullable|integer|min:0',

            'dias_semana' => 'required|array|min:1|max:7',
            'dias_semana.*' => 'integer|min:1|max:7',

            'moneda_costo' => 'required|string|max:3',
            'impuestos' => 'nullable|numeric|min:0',
            'impuestos_menor' => 'nullable|numeric|min:0',
            'redondeo' => 'nullable|string|in: ,R,C',

            'alojamientos' => 'nullable|array',
            'alojamientos.*.vigenciaalojamiento_id' => 'nullable|integer|min:0',
            'alojamientos.*.fk_producto_id' => 'nullable|integer|min:0',
            'alojamientos.*.fk_tarifacategoria_id' => 'nullable|integer|min:0',
            'alojamientos.*.fk_regimen_id' => 'nullable|integer|min:0',
            'alojamientos.*.noches' => 'nullable|integer|min:0',
            'alojamientos.*.ncategoria' => 'nullable|string|max:255',

            'tarifas' => 'nullable|array',
            'tarifas.*.categoria' => 'required_with:tarifas|integer|min:0',
            'tarifas.*.base' => 'required_with:tarifas|string|max:3',
            'tarifas.*.costo' => 'nullable|numeric|min:0',
            'tarifas.*.venta' => 'nullable|array',
            'tarifas.*.venta.*' => 'nullable|numeric|min:0',

            'tramos' => 'nullable|array',
            'tramos.*.min' => 'required_with:tramos|integer|min:0',
            'tramos.*.max' => 'required_with:tramos|integer|min:0',
            'tramos.*.costos' => 'nullable|array',
            'tramos.*.costos.*' => 'nullable|numeric|min:0',
            'tramos.*.venta' => 'nullable|array',
            'tramos.*.venta.*' => 'nullable|array',
            'tramos.*.venta.*.*' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date_format' => 'El campo :attribute debe tener formato dd/mm/aaaa.',
            'dias_semana.required' => 'Debe marcar al menos un día de la semana.',
            'dias_semana.min' => 'Debe marcar al menos un día de la semana.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute no puede ser negativo.',
        ];
    }
}
