<?php

namespace App\Http\Controllers\Ciudades;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $regiones = Region::with(['paises'])
            ->paginate($perPage);

        return response()->json($regiones);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'region_nombre' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pais = Region::create($request->all());
        return response()->json($pais, 201);
    }

    public function show($id)
    {
        try {
            $pais = Region::with(['paises'])
                ->findOrFail($id);
            return response()->json($pais);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Region no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $pais = Region::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'region_nombre' => 'string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $pais->update($request->all());
            return response()->json($pais);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Region no encontrado'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $pais = Region::findOrFail($id);
            $pais->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Region no encontrado'], 404);
        }
    }

    public function search(Request $request)
    {
        $query = Region::query();

        if ($request->has('nombre')) {
            $query->where('region_nombre', 'like', '%' . $request->nombre . '%');
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }
}
