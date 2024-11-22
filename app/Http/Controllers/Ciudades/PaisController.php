<?php

namespace App\Http\Controllers\Ciudades;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Pais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PaisController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $paises = Pais::with(['ciudades'])
            ->paginate($perPage);

        return response()->json($paises);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pais_nombre' => 'required|string|max:100',
            'pais_codigo' => 'required|string|max:3',
            'pais_activo' => 'boolean',
            'nombre_en' => 'nullable|string|max:100',
            'nombre_pg' => 'nullable|string|max:100',
            'continente' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pais = Pais::create($request->all());
        return response()->json($pais, 201);
    }

    public function show($id)
    {
        try {
            $pais = Pais::with(['ciudades'])
                ->findOrFail($id);
            return response()->json($pais);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'País no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $pais = Pais::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'pais_nombre' => 'string|max:100',
                'pais_codigo' => 'string|max:3',
                'pais_activo' => 'boolean',
                'nombre_en' => 'nullable|string|max:100',
                'nombre_pg' => 'nullable|string|max:100',
                'continente' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $pais->update($request->all());
            return response()->json($pais);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'País no encontrado'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $pais = Pais::findOrFail($id);
            $pais->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'País no encontrado'], 404);
        }
    }

    public function search(Request $request)
    {
        $query = Pais::query();

        if ($request->has('nombre')) {
            $query->where('pais_nombre', 'like', '%' . $request->nombre . '%');
        }

        if ($request->has('codigo')) {
            $query->where('pais_codigo', $request->codigo);
        }

        if ($request->has('activo')) {
            $query->where('pais_activo', $request->activo);
        }

        if ($request->has('continente')) {
            $query->where('continente', $request->continente);
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }
}
