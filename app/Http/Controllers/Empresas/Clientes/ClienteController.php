<?php

namespace App\Http\Controllers\Empresas\Clientes;

use App\Models\Cliente;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Helpers\PermisoHelper;

class ClienteController extends Controller
{
    private $sectionId = 112; // Asignar el ID de sección correspondiente

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 100);
        $query = Cliente::query();

        // Agregar filtros básicos aquí
        if ($request->has('search') && !empty($request->search)) {
            // Implementar búsqueda según los campos de la tabla
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!PermisoHelper::tienePermiso($this->sectionId, 'alta')) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'cliente_nombre' => 'required|string|max:250',
            'cliente_razonsocial' => 'required|string|max:250',
            'cuit' => 'required|string|max:20',
            'fk_tipoclavefiscal_id' => 'required|integer|exists:tipoclavefiscal,tipoclavefiscal_id',
            'fk_tipofactura_id' => 'required|integer|exists:tipofactura,tipofactura_id',
            'fk_condicioniva_id' => 'required|integer|exists:condicioniva,condicioniva_id',
            'fk_pais_id' => 'required|integer|exists:pais,pais_id',
            'fk_ciudad_id' => 'required|integer|exists:ciudad,ciudad_id',
            'cliente_direccionfiscal' => 'required|string|max:250',
            'cliente_email' => 'required|string|email|max:250',
            // ...otras reglas si las necesitas
        ], [
            'cliente_nombre.required' => 'El nombre del cliente es obligatorio.',
            'cliente_razonsocial.required' => 'La razón social es obligatoria.',
            'cuit.required' => 'El CUIT es obligatorio.',
            'fk_tipofactura_id.required' => 'El tipo de factura es obligatorio.',
            'fk_tipofactura_id.integer' => 'El tipo de factura debe ser un número entero.',
            'fk_tipofactura_id.exists' => 'El tipo de factura seleccionado no es válido.',
            'fk_condicioniva_id.required' => 'La condición de IVA es obligatoria.',
            'fk_condicioniva_id.integer' => 'La condición de IVA debe ser un número entero.',
            'fk_condicioniva_id.exists' => 'La condición de IVA seleccionada no es válida.',
            'fk_pais_id.required' => 'El país es obligatorio.',
            'fk_pais_id.integer' => 'El país debe ser un número entero.',
            'fk_pais_id.exists' => 'El país seleccionado no es válido.',
            'fk_ciudad_id.required' => 'La ciudad es obligatoria.',
            'fk_ciudad_id.integer' => 'La ciudad debe ser un número entero.',
            'fk_ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'fk_tipoclavefiscal_id.required' => 'El tipo de clave fiscal es obligatorio.',
            'fk_tipoclavefiscal_id.integer' => 'El tipo de clave fiscal debe ser un número entero.',
            'fk_tipoclavefiscal_id.exists' => 'El tipo de clave fiscal seleccionado no es válido.',
            'cliente_direccionfiscal.required' => 'La dirección fiscal es obligatoria.',
            'cliente_email.required' => 'El email es obligatorio.',
            'cliente_email.email' => 'El email debe ser una dirección de correo válida.',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $model = new Cliente();
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

        if (isset($data['cuit'])) {
            $data['cuit'] = str_replace(['-', '.'], '', $data['cuit']);
            // Controlar que el CUIT no exista en la base
            if (Cliente::where('cuit', $data['cuit'])->exists()) {
                return response()->json(['errors' => ['cuit' => ['Ya existe un cliente con este CUIT.']]], 422);
            }
        }
        if (!isset($data['fk_idioma_id'])) {
            $data['fk_idioma_id'] = 'es';
        }

        $defaultVacio = [
            'cliente_telefono',
            'cliente_legajo',
            'cliente_fax',
            'cliente_email2',
            'cliente_emailadmin',
            'cliente_ciudad',
            'cliente_provincia',
            'cliente_codigopostal',
            'iata',
            'cliente_logo',
            'gastos_fijo_moneda',
            'fk_moneda_id',
            'comentarios',
            'nombre_representante',
            'cuit_internacional'
        ];
        foreach ($defaultVacio as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
        }

        if (!isset($data['credito_habilitado'])) {
            $data['credito_habilitado'] = 0;
        }
        if (!isset($data['nro_clavefiscal'])) {
            $data['nro_clavefiscal'] = $data['cuit'];
        }

        $defaultEnCero = [
            'fk_tarifario1_id',
            'fk_tarifario2_id',
            'fk_tarifario3_id',
            'limite_credito',
            'credito_utilizado',
            'gastos_porcentaje_1',
            'gastos_porcentaje_2',
            'gastos_porcentaje_3',
            'gastos_fijo_1',
            'gastos_fijo_2',
            'gastos_fijo_3',
            'gastos_iva',
            'plazo_pago',
            'idnemo',
            'idtravelc',
            'licencia_id',
            'facturacion_periodo',
            'fk_usuario_promotor1',
            'fk_usuario_promotor2',
            'fk_usuario_promotor3',
            'fk_usuario_promotor4',
            'fk_usuario_vendedor',
            'cliente_promo',
            'cliente_web',
            'cliente_pasajerodirecto',
            'fk_cadenacliente_id',
            'plazo_pago',
            'idnemo',
            'idtravelc',
            'factura_automatica',
            'tipofacturacion'

        ];
        foreach ($defaultEnCero as $field) {
            if (!isset($data[$field]) && $tableColumns->contains($field)) {
                $data[$field] = 0;
            }
        }

        // Agregar campos automáticos si existen
        if ($tableColumns->contains('fechacarga')) {
            $data['fechacarga'] = now();
        }
        if ($tableColumns->contains('um')) {
            $data['um'] = now();
        }
        if ($tableColumns->contains('fk_usuario_id')) {
            $data['fk_usuario_id'] = auth()->id();
        }

        $item = Cliente::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Cliente::findOrFail($id);
            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $item = Cliente::findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Agregar reglas de validación aquí
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $request->all();
            $tableColumns = collect(Schema::getColumnListing($item->getTable()));

            // Actualizar campos automáticos
            if ($tableColumns->contains('um')) {
                $data['um'] = now();
            }
            if ($tableColumns->contains('fk_usuario_id')) {
                $data['fk_usuario_id'] = auth()->id();
            }

            $item->update($data);
            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $item = Cliente::findOrFail($id);
            $item->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Search resources.
     */
    public function search(Request $request)
    {
        $query = Cliente::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            // Implementar búsqueda en campos principales
        }

        return response()->json($query->paginate($perPage));
    }
}
