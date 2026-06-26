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
