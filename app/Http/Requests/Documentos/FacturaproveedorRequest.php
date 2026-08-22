<?php

namespace App\Http\Requests\Documentos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del formulario de facturas de tercero.
 *
 * El legacy validaba casi todo sólo en el navegador (scriptfactura3ro.js:231-404)
 * y armaba el INSERT concatenando strings sin escapar (factura3ero.php:904-951),
 * así que varios campos eran inyectables. Acá las reglas son de servidor.
 *
 * El mismo Request sirve para alta y edición: en edición sólo llegan los pocos
 * campos que `save_after_edit` permite tocar, por eso las reglas pasan a
 * `sometimes`.
 */
class FacturaproveedorRequest extends FormRequest
{
    /** La autorización se resuelve en el controller con App\Support\Permisos. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $alta = $this->isMethod('post');
        $req = $alta ? 'required' : 'sometimes|required';
        $opt = 'sometimes|nullable';

        $importe = $opt.'|numeric';

        return [
            'facturaproveedor_nro' => $req.'|string|max:100',
            'facturaproveedor_tipodocumento' => $req.'|string|max:50|in:'.implode(',', array_keys((array) config('facturaproveedor.tipos_documento'))),
            'facturaproveedor_tipofactura' => $opt.'|string|max:2',
            'fk_proveedor_id' => $req.'|integer|min:1',
            'fk_plancuenta_id' => ($alta ? 'required' : $opt).'|integer',
            'fk_proyecto_id' => $opt.'|integer',
            'fk_itemgasto_id' => $opt.'|integer',
            'fk_moneda_id' => $req.'|string|max:3',
            'cotizacion' => $opt.'|numeric',
            'tipomovimiento' => $req.'|string|max:50',
            'electronica' => $opt.'|string|max:1',
            // Adjunto del comprobante. 8 MB alcanza para un PDF escaneado.
            'archivo' => $opt.'|file|max:8192',

            // Fechas: el front nuevo manda ISO; se acepta dd/mm/YYYY por la API.
            'fecha' => $req.'|date_format:Y-m-d,d/m/Y',
            'fechacontable' => $opt.'|date_format:Y-m-d,d/m/Y',
            'vencimiento' => $opt.'|date_format:Y-m-d,d/m/Y',

            // Bases imponibles y demás importes.
            'exento' => $importe,
            'nocomputable' => $importe,
            'especial' => $importe,
            'general' => $importe,
            'monto27' => $importe,
            'monto25' => $importe,
            'ivatotal' => $importe,
            'ivatur' => $importe,
            'retencioniva' => $importe,
            'retencioniibb' => $importe,
            'percepcioniva' => $importe,
            'percepcioniibb' => $importe,
            'retencionganancias' => $importe,
            'percepcionganancias' => $importe,
            'otrosimpuestos' => $importe,

            'descripcion' => $opt.'|string',
            'observaciones' => $opt.'|string',

            // Conceptos configurables de la licencia; su suma reemplaza a exento.
            'adicionales' => $opt.'|array',
            'adicionales.*' => 'nullable|numeric',

            // Imputación porcentual por área. La suma la valida el service, que
            // conoce la regla por país (100, o 100/200 en Chile).
            'areaimputacion' => $opt.'|array',
            'areaimputacion.*' => 'nullable|numeric|min:0|max:200',
            'subareaimputacion' => $opt.'|array',
            'subareaimputacion.*' => 'nullable|numeric|min:0|max:200',

            // Servicios de reserva a imputar.
            'ocupacion' => $opt.'|array',
            'ocupacion.*.id' => 'required_with:ocupacion|integer|min:1',
            'ocupacion.*.monto' => 'nullable|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'date_format' => 'El campo :attribute debe tener formato dd/mm/aaaa.',
            'facturaproveedor_tipodocumento.in' => 'El tipo de documento no es válido.',
            'fk_proveedor_id.min' => 'Debe seleccionar un proveedor.',
            'ocupacion.*.id.required_with' => 'Cada servicio imputado debe indicar su id.',
        ];
    }

    public function attributes(): array
    {
        return [
            'facturaproveedor_nro' => 'número de factura',
            'facturaproveedor_tipodocumento' => 'tipo de documento',
            'facturaproveedor_tipofactura' => 'tipo de factura',
            'fk_proveedor_id' => 'proveedor',
            'fk_plancuenta_id' => 'cuenta contable',
            'fk_proyecto_id' => 'proyecto',
            'fk_itemgasto_id' => 'item de gasto',
            'fk_moneda_id' => 'moneda',
            'tipomovimiento' => 'tipo de gasto',
            'fecha' => 'fecha de factura',
            'fechacontable' => 'fecha contable',
            'vencimiento' => 'vencimiento',
            'nocomputable' => 'monto no computable',
            'especial' => 'monto 10,5%',
            'general' => 'monto gravado',
        ];
    }
}
