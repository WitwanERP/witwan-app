<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cuit')) {
            $this->merge([
                'cuit' => str_replace(['-', '.', ' '], '', (string) $this->input('cuit')),
            ]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('cliente');
        $req = $this->isMethod('post') ? 'required' : 'sometimes|required';
        $opt = $this->isMethod('post') ? 'nullable' : 'sometimes|nullable';

        return [
            // Datos generales
            'cliente_nombre' => "$req|string|max:250",
            'cliente_razonsocial' => "$req|string|max:250",
            'cliente_legajo' => "$opt|string|max:50",
            'cliente_direccionfiscal' => "$req|string|max:250",
            'cliente_codigopostal' => "$opt|string|max:20",
            'cliente_provincia' => "$opt|string|max:100",
            'cliente_ciudad' => "$opt|string|max:100",
            'fk_pais_id' => "$req|integer|exists:pais,pais_id",
            'fk_ciudad_id' => "$req|integer|exists:ciudad,ciudad_id",

            // Contacto
            'cliente_telefono' => "$opt|string|max:50",
            'cliente_fax' => "$opt|string|max:50",
            'cliente_email' => "$req|email|max:250",
            'cliente_email2' => "$opt|email|max:250",
            'cliente_emailadmin' => "$opt|email|max:250",

            // Fiscal
            'cuit' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('cliente', 'cuit')->ignore($id, 'cliente_id'),
            ],
            'cuit_internacional' => "$opt|string|max:50",
            'fk_tipoclavefiscal_id' => "$req|integer|exists:tipoclavefiscal,tipoclavefiscal_id",
            'fk_condicioniva_id' => "$req|integer|exists:condicioniva,condicioniva_id",
            'fk_tipofactura_id' => "$req|integer|exists:tipofactura,tipofactura_id",
            'nro_clavefiscal' => "$opt|string|max:50",

            // Comercial
            'fk_idioma_id' => "$opt|string|max:10",
            'limite_credito' => "$opt|numeric|min:0",
            'credito_habilitado' => "$opt|integer|in:0,1",
            'credito_utilizado' => "$opt|numeric",
            'plazo_pago' => "$opt|integer|min:0",
            'iata' => "$opt|string|max:20",
            'fk_cadenacliente_id' => "$opt|integer|min:0",
            'consolidador' => "$opt|string|max:1",

            // Tarifarios
            'fk_tarifario1_id' => "$opt|integer|min:0",
            'fk_tarifario2_id' => "$opt|integer|min:0",
            'fk_tarifario3_id' => "$opt|integer|min:0",

            // Vendedores / promotores
            'fk_usuario_vendedor' => "$opt|integer|min:0",
            'fk_usuario_promotor1' => "$opt|integer|min:0",
            'fk_usuario_promotor2' => "$opt|integer|min:0",
            'fk_usuario_promotor3' => "$opt|integer|min:0",
            'fk_usuario_promotor4' => "$opt|integer|min:0",

            // Gastos de reserva
            'fk_moneda_id' => "$opt|string|max:10",
            'gastos_iva' => "$opt|numeric",
            'gastos_porcentaje_1' => "$opt|numeric",
            'gastos_porcentaje_2' => "$opt|numeric",
            'gastos_porcentaje_3' => "$opt|numeric",
            'gastos_fijo_1' => "$opt|numeric",
            'gastos_fijo_2' => "$opt|numeric",
            'gastos_fijo_3' => "$opt|numeric",
            'gastos_fijo_moneda' => "$opt|string|max:10",

            // Flags char(1) Y/N (convención real de CI en la BD del tenant)
            'habilita' => "$opt|string|in:Y,N",
            'freelance' => "$opt|string|in:Y,N",
            'representante_geografico' => "$opt|string|in:Y,N",
            'usar_logo' => "$opt|string|in:Y,N",
            'autorizaws' => "$opt|integer|in:0,1",

            // Otros
            'clienteminorista' => "$opt|integer|in:0,1",
            'cliente_pasajerodirecto' => "$opt|boolean",
            'cliente_promo' => "$opt|integer|in:0,1",
            'cliente_web' => "$opt|integer|in:0,1",
            'nombre_representante' => "$opt|string|max:250",
            'cliente_logo' => "$opt|string|max:250",
            'comentarios' => "$opt|string",
            'facturacion_periodo' => "$opt|integer",
            'tipofacturacion' => "$opt|integer",
            'factura_automatica' => "$opt|integer|in:0,1",
            'tipo_fce' => "$opt|string|max:10",
            'licencia_id' => "$opt|integer|min:0",

            // Integración
            'idnemo' => "$opt|integer|min:0",
            'idtravelc' => "$opt|integer|min:0",

            // Contactos (JSON en cliente_extra)
            'contactos' => 'sometimes|nullable|array',
            'contactos.*.cliente_cont_nombre' => 'required_with:contactos|string|max:250',
            'contactos.*.cliente_cont_cargo' => 'nullable|string|max:150',
            'contactos.*.cliente_email' => 'nullable|email|max:250',
            'contactos.*.cliente_tel_tipo' => 'nullable|string|max:30',
            'contactos.*.cliente_tel_codpais' => 'nullable|string|max:10',
            'contactos.*.cliente_tel_codarea' => 'nullable|string|max:10',
            'contactos.*.cliente_telefono' => 'nullable|string|max:50',
            'contactos.*.cliente_cont_emailsend' => 'nullable|integer|in:0,1',

            // Tarjetas (JSON en cliente_extra)
            'tarjetas' => 'sometimes|nullable|array',
            'tarjetas.*.cliente_tarjeta_num' => 'required_with:tarjetas|string|max:50',
            'tarjetas.*.cliente_tarjeta_banco' => 'nullable|string|max:150',
            'tarjetas.*.cliente_tarjeta_venc' => 'nullable|string|max:10',
            'tarjetas.*.cliente_tarjeta_cs' => 'nullable|string|max:10',
            'tarjetas.*.cliente_tarjeta_empresa' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_nombre.required' => 'El nombre del cliente es obligatorio.',
            'cliente_razonsocial.required' => 'La razón social es obligatoria.',
            'cuit.required' => 'El CUIT es obligatorio.',
            'cuit.unique' => 'Ya existe un cliente con este CUIT.',
            'fk_tipofactura_id.required' => 'El tipo de factura es obligatorio.',
            'fk_tipofactura_id.exists' => 'El tipo de factura seleccionado no es válido.',
            'fk_condicioniva_id.required' => 'La condición de IVA es obligatoria.',
            'fk_condicioniva_id.exists' => 'La condición de IVA seleccionada no es válida.',
            'fk_pais_id.required' => 'El país es obligatorio.',
            'fk_pais_id.exists' => 'El país seleccionado no es válido.',
            'fk_ciudad_id.required' => 'La ciudad es obligatoria.',
            'fk_ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'fk_tipoclavefiscal_id.required' => 'El tipo de clave fiscal es obligatorio.',
            'fk_tipoclavefiscal_id.exists' => 'El tipo de clave fiscal seleccionado no es válido.',
            'cliente_direccionfiscal.required' => 'La dirección fiscal es obligatoria.',
            'cliente_email.required' => 'El email es obligatorio.',
            'cliente_email.email' => 'El email debe ser una dirección de correo válida.',
            'contactos.*.cliente_cont_nombre.required_with' => 'El nombre del contacto es obligatorio.',
            'tarjetas.*.cliente_tarjeta_num.required_with' => 'El número de tarjeta es obligatorio.',
        ];
    }
}
