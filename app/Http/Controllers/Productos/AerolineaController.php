<?php

namespace App\Http\Controllers\Productos;

use App\Http\Controllers\Controller;
use App\Models\Productos\Aerolinea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AerolineaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $aerolineas = Aerolinea::with(['cliente'])
            ->paginate($perPage);

        return response()->json($aerolineas);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'aerolinea_nombre' => 'required|string|max:100',
            'aerolinea_codigo' => 'required|string|max:2',
            'aerolinea_bspcode' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $aerolinea = Aerolinea::create($request->all());
        return response()->json($aerolinea, 201);
    }

    public function show($id)
    {
        try {
            $aerolinea = Aerolinea::with(['cliente'])
                ->findOrFail($id);
            return response()->json($aerolinea);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Aerolinea no encontrada'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $aerolinea = Aerolinea::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'region_nombre' => 'string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $aerolinea->update($request->all());
            return response()->json($aerolinea);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Aerolinea no encontrada'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $aerolinea = Aerolinea::findOrFail($id);
            $aerolinea->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Aerolinea no encontrada'], 404);
        }
    }

    public function search(Request $request)
    {
        $query = Aerolinea::query();

        if ($request->has('nombre')) {
            $query->where('region_nombre', 'like', '%' . $request->nombre . '%');
        }

        return response()->json($query->paginate($request->get('per_page', 100)));
    }
}
