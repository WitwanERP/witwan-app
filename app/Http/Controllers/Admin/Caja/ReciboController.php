<?php

namespace App\Http\Controllers\Admin\Caja;

use App\Models\Recibo;
use App\Models\Reserva;
use App\Models\Factura;
use App\Models\Asientocontable;
use App\Models\Movimiento;
use App\Models\RelFacturarecibo;
use App\Models\RelFilerecibo;
use App\Models\Cotizacion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReciboController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $query = Recibo::query();

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
        $model = new Recibo();
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

        $item = Recibo::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Recibo::findOrFail($id);
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
            $item = Recibo::findOrFail($id);

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
            $item = Recibo::findOrFail($id);
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
        $query = Recibo::query();
        $perPage = $request->get('per_page', 100);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            // Implementar búsqueda en campos principales
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Process a receipt with reservations and invoices
     */
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'cliente_id' => 'required|integer|exists:cliente,cliente_id',
            'moneda_id' => 'required|string|exists:moneda,moneda_id',
            'monto_total' => 'required|numeric|min:0',
            'reservas' => 'required|array|min:1',
            'reservas.*.reserva_id' => 'required|integer|exists:reserva,reserva_id',
            'reservas.*.monto' => 'required|numeric|min:0',
            'facturas' => 'sometimes|array',
            'facturas.*.factura_id' => 'required_with:facturas|integer|exists:factura,factura_id',
            'facturas.*.monto_aplicado' => 'required_with:facturas|numeric|min:0',
            'movimientos' => 'required|array|min:2',
            'movimientos.*.cuenta_id' => 'required|integer|exists:plancuentum,plancuenta_id',
            'movimientos.*.tipo' => 'required|in:debe,haber',
            'movimientos.*.monto' => 'required|numeric|min:0',
            'movimientos.*.descripcion' => 'nullable|string',
            'movimientos.*.banco' => 'nullable|string',
            'movimientos.*.operacion' => 'nullable|string',
            'cotizacion_personalizada' => 'nullable|numeric|min:0',
            'tipo_cambio_general' => 'nullable|numeric|min:0',
            'recibo_tipo' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->all();

            // 1. Validar que las facturas pertenezcan al mismo cliente
            if (!empty($data['facturas'])) {
                $facturaClientIds = Factura::whereIn('factura_id', collect($data['facturas'])->pluck('factura_id'))
                    ->pluck('fk_cliente_id')
                    ->unique();

                if ($facturaClientIds->count() > 1 || !$facturaClientIds->contains($data['cliente_id'])) {
                    throw new \Exception('Todas las facturas deben pertenecer al mismo cliente del recibo.');
                }
            }

            // 2. Validar que las reservas existan y calcular el total
            $reservasData = collect($data['reservas']);
            $totalReservas = $reservasData->sum('monto');

            $reservasIds = $reservasData->pluck('reserva_id');
            $reservasExistentes = Reserva::whereIn('reserva_id', $reservasIds)->count();

            if ($reservasExistentes !== $reservasIds->count()) {
                throw new \Exception('Una o más reservas no existen.');
            }

            // 3. Validar que el balance contable esté cuadrado
            $movimientos = collect($data['movimientos']);
            $totalDebe = $movimientos->where('tipo', 'debe')->sum('monto');
            $totalHaber = $movimientos->where('tipo', 'haber')->sum('monto');

            if (abs($totalDebe - $totalHaber) > 0.01) {
                throw new \Exception('El asiento contable no está balanceado. Debe: ' . $totalDebe . ', Haber: ' . $totalHaber);
            }

            // 4. Obtener cotización
            $cotizacion = $data['cotizacion_personalizada'] ??
                         $data['tipo_cambio_general'] ??
                         $this->getCurrencyRate($data['moneda_id'], $data['fecha']);

            // 5. Crear el recibo
            $recibo = Recibo::create([
                'recibo_tipo' => $data['recibo_tipo'] ?? 'PAGO',
                'recibo_nro' => $this->generateReciboNumber(),
                'fecha' => $data['fecha'],
                'fk_cliente_id' => $data['cliente_id'],
                'fk_moneda_id' => $data['moneda_id'],
                'monto' => $data['monto_total'],
                'fk_usuario_id' => auth()->id() ?? 1,
                'statusrecibo' => 'OK',
                'observaciones' => $data['observaciones'] ?? '',
                'automatico' => 0,
                'actualiza' => 1
            ]);

            // 6. Crear asiento contable
            $asiento = Asientocontable::create([
                'asientocontable_fecha' => $data['fecha'],
                'debe' => $totalDebe,
                'haber' => $totalHaber
            ]);

            // 7. Crear movimientos contables
            foreach ($movimientos as $mov) {
                Movimiento::create([
                    'fk_asientocontable_id' => $asiento->asientocontable_id,
                    'fk_plancuenta_id' => $mov['cuenta_id'],
                    'fk_moneda_id' => $data['moneda_id'],
                    'cuenta_debito' => $mov['tipo'] === 'debe' ? 1 : 0,
                    'cuenta_credito' => $mov['tipo'] === 'haber' ? 1 : 0,
                    'cotizacion_moneda' => $cotizacion,
                    'monto' => $mov['monto'],
                    'montofinal' => $mov['monto'] * $cotizacion,
                    'fecha' => $data['fecha'],
                    'fecha_acreditacion' => $data['fecha'],
                    'regdate' => now(),
                    'fk_usuario_id' => auth()->id() ?? 1,
                    'fk_cliente_id' => $data['cliente_id'],
                    'fk_recibo_id' => $recibo->recibo_id,
                    'descripcion' => $mov['descripcion'] ?? '',
                    'banco' => $mov['banco'] ?? '',
                    'operacion' => $mov['operacion'] ?? '',
                    'statusmovimiento' => 'OK',
                    'tipo' => 'RECIBO',
                    'utilizado' => 0,
                    'afecta_cobranza' => 1
                ]);
            }

            // 8. Relacionar reservas con el recibo
            foreach ($reservasData as $reserva) {
                RelFilerecibo::create([
                    'fk_file_id' => $reserva['reserva_id'],
                    'fk_recibo_id' => $recibo->recibo_id,
                    'fecha' => $data['fecha'],
                    'fk_moneda_id' => $data['moneda_id'],
                    'monto' => $reserva['monto']
                ]);
            }

            // 9. Relacionar facturas si existen
            if (!empty($data['facturas'])) {
                foreach ($data['facturas'] as $factura) {
                    // Validar que no se exceda el monto disponible de la factura
                    $facturaObj = Factura::find($factura['factura_id']);
                    $montoYaAplicado = RelFacturarecibo::where('fk_factura_id', $factura['factura_id'])
                        ->sum('monto');

                    if (($montoYaAplicado + $factura['monto_aplicado']) > $facturaObj->factura_total) {
                        throw new \Exception('El monto aplicado excede el total disponible de la factura ID: ' . $factura['factura_id']);
                    }

                    RelFacturarecibo::create([
                        'fk_factura_id' => $factura['factura_id'],
                        'fk_recibo_id' => $recibo->recibo_id,
                        'monto' => $factura['monto_aplicado'],
                        'fecha' => $data['fecha']
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Recibo procesado exitosamente',
                'data' => [
                    'recibo_id' => $recibo->recibo_id,
                    'recibo_nro' => $recibo->recibo_nro,
                    'asiento_id' => $asiento->asientocontable_id,
                    'monto_total' => $data['monto_total'],
                    'cotizacion_aplicada' => $cotizacion
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el recibo',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get currency exchange rate
     */
    private function getCurrencyRate($currencyId, $date)
    {
        $cotizacion = Cotizacion::where('cotizacion_moneda', $currencyId)
            ->whereDate('cotizacion_fecha', '<=', $date)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        if ($cotizacion) {
            return $cotizacion->cotizacion_relacion;
        }

        // Fallback to most recent rate
        $cotizacion = Cotizacion::where('cotizacion_moneda', $currencyId)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        return $cotizacion ? $cotizacion->cotizacion_relacion : 1.0;
    }

    /**
     * Generate receipt number
     */
    private function generateReciboNumber()
    {
        $lastRecibo = Recibo::orderBy('recibo_id', 'desc')->first();
        $nextNumber = $lastRecibo ? ($lastRecibo->recibo_id + 1) : 1;

        return 'REC-' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }
}