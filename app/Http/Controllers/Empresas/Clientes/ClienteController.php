<?php

namespace App\Http\Controllers\Empresas\Clientes;

use App\Models\Cliente;
use App\Models\Creditoextra;
use App\Models\Cotizacion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Helpers\PermisoHelper;
use App\Helpers\SysconfigHelper;

class ClienteController extends Controller
{
    private $sectionId = 112; // Asignar el ID de sección correspondiente

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 100);
        $query = Cliente::query();

        // Agregar filtros básicos aquí
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('cliente_nombre', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('cliente_razonsocial', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('cuit', 'LIKE', "%{$searchTerm}%");
            });
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!PermisoHelper::tienePermiso($this->sectionId, 'alta')) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'cliente_nombre' => 'required|string|max:250',
            'cliente_razonsocial' => 'required|string|max:250',
            'cuit' => 'required|string|max:20',
            'fk_tipoclavefiscal_id' => 'required|integer|exists:tipoclavefiscal,tipoclavefiscal_id',
            'fk_tipofactura_id' => 'required|integer|exists:tipofactura,tipofactura_id',
            'fk_condicioniva_id' => 'required|integer|exists:condicioniva,condicioniva_id',
            'fk_pais_id' => 'required|integer|exists:pais,pais_id',
            'fk_ciudad_id' => 'required|integer|exists:ciudad,ciudad_id',
            'cliente_direccionfiscal' => 'required|string|max:250',
            'cliente_email' => 'required|string|email|max:250',
            // ...otras reglas si las necesitas
        ], [
            'cliente_nombre.required' => 'El nombre del cliente es obligatorio.',
            'cliente_razonsocial.required' => 'La razón social es obligatoria.',
            'cuit.required' => 'El CUIT es obligatorio.',
            'fk_tipofactura_id.required' => 'El tipo de factura es obligatorio.',
            'fk_tipofactura_id.integer' => 'El tipo de factura debe ser un número entero.',
            'fk_tipofactura_id.exists' => 'El tipo de factura seleccionado no es válido.',
            'fk_condicioniva_id.required' => 'La condición de IVA es obligatoria.',
            'fk_condicioniva_id.integer' => 'La condición de IVA debe ser un número entero.',
            'fk_condicioniva_id.exists' => 'La condición de IVA seleccionada no es válida.',
            'fk_pais_id.required' => 'El país es obligatorio.',
            'fk_pais_id.integer' => 'El país debe ser un número entero.',
            'fk_pais_id.exists' => 'El país seleccionado no es válido.',
            'fk_ciudad_id.required' => 'La ciudad es obligatoria.',
            'fk_ciudad_id.integer' => 'La ciudad debe ser un número entero.',
            'fk_ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'fk_tipoclavefiscal_id.required' => 'El tipo de clave fiscal es obligatorio.',
            'fk_tipoclavefiscal_id.integer' => 'El tipo de clave fiscal debe ser un número entero.',
            'fk_tipoclavefiscal_id.exists' => 'El tipo de clave fiscal seleccionado no es válido.',
            'cliente_direccionfiscal.required' => 'La dirección fiscal es obligatoria.',
            'cliente_email.required' => 'El email es obligatorio.',
            'cliente_email.email' => 'El email debe ser una dirección de correo válida.',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $model = new Cliente();
        $tableColumns = collect(Schema::getColumnListing($model->getTable()));

        if (isset($data['cuit'])) {
            $data['cuit'] = str_replace(['-', '.'], '', $data['cuit']);
            // Controlar que el CUIT no exista en la base
            if (Cliente::where('cuit', $data['cuit'])->exists()) {
                return response()->json(['errors' => ['cuit' => ['Ya existe un cliente con este CUIT.']]], 422);
            }
        }
        if (!isset($data['fk_idioma_id'])) {
            $data['fk_idioma_id'] = 'es';
        }

        $defaultVacio = [
            'cliente_telefono',
            'cliente_legajo',
            'cliente_fax',
            'cliente_email2',
            'cliente_emailadmin',
            'cliente_ciudad',
            'cliente_provincia',
            'cliente_codigopostal',
            'iata',
            'cliente_logo',
            'gastos_fijo_moneda',
            'fk_moneda_id',
            'comentarios',
            'nombre_representante',
            'cuit_internacional'
        ];
        foreach ($defaultVacio as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
        }

        if (!isset($data['credito_habilitado'])) {
            $data['credito_habilitado'] = 0;
        }
        if (!isset($data['nro_clavefiscal'])) {
            $data['nro_clavefiscal'] = $data['cuit'];
        }

        $defaultEnCero = [
            'fk_tarifario1_id',
            'fk_tarifario2_id',
            'fk_tarifario3_id',
            'limite_credito',
            'credito_utilizado',
            'gastos_porcentaje_1',
            'gastos_porcentaje_2',
            'gastos_porcentaje_3',
            'gastos_fijo_1',
            'gastos_fijo_2',
            'gastos_fijo_3',
            'gastos_iva',
            'plazo_pago',
            'idnemo',
            'idtravelc',
            'licencia_id',
            'facturacion_periodo',
            'fk_usuario_promotor1',
            'fk_usuario_promotor2',
            'fk_usuario_promotor3',
            'fk_usuario_promotor4',
            'fk_usuario_vendedor',
            'cliente_promo',
            'cliente_web',
            'cliente_pasajerodirecto',
            'fk_cadenacliente_id',
            'plazo_pago',
            'idnemo',
            'idtravelc',
            'factura_automatica',
            'tipofacturacion'

        ];
        foreach ($defaultEnCero as $field) {
            if (!isset($data[$field]) && $tableColumns->contains($field)) {
                $data[$field] = 0;
            }
        }

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

        $item = Cliente::create($data);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Cliente::findOrFail($id);
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
            $item = Cliente::findOrFail($id);

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
            $item = Cliente::findOrFail($id);
            $item->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Get client credit limit information
     */
    public function creditLimit($clientId, Request $request)
    {
        // Check if credit system is blocked
        $bloquearCredito = SysconfigHelper::get('bloquearCredito', '0');
        if ($bloquearCredito === '1') {
            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => 'NO-OK',
                'Message' => 'Sistema no disponible'
            ], 401);
        }

        try {
            $cliente = Cliente::findOrFail($clientId);

            // Check if credit is enabled for this client
            if ($cliente->credito_habilitado == 0) {
                return response()->json([
                    'CodeClientBackOffice' => $clientId,
                    'status' => 'NO-OK',
                    'Message' => 'Cliente sin crédito disponible. Favor contactar al administrador.',
                    'credito_autorizado' => 0,
                    'credito_utilizado' => 0,
                    'credito_disponible' => 0
                ]);
            }

            // Get extra credit for today
            $creditoExtra = Creditoextra::where('fk_cliente_id', $clientId)
                ->whereDate('creditoextra_fecha', today())
                ->orderBy('creditoextra_id', 'desc')
                ->first();

            $extraCredit = $creditoExtra ? $creditoExtra->creditoextra_monto : 0;
            $creditoAutorizado = $cliente->limite_credito + $extraCredit;

            // Calculate used credit
            $creditoUtilizado = $this->calculateUsedCredit($clientId);

            // Add requested amount if provided
            $valorSolicitado = $request->input('value', 0);
            $creditoUtilizadoTotal = $creditoUtilizado + floatval($valorSolicitado);

            $status = $creditoUtilizadoTotal < $creditoAutorizado ? 'OK' : 'NO-OK';
            $message = $status === 'OK' ? 'Autorizado.' : 'Cliente sin crédito disponible. Favor contactar al administrador.';

            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => $status,
                'Message' => $message,
                'credito_autorizado' => $creditoAutorizado,
                'credito_utilizado' => $creditoUtilizadoTotal,
                'credito_disponible' => $creditoAutorizado - $creditoUtilizadoTotal
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => 'NO-OK',
                'Message' => 'Cliente no encontrado.'
            ], 404);
        }
    }

    /**
     * Calculate used credit for a client
     */
    private function calculateUsedCredit($clientId)
    {
        $usado = 0;

        // 1. Calculate from unpaid invoices
        $facturas = DB::select("
            SELECT ROUND(factura_conceptos_gravados*1.19+factura_conceptos_gravadosespecial*1.105 + factura_conceptos_exentos + factura_conceptos_nogravados,0) +
                   IF(factura_fecha>'2015-12-17',factura_rgterrestres,0) -
                   CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(factura.remitofull, ':', 3), ':', -1) AS DECIMAL(12,2)) AS total,
                   (SELECT COALESCE(SUM(rel_facturarecibo.monto), 0) FROM rel_facturarecibo WHERE rel_facturarecibo.fk_factura_id=factura.factura_id) AS aplicado
            FROM factura
            WHERE statusfactura != 'AN' AND statusfactura != 'NU' AND fk_cliente_id = ?
        ", [$clientId]);

        foreach ($facturas as $factura) {
            $usado += $factura->total - $factura->aplicado;
        }

        // 2. Calculate from closed/confirmed reservations not yet invoiced
        $reservas = DB::select("
            SELECT reserva.reserva_id, reserva.total, reserva.cobrado, reserva.fk_moneda_id AS fmoneda
            FROM reserva
            JOIN servicio ON servicio.fk_reserva_id = reserva.reserva_id
            WHERE (status != 'CA' AND servicio_id NOT IN (SELECT DISTINCT(fk_servicio_id) FROM rel_serviciofactura WHERE fk_servicio_id IS NOT NULL))
              AND fk_cliente_id = ?
              AND fk_filestatus_id IN ('CL','CO')
              AND reserva.total != reserva.cobrado
            GROUP BY reserva.reserva_id, reserva.total, reserva.cobrado, reserva.fk_moneda_id
        ", [$clientId]);

        foreach ($reservas as $reserva) {
            // Get currency exchange rate - simplified version
            $tcmoneda = $this->getCurrencyRate($reserva->fmoneda);
            $usado += ($reserva->total - $reserva->cobrado) * $tcmoneda;
        }

        return $usado;
    }

    /**
     * Get remaining credit for a client
     */
    public function remainingCredit($clientId)
    {
        try {
            $cliente = Cliente::findOrFail($clientId);

            // Check if credit is enabled for this client
            if ($cliente->credito_habilitado == 0) {
                return response()->json([
                    'cliente_id' => $clientId,
                    'cliente_nombre' => $cliente->cliente_nombre,
                    'credito_habilitado' => false,
                    'limite_credito' => 0,
                    'credito_extra' => 0,
                    'credito_autorizado' => 0,
                    'credito_utilizado' => 0,
                    'credito_disponible' => 0,
                    'mensaje' => 'Cliente sin crédito habilitado'
                ]);
            }

            // Get extra credit for today
            $creditoExtra = Creditoextra::where('fk_cliente_id', $clientId)
                ->whereDate('creditoextra_fecha', today())
                ->orderBy('creditoextra_id', 'desc')
                ->first();

            $extraCredit = $creditoExtra ? $creditoExtra->creditoextra_monto : 0;
            $creditoAutorizado = $cliente->limite_credito + $extraCredit;

            // Calculate used credit
            $creditoUtilizado = $this->calculateUsedCredit($clientId);
            $creditoDisponible = max(0, $creditoAutorizado - $creditoUtilizado);

            $creditoUtilizadoDisplay = max(0, $creditoUtilizado);

            return response()->json([
                'cliente_id' => $clientId,
                'cliente_nombre' => $cliente->cliente_nombre,
                'credito_habilitado' => true,
                'limite_credito' => $cliente->limite_credito,
                'credito_extra' => $extraCredit,
                'credito_autorizado' => $creditoAutorizado,
                'credito_utilizado' => $creditoUtilizado,
                'credito_disponible' => $creditoUtilizadoDisplay,
                'porcentaje_disponible' => $creditoAutorizado > 0 ? round(($creditoUtilizadoDisplay / $creditoAutorizado) * 100, 2) : 0,
                'mensaje' => $creditoDisponible > 0 ? 'Crédito disponible' : 'Sin crédito disponible'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Cliente no encontrado',
                'cliente_id' => $clientId
            ], 404);
        }
    }

    /**
     * Get currency exchange rate from cotizacion table
     */
    private function getCurrencyRate($currencyId, $date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        // Get the most recent exchange rate for the currency and date
        $cotizacion = Cotizacion::where('cotizacion_moneda', $currencyId)
            ->whereDate('cotizacion_fecha', '<=', $date)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        if ($cotizacion) {
            return $cotizacion->cotizacion_relacion;
        }

        // If no rate found, try to get the most recent rate for this currency
        $cotizacion = Cotizacion::where('cotizacion_moneda', $currencyId)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        if ($cotizacion) {
            return $cotizacion->cotizacion_relacion;
        }

        // Default to 1.0 if no exchange rate found
        return 1.0;
    }
}
