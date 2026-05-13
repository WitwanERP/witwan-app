<?php

namespace App\Http\Controllers\Empresas\Clientes;

use App\Helpers\PermisoHelper;
use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Cadenacliente;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\ClienteExtra;
use App\Models\Condicioniva;
use App\Models\Cotizacion;
use App\Models\Creditoextra;
use App\Models\Idioma;
use App\Models\Loginterfase;
use App\Models\Moneda;
use App\Models\Pais;
use App\Models\Reserva;
use App\Models\Tarifario;
use App\Models\Tipoclavefiscal;
use App\Models\Tipofactura;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClienteController extends Controller
{
    private $sectionId = 112; // Asignar el ID de sección correspondiente

    public function index(Request $request)
    {

        $perPage = $request->get('per_page', 100);
        $query = Cliente::visiblesAlUsuario();

        // Agregar filtros básicos aquí
        if ($request->has('search') && ! empty($request->search)) {
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
    public function store(ClienteRequest $request)
    {
        if (! PermisoHelper::tienePermiso($this->sectionId, 'alta')) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        $validated = $request->validated();
        $contactos = $request->input('contactos');
        $tarjetas = $request->input('tarjetas');
        unset($validated['contactos'], $validated['tarjetas']);

        return DB::transaction(function () use ($validated, $contactos, $tarjetas) {
            $model = new Cliente;
            $tableColumns = collect(Schema::getColumnListing($model->getTable()));
            $data = $validated;

            if (! isset($data['fk_idioma_id'])) {
                $data['fk_idioma_id'] = 'es';
            }

            $defaultVacio = [
                'cliente_telefono', 'cliente_legajo', 'cliente_fax',
                'cliente_email2', 'cliente_emailadmin', 'cliente_ciudad',
                'cliente_provincia', 'cliente_codigopostal', 'iata',
                'cliente_logo', 'gastos_fijo_moneda', 'fk_moneda_id',
                'comentarios', 'nombre_representante', 'cuit_internacional',
            ];
            foreach ($defaultVacio as $field) {
                if (! isset($data[$field])) {
                    $data[$field] = '';
                }
            }

            if (! isset($data['credito_habilitado'])) {
                $data['credito_habilitado'] = 0;
            }
            if (! isset($data['nro_clavefiscal'])) {
                $data['nro_clavefiscal'] = $data['cuit'];
            }

            $defaultEnCero = [
                'fk_tarifario1_id', 'fk_tarifario2_id', 'fk_tarifario3_id',
                'limite_credito', 'credito_utilizado',
                'gastos_porcentaje_1', 'gastos_porcentaje_2', 'gastos_porcentaje_3',
                'gastos_fijo_1', 'gastos_fijo_2', 'gastos_fijo_3', 'gastos_iva',
                'plazo_pago', 'idnemo', 'idtravelc', 'licencia_id',
                'facturacion_periodo',
                'fk_usuario_promotor1', 'fk_usuario_promotor2',
                'fk_usuario_promotor3', 'fk_usuario_promotor4',
                'fk_usuario_vendedor',
                'cliente_promo', 'cliente_web', 'cliente_pasajerodirecto',
                'fk_cadenacliente_id',
                'factura_automatica', 'tipofacturacion',
            ];
            foreach ($defaultEnCero as $field) {
                if (! isset($data[$field]) && $tableColumns->contains($field)) {
                    $data[$field] = 0;
                }
            }

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

            $this->saveExtra($item->cliente_id, 'contactos', $contactos);
            $this->saveExtra($item->cliente_id, 'tarjetas', $tarjetas);
            $this->syncTarifarios($item);

            $payload = $item->toArray();
            $payload['contactos'] = $this->loadExtra($item->cliente_id, 'contactos');
            $payload['tarjetas'] = $this->loadExtra($item->cliente_id, 'tarjetas');

            return response()->json($payload, 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = Cliente::visiblesAlUsuario()
                ->with(['ciudad', 'pais', 'condicioniva'])
                ->findOrFail($id);
            $payload = $item->toArray();
            $payload['contactos'] = $this->loadExtra((int) $id, 'contactos');
            $payload['tarjetas'] = $this->loadExtra((int) $id, 'tarjetas');

            return response()->json($payload);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClienteRequest $request, $id)
    {
        if (! PermisoHelper::tienePermiso($this->sectionId, 'modificar')) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        try {
            $item = Cliente::visiblesAlUsuario()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $validated = $request->validated();
        $hasContactos = $request->has('contactos');
        $hasTarjetas = $request->has('tarjetas');
        $contactos = $request->input('contactos');
        $tarjetas = $request->input('tarjetas');
        unset($validated['contactos'], $validated['tarjetas']);

        return DB::transaction(function () use ($item, $validated, $hasContactos, $hasTarjetas, $contactos, $tarjetas) {
            $tableColumns = collect(Schema::getColumnListing($item->getTable()));
            $data = $validated;

            if ($tableColumns->contains('um')) {
                $data['um'] = now();
            }
            if ($tableColumns->contains('fk_usuario_id')) {
                $data['fk_usuario_id'] = auth()->id();
            }

            $item->update($data);

            if ($hasContactos) {
                $this->saveExtra($item->cliente_id, 'contactos', $contactos);
            }
            if ($hasTarjetas) {
                $this->saveExtra($item->cliente_id, 'tarjetas', $tarjetas);
            }

            $touchedTarifarios = array_intersect_key(
                $data,
                array_flip(['fk_tarifario1_id', 'fk_tarifario2_id', 'fk_tarifario3_id'])
            );
            if (! empty($touchedTarifarios)) {
                $this->syncTarifarios($item->fresh());
            }

            $payload = $item->fresh()->toArray();
            $payload['contactos'] = $this->loadExtra($item->cliente_id, 'contactos');
            $payload['tarjetas'] = $this->loadExtra($item->cliente_id, 'tarjetas');

            return response()->json($payload);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (! PermisoHelper::tienePermiso($this->sectionId, 'baja')) {
            return response()->json(['error' => 'Permiso denegado'], 403);
        }

        try {
            $item = Cliente::visiblesAlUsuario()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $reservas = Reserva::where('facturar_a', $id)->count();
        if ($reservas > 0) {
            return response()->json([
                'error' => 'No se puede eliminar: cliente con reservas asociadas',
                'reservas' => $reservas,
            ], 422);
        }

        $item->habilita = 'N';
        $tableColumns = collect(Schema::getColumnListing($item->getTable()));
        if ($tableColumns->contains('um')) {
            $item->um = now();
        }
        if ($tableColumns->contains('fk_usuario_id')) {
            $item->fk_usuario_id = auth()->id();
        }
        $item->save();

        return response()->json(null, 204);
    }

    /**
     * Search clientes by nombre, razón social o CUIT.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $limit = (int) $request->get('limit', 10);
        $limit = max(1, min($limit, 50));

        $rows = Cliente::visiblesAlUsuario()
            ->where('habilita', 'S')
            ->where(function ($w) use ($q) {
                $w->where('cliente_nombre', 'LIKE', "%{$q}%")
                    ->orWhere('cliente_razonsocial', 'LIKE', "%{$q}%")
                    ->orWhere('cuit', 'LIKE', "%{$q}%");
            })
            ->orderBy('cliente_nombre')
            ->limit($limit)
            ->get(['cliente_id as id', 'cliente_nombre as nombre', 'cuit']);

        return response()->json($rows);
    }

    /**
     * Aggregated catalogs needed by the cliente form.
     * Cached for 10 minutes — these change rarely.
     */
    public function options()
    {
        $data = Cache::remember('cliente_options', 600, function () {
            return [
                'paises' => Pais::orderBy('pais_nombre')
                    ->get(['pais_id as id', 'pais_nombre as nombre', 'pais_codigo as codigo']),

                'ciudades' => Ciudad::where('ciudad_activo', 1)
                    ->orderBy('ciudad_nombre')
                    ->get(['ciudad_id as id', 'ciudad_nombre as nombre', 'fk_pais_id', 'ciudad_codigo as codigo']),

                'condiciones_iva' => Condicioniva::orderBy('condicioniva_nombre')
                    ->get(['condicioniva_id as id', 'condicioniva_nombre as nombre', 'fk_tipofactura_id', 'porcentaje', 'incluido']),

                'tipos_factura' => Tipofactura::orderBy('tipofactura_nombre')
                    ->get(['tipofactura_id as id', 'tipofactura_nombre as nombre']),

                'tipos_clave_fiscal' => Tipoclavefiscal::orderBy('tipoclavefiscal_nombre')
                    ->get(['tipoclavefiscal_id as id', 'tipoclavefiscal_nombre as nombre']),

                'idiomas' => Idioma::orderBy('orden')
                    ->get(['idioma_id as id', 'idioma_nombre as nombre']),

                'tarifarios' => [
                    'receptivo' => Tarifario::where('fk_sistema_id', 1)
                        ->orderBy('orden')
                        ->get(['tarifario_id as id', 'tarifario_nombre as nombre', 'fk_moneda_id']),
                    'mayorista' => Tarifario::where('fk_sistema_id', 2)
                        ->orderBy('orden')
                        ->get(['tarifario_id as id', 'tarifario_nombre as nombre', 'fk_moneda_id']),
                    'nacional' => Tarifario::where('fk_sistema_id', 7)
                        ->orderBy('orden')
                        ->get(['tarifario_id as id', 'tarifario_nombre as nombre', 'fk_moneda_id']),
                ],

                'vendedores' => Usuario::where('usuario_interno', 'Y')
                    ->orderByRaw('TRIM(usuario_apellido)')
                    ->get([
                        'usuario_id as id',
                        DB::raw("CONCAT(usuario_apellido, ' ', usuario_nombre) AS nombre"),
                    ]),

                'cadenas' => Cadenacliente::orderBy('cadenacliente_nombre')
                    ->get(['cadenacliente_id as id', 'cadenacliente_nombre as nombre']),

                'monedas' => Moneda::orderBy('orden')
                    ->get(['moneda_id as id', 'moneda_nombre as nombre', 'iso_code', 'moneda_basica']),

                'representantes' => Cliente::whereIn('representante_geografico', ['Y', '1'])
                    ->orderByRaw('TRIM(cliente_nombre)')
                    ->get(['cliente_id as id', 'cliente_nombre as nombre']),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Persist a JSON-encoded extra row in cliente_extra (replace-on-write).
     * $items === null  → key not present in payload, do not touch existing data.
     * $items === []    → explicit empty array, delete existing row.
     * $items non-empty → replace existing row with the new array.
     */
    private function saveExtra(int $clienteId, string $nombre, ?array $items): void
    {
        if ($items === null) {
            return;
        }

        ClienteExtra::where('fk_cliente_id', $clienteId)
            ->where('extra_nombre', $nombre)
            ->delete();

        if (empty($items)) {
            return;
        }

        ClienteExtra::create([
            'fk_cliente_id' => $clienteId,
            'extra_nombre' => $nombre,
            'extra_valor' => json_encode(array_values($items), JSON_UNESCAPED_UNICODE),
            'regdate' => now(),
        ]);
    }

    /**
     * Load and decode an extra JSON row from cliente_extra.
     */
    private function loadExtra(int $clienteId, string $nombre): array
    {
        $row = ClienteExtra::where('fk_cliente_id', $clienteId)
            ->where('extra_nombre', $nombre)
            ->first();

        if (! $row) {
            return [];
        }

        $decoded = json_decode($row->extra_valor, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sync rel_clientesistema rows from fk_tarifarioN_id fields.
     * Mapping: tarifario1 → sistema 1, tarifario2 → sistema 2, tarifario3 → sistema 7.
     */
    private function syncTarifarios(Cliente $cliente): void
    {
        $map = [
            1 => (int) $cliente->fk_tarifario1_id,
            2 => (int) $cliente->fk_tarifario2_id,
            7 => (int) $cliente->fk_tarifario3_id,
        ];

        DB::table('rel_clientesistema')
            ->where('fk_cliente_id', $cliente->cliente_id)
            ->delete();

        foreach ($map as $sistemaId => $tarifarioId) {
            if ($tarifarioId > 0) {
                DB::table('rel_clientesistema')->insert([
                    'fk_cliente_id' => $cliente->cliente_id,
                    'fk_sistema_id' => $sistemaId,
                    'fk_tarifario_id' => $tarifarioId,
                ]);
            }
        }
    }

    /**
     * Get client credit limit information
     */
    public function creditLimit($clientId, Request $request)
    {
        $valorSolicitado = $request->input('value', 0);
        $monedaSolicitada = $request->input('moneda');
        $ip = $request->ip();

        // Check if credit system is blocked
        $bloquearCredito = SysconfigHelper::get('bloquearCredito', '0');
        if ($bloquearCredito === '1') {
            $this->logCreditLimit($clientId, $valorSolicitado, $monedaSolicitada, $ip, 'NO-OK', 'Sistema no disponible');

            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => 'NO-OK',
                'Message' => 'Sistema no disponible',
            ], 401);
        }

        try {
            $cliente = Cliente::visiblesAlUsuario()->findOrFail($clientId);

            // Check if credit is enabled for this client
            if ($cliente->credito_habilitado == 0) {
                $this->logCreditLimit($clientId, $valorSolicitado, $monedaSolicitada, $ip, 'NO-OK', 'Crédito no habilitado');

                return response()->json([
                    'CodeClientBackOffice' => $clientId,
                    'status' => 'NO-OK',
                    'Message' => 'Cliente sin crédito disponible. Favor contactar al administrador.',
                    'credito_autorizado' => 0,
                    'credito_utilizado' => 0,
                    'credito_disponible' => 0,
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
            $creditoUtilizadoTotal = $creditoUtilizado + floatval($valorSolicitado);

            $status = $creditoUtilizadoTotal < $creditoAutorizado ? 'OK' : 'NO-OK';
            $message = $status === 'OK' ? 'Autorizado.' : 'Cliente sin crédito disponible. Favor contactar al administrador.';

            // Convert to requested currency if provided
            $monedaRespuesta = null;

            if ($monedaSolicitada) {
                $tcMoneda = $this->getCurrencyRate($monedaSolicitada);
                if ($tcMoneda > 0 && $tcMoneda != 1) {
                    $creditoAutorizado = $creditoAutorizado / $tcMoneda;
                    $creditoUtilizadoTotal = $creditoUtilizadoTotal / $tcMoneda;
                }
                $monedaRespuesta = $monedaSolicitada;
            } else {
                // Get base currency, except for special IPs
                $useUSD = false;
                if ($ip === '137.116.211.8') {
                    $useUSD = true;
                } else {
                    // Check if IP is in 20.101.238.16/28
                    $ipLong = ip2long($ip);
                    $rangeStart = ip2long('20.101.238.16');
                    $rangeEnd = ip2long('20.101.238.31'); // /28 = 16 IPs, last is .31
                    if ($ipLong !== false && $ipLong >= $rangeStart && $ipLong <= $rangeEnd) {
                        $useUSD = true;
                    }
                }
                if ($useUSD) {
                    $monedaRespuesta = 'USD';
                } else {
                    $monedaBase = Moneda::where('moneda_basica', 'S')->first();
                    $monedaRespuesta = $monedaBase ? $monedaBase->moneda_id : null;
                }
            }

            $this->logCreditLimit(
                $clientId,
                $valorSolicitado,
                $monedaRespuesta,
                $ip,
                $status,
                $message,
                round($creditoAutorizado, 2),
                round($creditoUtilizadoTotal, 2)
            );

            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => $status,
                'Message' => $message,
                'credito_autorizado' => round($creditoAutorizado, 2),
                'credito_utilizado' => round($creditoUtilizadoTotal, 2),
                'credito_disponible' => round($creditoAutorizado - $creditoUtilizadoTotal, 2),
                'moneda' => $monedaRespuesta,
            ]);
        } catch (ModelNotFoundException $e) {
            $this->logCreditLimit($clientId, $valorSolicitado, $monedaSolicitada, $ip, 'NO-OK', 'Cliente no encontrado');

            return response()->json([
                'CodeClientBackOffice' => $clientId,
                'status' => 'NO-OK',
                'Message' => 'Cliente no encontrado.',
            ], 404);
        }
    }

    /**
     * Log a credit limit check to loginterfase.
     */
    private function logCreditLimit(
        $clientId,
        $valorSolicitado,
        $monedaSolicitada,
        $ip,
        $status,
        $message,
        $creditoAutorizado = null,
        $creditoUtilizado = null
    ) {
        try {
            $payload = [
                'cliente_id' => $clientId,
                'value' => $valorSolicitado,
                'moneda' => $monedaSolicitada,
                'ip' => $ip,
                'status' => $status,
                'message' => $message,
            ];
            if ($creditoAutorizado !== null) {
                $payload['credito_autorizado'] = $creditoAutorizado;
            }
            if ($creditoUtilizado !== null) {
                $payload['credito_utilizado'] = $creditoUtilizado;
            }

            Loginterfase::create([
                'loginterfase_fecha' => now(),
                'loginterfase_tipo' => 'control_credito',
                'loginterfase_texto' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // No bloquear el flujo de control de crédito si falla el log
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
            $cliente = Cliente::visiblesAlUsuario()->findOrFail($clientId);

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
                    'mensaje' => 'Cliente sin crédito habilitado',
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
            $porcentajeDisponible = $creditoAutorizado > 0 ? round(($creditoDisponible / $creditoAutorizado) * 100, 2) : 0;
            if ($porcentajeDisponible > 100) {
                $porcentajeDisponible = 100;
            }

            return response()->json([
                'cliente_id' => $clientId,
                'cliente_nombre' => $cliente->cliente_nombre,
                'credito_habilitado' => true,
                'limite_credito' => $cliente->limite_credito,
                'credito_extra' => $extraCredit,
                'credito_autorizado' => $creditoAutorizado,
                'credito_utilizado' => $creditoUtilizadoDisplay,
                'credito_disponible' => $creditoDisponible,
                'porcentaje_disponible' => $porcentajeDisponible,
                'mensaje' => $creditoDisponible > 0 ? 'Crédito disponible' : 'Sin crédito disponible',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Cliente no encontrado',
                'cliente_id' => $clientId,
            ], 404);
        }
    }

    /**
     * Get currency exchange rate from cotizacion table
     */
    private function getCurrencyRate($currencyId, $date = null)
    {
        if (! $date) {
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
