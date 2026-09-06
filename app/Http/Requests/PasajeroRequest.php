<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasajeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes|required';
        $opt = $this->isMethod('post') ? 'nullable' : 'sometimes|nullable';

        return [
            // Datos personales
            'pasajero_apellido' => "$req|string|max:100",
            'pasajero_nombre' => "$req|string|max:100",
            'pasajero_apodo' => "$opt|string|max:100",
            'pasajero_nacionalidad' => "$opt|string|max:100",
            'pasajero_nacimiento' => "$opt|string|max:100",
            'pasajero_sexo' => "$opt|string|in:M,F",
            'pasajero_email' => "$req|email|max:100",
            'fk_usuario_vendedor' => "$req|integer|min:0",
            'cargo' => "$opt|string|max:100",
            'habilita' => "$opt|string|in:Y,N",
            'freelance' => "$opt|string|in:Y,N",
            'observaciones' => "$opt|string",

            // Documento principal
            'tipodoc' => "$opt|string|max:15",
            'nrodoc' => "$opt|string|max:50",
            'emisordoc' => "$opt|integer|min:0",
            'emisorfecha' => "$opt|string|max:100",
            'vencimientodoc' => "$opt|string|max:100",

            // Domicilio fiscal
            'pasajero_direccionfiscal' => "$opt|string|max:100",
            'pasajero_codigopostal' => "$opt|string|max:100",
            'fk_pais_id' => "$opt|integer|min:0",
            'pasajero_ciudad' => "$opt|string|max:100",
            'fk_ciudad_id' => "$opt|integer|min:0",

            // Fiscal
            'fk_tipoclavefiscal_id' => "$opt|integer|min:0",
            'nro_clavefiscal' => "$opt|string|max:50",
            'fk_condicioniva_id' => "$opt|integer|min:0",

            // Gastos de reserva
            'fk_tarifario1_id' => "$opt|integer|min:0",
            'fk_tarifario2_id' => "$opt|integer|min:0",
            'fk_moneda_id' => "$opt|string|max:10",
            'gastos_iva' => "$opt|numeric",
            'gastos_porcentaje_1' => "$opt|numeric",
            'gastos_fijo_1' => "$opt|numeric",

            // Tilde: crear cliente a partir del pasajero
            'es_cliente' => 'sometimes|boolean',

            // Tags, extras JSON (pasajero_extra) y relaciones
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'integer',
            'documentos' => 'sometimes|nullable|array',
            'documentos.*.pasajero_doc_tipo' => 'nullable|string|max:30',
            'documentos.*.pasajero_doc_nro' => 'nullable|string|max:50',
            'documentos.*.pasajero_doc_paisemisor' => 'nullable',
            'documentos.*.pasajero_doc_emisorfecha' => 'nullable|string|max:20',
            'documentos.*.pasajero_doc_vencimiento' => 'nullable|string|max:20',
            'domicilios' => 'sometimes|nullable|array',
            'domicilios.*.pasajero_dom_tipo' => 'nullable|string|max:30',
            'domicilios.*.pasajero_direccion' => 'nullable|string|max:150',
            'domicilios.*.pasajero_codigopostal' => 'nullable|string|max:20',
            'domicilios.*.pasajero_dom_pais' => 'nullable',
            'domicilios.*.pasajero_provincia' => 'nullable|string|max:100',
            'domicilios.*.pasajero_ciudad' => 'nullable|string|max:100',
            'visas' => 'sometimes|nullable|array',
            'visas.*.visa_nro' => 'nullable|string|max:50',
            'visas.*.visa_pais' => 'nullable',
            'visas.*.visa_fecha' => 'nullable|string|max:20',
            'visas.*.visa_vence' => 'nullable|string|max:20',
            'telefonos' => 'sometimes|nullable|array',
            'telefonos.*.pasajero_tel_tipo' => 'nullable|string|max:30',
            'telefonos.*.pasajero_tel_codpais' => 'nullable|string|max:10',
            'telefonos.*.pasajero_tel_codarea' => 'nullable|string|max:10',
            'telefonos.*.pasajero_telefono' => 'nullable|string|max:50',
            'emails' => 'sometimes|nullable|array',
            'emails.*.pasajero_correo_tipo' => 'nullable|string|max:30',
            'emails.*.pasajero_email' => 'nullable|string|max:150',
            'emails.*.pasajero_correo_noenviar' => 'nullable|integer|in:0,1',
            'frecuentes' => 'sometimes|nullable|array',
            'frecuentes.*.paxfrec_nombre' => 'nullable|string|max:100',
            'frecuentes.*.paxfrec_num' => 'nullable|string|max:50',
            'frecuentes.*.paxfrec_pin' => 'nullable|string|max:50',
            'pax_relacionados' => 'sometimes|nullable|array',
            'pax_relacionados.*.paxrel_id' => 'nullable|integer',
            'pax_relacionados.*.paxrel_vinculo' => 'nullable|string|max:50',
            'cliente_relacionados' => 'sometimes|nullable|array',
            'cliente_relacionados.*.clienterel_id' => 'nullable|integer',
            'cliente_relacionados.*.clienterel_vinculo' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'pasajero_apellido.required' => 'El apellido es obligatorio.',
            'pasajero_nombre.required' => 'El nombre es obligatorio.',
            'pasajero_email.required' => 'El email es obligatorio.',
            'pasajero_email.email' => 'El email debe ser una dirección válida.',
            'fk_usuario_vendedor.required' => 'El vendedor es obligatorio.',
        ];
    }
}
