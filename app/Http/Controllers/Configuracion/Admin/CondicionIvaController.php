<?php

namespace App\Http\Controllers\Configuracion\Admin;

use App\Models\Configuracion\Admin\CondicionIva;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CondicionIvaController extends Controller
{
    /**
     * Obtener todas las condiciones de IVA
     */
    public function index()
    {
        $condicionesIva = CondicionIva::all();
        return response()->json($condicionesIva);
    }

    /**
     * Las operaciones de creación están prohibidas
     */
    public function store(Request $request)
    {
        return response()->json([
            'error' => 'Operación no permitida. Este es un recurso de solo lectura.'
        ], 403);
    }

    /**
     * Mostrar una condición de IVA específica
     */
    public function show($id)
    {
        try {
            $condicionIva = CondicionIva::findOrFail($id);
            return response()->json($condicionIva);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Condición de IVA no encontrada'], 404);
        }
    }

    /**
     * Las operaciones de actualización están prohibidas
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'error' => 'Operación no permitida. Este es un recurso de solo lectura.'
        ], 403);
    }

    /**
     * Las operaciones de eliminación están prohibidas
     */
    public function destroy($id)
    {
        return response()->json([
            'error' => 'Operación no permitida. Este es un recurso de solo lectura.'
        ], 403);
    }

    /**
     * Buscar condiciones de IVA por nombre
     */
    public function search(Request $request)
    {
        $query = CondicionIva::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where('condicioniva_nombre', 'like', '%' . $searchTerm . '%');
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Obtener una condición de IVA por ID
     */
    public function getById($id)
    {
        try {
            $condicionIva = CondicionIva::findOrFail($id);
            return response()->json($condicionIva);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Condición de IVA no encontrada'], 404);
        }
    }

    /**
     * Obtener una condición de IVA por nombre
     */
    public function getByName($name)
    {
        try {
            $condicionIva = CondicionIva::where('condicioniva_nombre', $name)->firstOrFail();
            return response()->json($condicionIva);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Condición de IVA no encontrada'], 404);
        }
    }

    /**
     * Obtener condiciones de IVA por múltiples IDs
     */
    public function getByIds(Request $request)
    {
        $ids = $request->input('ids');
        $condicionesIva = CondicionIva::whereIn('condicioniva_id', $ids)->get();
        return response()->json($condicionesIva);
    }

    /**
     * Obtener condiciones de IVA por múltiples nombres
     */
    public function getByNames(Request $request)
    {
        $names = $request->input('names');
        $condicionesIva = CondicionIva::whereIn('condicioniva_nombre', $names)->get();
        return response()->json($condicionesIva);
    }

    /**
     * Obtener una condición de IVA por nombre o ID
     */
    public function getByNameOrId($nameOrId)
    {
        $condicionIva = CondicionIva::where('condicioniva_nombre', $nameOrId)
            ->orWhere('condicioniva_id', $nameOrId)
            ->first();

        if ($condicionIva) {
            return response()->json($condicionIva);
        } else {
            return response()->json(['error' => 'Condición de IVA no encontrada'], 404);
        }
    }
}
