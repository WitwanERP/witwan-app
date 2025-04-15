<?php

namespace App\Http\Controllers\Empresas;

use App\Models\Empresas\Cliente;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);

        $query = Cliente::with(['pais']);

        // Filtrar por nombre
        if ($request->has('nombre') && !empty($request->nombre)) {
            $query->where('cliente_nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtrar por razón social
        if ($request->has('razon_social') && !empty($request->razon_social)) {
            $query->where('cliente_razonsocial', 'like', '%' . $request->razon_social . '%');
        }

        // Filtrar por CUIT
        if ($request->has('cuit') && !empty($request->cuit)) {
            $cuit = str_replace(['-', ' '], '', $request->cuit);
            $query->where('cuit', 'like', '%' . $cuit . '%');
        }

        // Filtrar por ciudad
        if ($request->has('ciudad') && !empty($request->ciudad)) {
            $query->where('cliente_ciudad', 'like', '%' . $request->ciudad . '%');
        }

        // Filtrar por país
        if ($request->has('pais_id') && !empty($request->pais_id)) {
            $query->where('fk_pais_id', $request->pais_id);
        }

        $clientes = $query->paginate($perPage);

        return response()->json($clientes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_nombre' => 'required|string|max:250',
            'cliente_razonsocial' => 'required|string|max:250',
            'limite_credito' => 'numeric',
            'credito_habilitado' => 'boolean',
            'credito_utilizado' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if (!empty($data['cuit'])) {
            $cuit = $data['cuit'];
            $cuit = str_replace(['-', ' '], '', $cuit); // Eliminar guiones y espacios
            $cuit = preg_replace('/[^0-9]/', '', $cuit); // Eliminar caracteres no numéricos
            $esCompartido = (intval($cuit) > 5000000000 || $cuit === '555555555');

            if (!$esCompartido) {
                $clienteExistente = Cliente::where('cuit', $cuit)->first();
                if ($clienteExistente) {
                    return response()->json([
                        'errors' => [
                            'cuit' => ['Ya existe un cliente con este CUIT']
                        ]
                    ], 422);
                }
            }
        }

        $cliente = Cliente::create($request->all());
        return response()->json($cliente, 201);
    }

    public function show($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            return response()->json($cliente);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'cliente_nombre' => 'string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $cliente->update($request->all());
            return response()->json($cliente);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente no encontrada'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $cliente->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente no encontrada'], 404);
        }
    }

    public function search(Request $request)
    {
        $query = Cliente::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('cliente_nombre', 'like', '%' . $searchTerm . '%')
                  ->orWhere('cliente_razonsocial', 'like', '%' . $searchTerm . '%')
                  ->orWhere('cuit', 'like', '%' . $searchTerm . '%');
            });
        }

        return response()->json($query->paginate($perPage));
    }
}
