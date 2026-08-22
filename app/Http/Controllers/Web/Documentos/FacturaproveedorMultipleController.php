<?php

namespace App\Http\Controllers\Web\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Exceptions\Documentos\FacturaproveedorExceptionEnFila;
use App\Http\Controllers\Controller;
use App\Services\Documentos\FacturaproveedorMultipleService;
use App\Services\Documentos\FacturaproveedorService;
use App\Support\Permisos;
use App\Support\Secciones;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Carga múltiple de facturas de tercero.
 *
 * Reemplaza al controller `Factura3erom` del CI. Comparte el núcleo de alta con
 * la carga individual: acá sólo se arma la cabecera común y se recorren las
 * filas.
 */
class FacturaproveedorMultipleController extends Controller
{
    private const RUTA = '/app/facturas-proveedor';

    public function create(FacturaproveedorService $facturas)
    {
        Permisos::exigir(Secciones::FACTURA_TERCERO, 'alta', 'Facturas de Terceros');

        return Inertia::render('Documentos/FacturasProveedor/CargaMultiple', [
            'opciones' => $facturas->opcionesFormulario(),
            'monedas' => $this->monedas(),
            'baseUrl' => self::RUTA,
        ]);
    }

    public function store(Request $request, FacturaproveedorMultipleService $multiple)
    {
        Permisos::exigir(Secciones::FACTURA_TERCERO, 'alta', 'Facturas de Terceros');

        $datos = $request->validate([
            'cabecera' => 'required|array',
            'cabecera.fk_proveedor_id' => 'required|integer|min:1',
            'cabecera.tipomovimiento' => 'required|string',
            'cabecera.fk_plancuenta_id' => 'required|integer',
            'cabecera.fk_moneda_id' => 'required|string|max:3',
            'cabecera.cotizacion' => 'nullable|numeric',
            'cabecera.fk_proyecto_id' => 'nullable|integer',
            'cabecera.areaimputacion' => 'nullable|array',
            'cabecera.descripcion' => 'nullable|string',
            'documentos' => 'required|array|min:1',
            'documentos.*.facturaproveedor_nro' => 'required|string|max:100',
            'documentos.*.facturaproveedor_tipodocumento' => 'required|string|max:50',
            'documentos.*.facturaproveedor_tipofactura' => 'nullable|string|max:2',
            'documentos.*.fecha' => 'required|date_format:Y-m-d,d/m/Y',
            'documentos.*.fechacontable' => 'nullable|date_format:Y-m-d,d/m/Y',
            'documentos.*.vencimiento' => 'nullable|date_format:Y-m-d,d/m/Y',
            'documentos.*.exento' => 'nullable|numeric',
            'documentos.*.nocomputable' => 'nullable|numeric',
            'documentos.*.especial' => 'nullable|numeric',
            'documentos.*.general' => 'nullable|numeric',
            'documentos.*.monto27' => 'nullable|numeric',
            'documentos.*.monto25' => 'nullable|numeric',
            'documentos.*.ivatotal' => 'nullable|numeric',
            'documentos.*.ivatur' => 'nullable|numeric',
            'documentos.*.retencioniva' => 'nullable|numeric',
            'documentos.*.retencioniibb' => 'nullable|numeric',
            'documentos.*.percepcioniva' => 'nullable|numeric',
            'documentos.*.percepcioniibb' => 'nullable|numeric',
            'documentos.*.retencionganancias' => 'nullable|numeric',
            'documentos.*.percepcionganancias' => 'nullable|numeric',
            'documentos.*.otrosimpuestos' => 'nullable|numeric',
        ]);

        try {
            $ids = $multiple->crear($datos['cabecera'], $datos['documentos'], (int) auth()->id());
        } catch (FacturaproveedorExceptionEnFila|FacturaproveedorException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect(self::RUTA)
            ->with('success', count($ids).' factura(s) de tercero creada(s).');
    }

    private function monedas(): array
    {
        return \App\Models\Moneda::query()
            ->orderBy('moneda_id')
            ->get(['moneda_id', 'moneda_nombre', 'moneda_basica'])
            ->map(fn ($m) => [
                'id' => $m->moneda_id,
                'label' => $m->moneda_nombre ?: $m->moneda_id,
                'basica' => $m->moneda_basica === 'Y',
            ])
            ->all();
    }
}
