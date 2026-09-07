<?php

namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST del generador de reservas: cabecera del file + servicios[] con nómina.
 * Es el contrato que arma el carrito del front (Pages/Reservas/Generador) y
 * que consume GeneradorReservaService::crear(); la validación de dominio
 * (cliente del área, fechas mínimas, crédito, etc.) vive en el servicio.
 */
class NuevaReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fk_cliente_id' => 'required|integer',
            'titular_nombre' => 'required|string|max:150',
            'titular_apellido' => 'required|string|max:150',
            'titular_email' => 'nullable|email|max:50',
            'titular_celular' => 'nullable|string|max:50',
            'fk_moneda_id' => 'required|string|max:3',
            'residente' => 'nullable|in:R,N',
            'agente' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'fecha_vencimiento' => 'nullable|date_format:Y-m-d',
            'forzar_credito' => 'nullable|boolean',
            'servicios' => 'required|array|min:1',
            'servicios.*.fk_tipoproducto_id' => 'required|string|max:3',
            'servicios.*.servicio_nombre' => 'required|string|max:200',
            'servicios.*.fk_proveedor_id' => 'nullable|integer',
            'servicios.*.fk_prestador_id' => 'nullable|integer',
            'servicios.*.fk_producto_id' => 'nullable|integer',
            'servicios.*.fk_tarifacategoria_id' => 'nullable|integer',
            'servicios.*.fk_regimen_id' => 'nullable|integer',
            'servicios.*.fk_base_id' => 'nullable|string|max:3',
            'servicios.*.fk_ciudad_id' => 'nullable|integer',
            'servicios.*.vigencia_ini' => 'required|date_format:Y-m-d',
            'servicios.*.vigencia_fin' => 'nullable|date_format:Y-m-d',
            'servicios.*.adultos' => 'nullable|integer|min:0',
            'servicios.*.menores' => 'nullable|integer|min:0',
            'servicios.*.infante' => 'nullable|integer|min:0',
            'servicios.*.juniors' => 'nullable|integer|min:0',
            'servicios.*.fk_moneda_id' => 'required|string|max:3',
            'servicios.*.moneda_costo' => 'nullable|string|max:3',
            'servicios.*.total' => 'required|numeric|min:0',
            'servicios.*.costo' => 'nullable|numeric|min:0',
            'servicios.*.iva' => 'nullable|numeric|min:0',
            'servicios.*.iva_costo' => 'nullable|numeric|min:0',
            'servicios.*.impuestos' => 'nullable|numeric|min:0',
            'servicios.*.status' => 'nullable|in:CO,RQ',
            'servicios.*.nro_confirmacion' => 'nullable|string|max:200',
            'servicios.*.comentarios' => 'nullable|string',
            'servicios.*.vencimiento_proveedor' => 'nullable|date_format:Y-m-d',
            'servicios.*.servicio_extra' => 'nullable|array',
            'servicios.*.servicio_extra.pickup' => 'nullable|string|max:200',
            'servicios.*.servicio_extra.dropoff' => 'nullable|string|max:200',
            'servicios.*.servicio_extra.hora_pickup' => 'nullable|string|max:10',
            'servicios.*.pasajeros' => 'nullable|array',
            'servicios.*.pasajeros.*.nombre' => 'nullable|string|max:100',
            'servicios.*.pasajeros.*.apellido' => 'nullable|string|max:100',
            'servicios.*.pasajeros.*.documento' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.nacionalidad' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.tipopax' => 'nullable|string|max:3',
            'servicios.*.pasajeros.*.nacimiento' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.telefono' => 'nullable|string|max:50',
            'servicios.*.pasajeros.*.edad' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'servicios.required' => 'La reserva necesita al menos un servicio.',
            'servicios.min' => 'La reserva necesita al menos un servicio.',
            'date_format' => 'El campo :attribute debe tener formato aaaa-mm-dd.',
        ];
    }
}
