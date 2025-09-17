<?php

namespace App\Http\Controllers\Reservas;

use App\Models\Negocio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Helpers\PermisoHelper;

class NegocioController extends Controller
{
    private $sectionId = 114; // Asignar el ID de sección correspondiente

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $query = Negocio::query();

        // Agregar filtros básicos aquí
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('negocio_nombre', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('negocio_descripcion', 'LIKE', "%{$searchTerm}%");
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
            'negocio_nombre' => 'required|string|max:250',
            'negocio_vencimiento' => 'nullable|date',
            'fk_ciudad_id' => 'nullable|integer|exists:ciudad,ciudad_id',
            'fk_sistema_id' => 'nullable|integer|exists:sistema,sistema_id',
            'negocio_descripcion' => 'nullable|string|max:500',
        ], [
            'negocio_nombre.required' => 'El nombre del negocio es obligatorio.',
            'negocio_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'fk_ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'fk_sistema_id.exists' => 'El sistema seleccionado no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $data = $request->all();
        $model = new Negocio();
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

        // Campo descripción por defecto vacío si no se proporciona
        if (!isset($data['negocio_descripcion'])) {
            $data['negocio_descripcion'] = '';
        }

        // Campos por defecto en cero
        $defaultEnCero = [
            'fk_ciudad_id',
            'fk_sistema_id'
        ];
        foreach ($defaultEnCero as $field) {
            if (!isset($data[$field]) && $tableColumns->contains($field)) {
                $data[$field] = 0;
            }
        }

        $item = Negocio::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Negocio::findOrFail($id);
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
            $item = Negocio::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'negocio_nombre' => 'sometimes|required|string|max:250',
                'negocio_vencimiento' => 'nullable|date',
                'fk_ciudad_id' => 'nullable|integer|exists:ciudad,ciudad_id',
                'fk_sistema_id' => 'nullable|integer|exists:sistema,sistema_id',
                'negocio_descripcion' => 'nullable|string|max:500',
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
            $item = Negocio::findOrFail($id);
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
        $query = Negocio::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            // Implementar búsqueda en campos principales
        }
        if ($request->has('nombre') && !empty($request->nombre)) {
            $query->where("negocio_nombre", "like", "%" . $request->nombre . "%");
        }

        // Filtro de vencimiento desde (convertir dd/mm/yyyy a yyyy-mm-dd)
        if ($request->has('vencimiento') && !empty($request->vencimiento)) {
            try {
                $fechaDesde = \DateTime::createFromFormat('d/m/Y', $request->vencimiento);
                if ($fechaDesde) {
                    $query->whereDate("negocio_vencimiento", ">=", $fechaDesde->format('Y-m-d'));
                }
            } catch (\Exception $e) {
                // Si el formato no es válido, ignorar el filtro
            }
        }

        // Filtro de vencimiento hasta (convertir dd/mm/yyyy a yyyy-mm-dd)
        if ($request->has('vencimiento_to') && !empty($request->vencimiento_to)) {
            try {
                $fechaHasta = \DateTime::createFromFormat('d/m/Y', $request->vencimiento_to);
                if ($fechaHasta) {
                    $query->whereDate("negocio_vencimiento", "<=", $fechaHasta->format('Y-m-d'));
                }
            } catch (\Exception $e) {
                // Si el formato no es válido, ignorar el filtro
            }
        }

        return response()->json($query->paginate($perPage));
    }
}
