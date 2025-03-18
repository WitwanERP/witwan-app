<?php

namespace App\Http\Controllers\Ciudades;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Ciudad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CiudadController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $includeAll = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $ciudades = Ciudad::filterActivo($includeAll)
        ->with(['pais', 'puntosInternos'])
        ->paginate($perPage);

        return response()->json($ciudades);

    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ciudad_nombre' => 'required|string|max:100',
            'fk_pais_id' => 'required|exists:pais,pais_id',
            'ciudad_codigo' => 'required|string|max:20',
            'nombre_en' => 'nullable|string|max:100',
            'nombre_pg' => 'nullable|string|max:100',
            'ap' => 'boolean',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'codigo_tourico' => 'nullable|string|max:3',
            'codigo_amex' => 'nullable|integer',
            'codigo_hb' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ciudad = Ciudad::create($request->all());
        return response()->json($ciudad, 201);
    }

    public function show($id)
    {
        try {
            $ciudad = Ciudad::with(['pais', 'puntosInternos'])
                ->findOrFail($id);
            return response()->json($ciudad);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ciudad = Ciudad::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'ciudad_nombre' => 'string|max:100',
                'fk_pais_id' => 'exists:pais,pais_id',
                'ciudad_codigo' => 'string|max:20',
                'nombre_en' => 'nullable|string|max:100',
                'nombre_pg' => 'nullable|string|max:100',
                'ap' => 'boolean',
                'latitud' => 'nullable|numeric',
                'longitud' => 'nullable|numeric',
                'codigo_tourico' => 'nullable|string|max:3',
                'codigo_amex' => 'nullable|integer',
                'codigo_hb' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $ciudad->update($request->all());
            return response()->json($ciudad);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $ciudad = Ciudad::findOrFail($id);
            $ciudad->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }
    }

    public function search(Request $request)
    {
        $query = Ciudad::query();

        if ($request->has('nombre')) {
            $query->where('ciudad_nombre', 'like', '%' . $request->nombre . '%');
        }

        if ($request->has('pais_id')) {
            $query->where('fk_pais_id', $request->pais_id);
        }

        if ($request->has('activo')) {
            $query->where('ciudad_activo', $request->activo);
        }

        return response()->json($query->with(['pais'])->paginate($request->get('per_page', 100)));
    }
}
