<?php

namespace App\Http\Controllers\Empresas\Pasajeros;

use App\Models\Pasajero;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Helpers\PermisoHelper;

class PasajeroController extends Controller
{
    private $sectionId = 113; // Asignar el ID de sección correspondiente

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 100);
        $query = Pasajero::query();

        // Agregar filtros básicos aquí
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('pasajero_nombre', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('pasajero_apellido', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('pasajero_email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('nrodoc', 'LIKE', "%{$searchTerm}%");
            });
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
            'pasajero_nombre' => 'required|string|max:250',
            'pasajero_apellido' => 'required|string|max:250',
            'pasajero_email' => 'required|string|email|max:250',
            'fk_cliente_id' => 'required|integer|exists:cliente,cliente_id',
            'pasajero_nacionalidad' => 'nullable|string|max:100',
            'pasajero_nacimiento' => 'nullable|date',
            'pasajero_sexo' => 'nullable|string|in:M,F',
            'tipodoc' => 'nullable|string|max:50',
            'nrodoc' => 'nullable|string|max:50',
            'fk_pais_id' => 'nullable|integer|exists:pais,pais_id',
            'fk_ciudad_id' => 'nullable|integer|exists:ciudad,ciudad_id',
        ], [
            'pasajero_nombre.required' => 'El nombre del pasajero es obligatorio.',
            'pasajero_apellido.required' => 'El apellido del pasajero es obligatorio.',
            'pasajero_email.required' => 'El email es obligatorio.',
            'pasajero_email.email' => 'El email debe ser una dirección de correo válida.',
            'fk_cliente_id.required' => 'El cliente es obligatorio.',
            'fk_cliente_id.exists' => 'El cliente seleccionado no es válido.',
            'pasajero_sexo.in' => 'El sexo debe ser M o F.',
            'fk_pais_id.exists' => 'El país seleccionado no es válido.',
            'fk_ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $model = new Pasajero();
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

        // Campos por defecto vacíos
        $defaultVacio = [
            'pasajero_apodo',
            'pasajero_clave',
            'cargo',
            'cliente_asociado',
            'fk_usuario_promotor1',
            'freelance',
            'habilita',
            'pasajero_foto',
            'emisorfecha',
            'vencimientodoc',
            'pasajero_direccionfiscal',
            'pasajero_codigopostal',
            'pasajero_ciudad',
            'nro_clavefiscal',
            'fk_moneda_id',
            'fotodoc',
            'observaciones'
        ];
        foreach ($defaultVacio as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
        }

        // Campos por defecto en cero
        $defaultEnCero = [
            'mostrar_ficha',
            'fk_usuario_vendedor',
            'emisordoc',
            'fk_pais_id',
            'fk_ciudad_id',
            'fk_tipoclavefiscal_id',
            'fk_condicioniva_id',
            'fk_tarifario1_id',
            'fk_tarifario2_id',
            'gastos_iva',
            'gastos_fijo_1',
            'gastos_porcentaje_1'
        ];
        foreach ($defaultEnCero as $field) {
            if (!isset($data[$field]) && $tableColumns->contains($field)) {
                $data[$field] = 0;
            }
        }

        // Campos automáticos si existen
        if ($tableColumns->contains('ultimo_mail')) {
            $data['ultimo_mail'] = now();
        }

        $item = Pasajero::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Pasajero::findOrFail($id);
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
            $item = Pasajero::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'pasajero_nombre' => 'sometimes|required|string|max:250',
                'pasajero_apellido' => 'sometimes|required|string|max:250',
                'pasajero_email' => 'sometimes|required|string|email|max:250',
                'fk_cliente_id' => 'sometimes|required|integer|exists:cliente,cliente_id',
                'pasajero_nacionalidad' => 'nullable|string|max:100',
                'pasajero_nacimiento' => 'nullable|date',
                'pasajero_sexo' => 'nullable|string|in:M,F',
                'tipodoc' => 'nullable|string|max:50',
                'nrodoc' => 'nullable|string|max:50',
                'fk_pais_id' => 'nullable|integer|exists:pais,pais_id',
                'fk_ciudad_id' => 'nullable|integer|exists:ciudad,ciudad_id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $request->all();
            $tableColumns = collect(Schema::getColumnListing($item->getTable()));

            // Actualizar campos automáticos
            if ($tableColumns->contains('ultimo_mail')) {
                $data['ultimo_mail'] = now();
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
            $item = Pasajero::findOrFail($id);
            $item->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }
}