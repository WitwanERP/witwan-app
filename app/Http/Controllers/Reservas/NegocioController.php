<?php

namespace App\Http\Controllers\Reservas;

use App\Models\Negocio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NegocioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $query = Negocio::query();

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
        $validator = Validator::make($request->all(), [
            // Agregar reglas de validación aquí
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $model = new Negocio();
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

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
                "negocio_nombre" => "required|string|max:100",
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

        return response()->json($query->paginate($perPage));
    }
}
