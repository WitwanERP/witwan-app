<?php

namespace App\Http\Controllers\Reservas;

use App\Models\Reserva;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);
        $validatedParams = $this->validateParams($request);
        //DB::enableQueryLog();
        $perPage = $request->get('per_page', 20);

        $items = Reserva
            ::where('fk_filestatus_id', '!=', 'AG')
            ->where('reserva_id', '!=', 1);

        if (isset($validatedParams['fecha_alta_desde']) && $validatedParams['fecha_alta_desde']) {
            $items = $items->where('fecha_alta', '>=', $validatedParams['fecha_alta_desde']);
        }
        if (isset($validatedParams['fecha_alta_hasta']) && $validatedParams['fecha_alta_hasta']) {
            $items = $items->where('fecha_alta', '<=', $validatedParams['fecha_alta_hasta']);
        }
        if (isset($validatedParams['codigo']) && $validatedParams['codigo']) {
            $items = $items->where('codigo', 'IN', $validatedParams['fecha_alta_hasta']);
        }
        if (isset($validatedParams['fk_cliente_id']) && $validatedParams['fk_cliente_id']) {
            $items = $items->where('fk_cliente_id', '=', $validatedParams['fk_cliente_id']);
        }
        $withRelations = [];
        if ($request->get('withRelations') && $request->get('withRelations') == 'true') {
            $withRelations = [
                'cliente' => function ($query) {
                    $query->select('cliente_id', 'cliente_nombre', 'cuit'); // Selecciona solo los campos necesarios
                },
                'facturara' => function ($query) {
                    $query->select('cliente_id', 'cliente_nombre', 'cuit'); // Selecciona solo los campos necesarios
                },
                'vendedor' => function ($query) {
                    $query->select('usuario_id', 'usuario_nombre', 'usuario_apellido'); // Selecciona solo los campos necesarios
                },
                'servicios'
            ];
            $items = $items->with($withRelations);
        }
        $items = $items
            ->orderByDesc('fecha_alta')
            ->paginate($perPage);

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        return response()->json([
            'data' => $items,
            'processing_time' => $processingTime
        ]);
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
        $model = new Reserva();
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

        $item = Reserva::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Reserva::findOrFail($id);
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
            $item = Reserva::findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Agregar reglas de validación aquí
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
            $item = Reserva::findOrFail($id);
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
        $query = Reserva::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            // Implementar búsqueda en campos principales
        }

        return response()->json($query->paginate($perPage));
    }
        private function validateParams(Request $request)
    {
        return $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'fecha_alta_desde' => 'nullable|date',
            'fecha_alta_hasta' => 'nullable|date|after_or_equal:fecha_alta_desde',
            'codigo' => 'nullable|string',
            'fk_cliente_id' => 'nullable|integer|exists:cliente,cliente_id',
        ]);
    }
}
