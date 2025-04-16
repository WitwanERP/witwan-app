<?php

namespace App\Http\Controllers\Configuracion\Admin;

use App\Models\Configuracion\Admin\TipoFactura;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TipoFacturaController extends Controller
{
    /**
     * Obtener todos los tipos de factura
     */
    public function index()
    {
        $tiposFactura = TipoFactura::all();
        return response()->json($tiposFactura);
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
     * Mostrar un tipo de factura específico
     */
    public function show($id)
    {
        try {
            $tipoFactura = TipoFactura::findOrFail($id);
            return response()->json($tipoFactura);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tipo de factura no encontrado'], 404);
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
     * Buscar tipos de factura por nombre
     */
    public function search(Request $request)
    {
        $query = TipoFactura::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where('tipofactura_nombre', 'like', '%' . $searchTerm . '%');
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Obtener un tipo de factura por ID
     */
    public function getById($id)
    {
        try {
            $tipoFactura = TipoFactura::findOrFail($id);
            return response()->json($tipoFactura);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tipo de factura no encontrado'], 404);
        }
    }

    /**
     * Obtener un tipo de factura por nombre
     */
    public function getByName($name)
    {
        try {
            $tipoFactura = TipoFactura::where('tipofactura_nombre', $name)->firstOrFail();
            return response()->json($tipoFactura);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tipo de factura no encontrado'], 404);
        }
    }

    /**
     * Obtener tipos de factura por múltiples IDs
     */
    public function getByIds(Request $request)
    {
        $ids = $request->input('ids');
        $tiposFactura = TipoFactura::whereIn('tipofactura_id', $ids)->get();
        return response()->json($tiposFactura);
    }

    /**
     * Obtener tipos de factura por múltiples nombres
     */
    public function getByNames(Request $request)
    {
        $names = $request->input('names');
        $tiposFactura = TipoFactura::whereIn('tipofactura_nombre', $names)->get();
        return response()->json($tiposFactura);
    }

    /**
     * Obtener un tipo de factura por nombre o ID
     */
    public function getByNameOrId($nameOrId)
    {
        $tipoFactura = TipoFactura::where('tipofactura_nombre', $nameOrId)
            ->orWhere('tipofactura_id', $nameOrId)
            ->first();

        if ($tipoFactura) {
            return response()->json($tipoFactura);
        } else {
            return response()->json(['error' => 'Tipo de factura no encontrado'], 404);
        }
    }
}
