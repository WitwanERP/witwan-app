<?php

namespace App\Http\Controllers\Admin\Documentos;

use App\Helpers\PermisoHelper;
use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Controller;
use App\Models\Asientocontable;
use App\Models\Cierrecaja;
use App\Models\Cotizacion;
use App\Models\Facturaproveedor;
use App\Models\Moneda;
use App\Models\Movimiento;
use App\Models\Plancuenta;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\RelFacturaproveedorocupacion;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Facturas de Terceros (facturas de proveedor).
 *
 * Replica el controlador CodeIgniter `administracion/factura3ero.php`.
 * Cubre el flujo estándar (todas las licencias por defecto) y la división
 * multi-base de SECONTUR (witwan_secontur1/2/3 vía conexiones de BD).
 * Las ramas de licencias `mutual` y `towerXXX` quedan fuera de alcance
 * por estar en desuso.
 *
 * Las cuentas contables (fc3exento, cuentaproveedor, etc.) se resuelven
 * desde la tabla `sysconfig`, igual que el resto de la app.
 */
class FacturaproveedorController extends Controller
{
    /** Sección de permisos para Facturas de Terceros. */
    private const SECTION_ID = 0;

    /**
     * Listado de facturas de proveedor con los montos calculados
     * (neto gravado, IVA por alícuota, total, etc.), replicando la query
     * del listado del controlador CI.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 100);

        $coef2 = $this->coef2();
        $decimales = $this->valoresDecimales();
        $monedaBasica = $this->monedaBasica();

        // Multiplicadores reutilizados: cotización si la moneda != básica,
        // y -1 cuando es Nota de Crédito.
        $ctz = "IF(facturaproveedor.fk_moneda_id != ?, facturaproveedor.cotizacion, 1)";
        $signo = "IF(facturaproveedor.facturaproveedor_tipodocumento = 'Nota de Credito', -1, 1)";

        $query = Facturaproveedor::query()
            ->from('facturaproveedor')
            ->join('proveedor', 'proveedor.proveedor_id', '=', 'facturaproveedor.fk_proveedor_id')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'facturaproveedor.fk_usuario_id')
            ->leftJoin('plancuenta', 'plancuenta.plancuenta_id', '=', 'facturaproveedor.fk_plancuenta_id')
            ->leftJoin('rel_facturaproveedorocupacion', 'rel_facturaproveedorocupacion.fk_facturaproveedor_id', '=', 'facturaproveedor.facturaproveedor_id')
            ->leftJoin('servicio', 'servicio.servicio_id', '=', 'rel_facturaproveedorocupacion.fk_ocupacion_id')
            ->leftJoin('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->groupBy('facturaproveedor.facturaproveedor_id')
            ->orderByDesc('facturaproveedor.fecha')
            ->selectRaw("
                facturaproveedor.facturaproveedor_id,
                facturaproveedor.facturaproveedor_tipodocumento,
                facturaproveedor.fk_proveedor_id,
                facturaproveedor.fk_plancuenta_id,
                facturaproveedor.facturaproveedor_nro,
                CONCAT(IF(facturaproveedor.facturaproveedor_tipodocumento='Factura','FC',IF(facturaproveedor.facturaproveedor_tipodocumento='Nota de Credito','NC','')),' ',facturaproveedor.facturaproveedor_nro) AS numero,
                facturaproveedor.fecha,
                facturaproveedor.fechacontable,
                facturaproveedor.facturaproveedor_tipofactura,
                facturaproveedor.vencimiento,
                facturaproveedor.cotizacion,
                facturaproveedor.descripcion,
                facturaproveedor.fechacarga,
                facturaproveedor.tipomovimiento,
                facturaproveedor.fk_moneda_id,
                facturaproveedor.fk_proyecto_id,
                reserva.codigo,
                ROUND(facturaproveedor.ivatur * {$ctz} * {$signo}, 2) AS ivatur,
                ROUND((facturaproveedor.montogeneral + facturaproveedor.montoespecial + facturaproveedor.monto27 + facturaproveedor.monto25) * {$ctz} * {$signo}, 2) AS netogravado,
                ROUND(facturaproveedor.montoexento * {$ctz} * {$signo}, 2) AS montoexento,
                ROUND(facturaproveedor.montonocomputable * {$ctz} * {$signo}, 2) AS montonocomputable,
                ROUND(facturaproveedor.montoespecial * {$ctz} * {$signo}, 2) AS montoespecial,
                ROUND(facturaproveedor.montogeneral * {$ctz} * {$signo}, 2) AS montogeneral,
                ROUND(facturaproveedor.monto27 * {$ctz} * {$signo}, 2) AS monto27,
                ROUND(facturaproveedor.monto25 * {$ctz} * {$signo}, 2) AS monto25,
                ROUND(facturaproveedor.retencioniva * {$ctz} * {$signo}, 2) AS retencioniva,
                ROUND(facturaproveedor.percepcioniva * {$ctz} * {$signo}, 2) AS percepcioniva,
                ROUND(facturaproveedor.retencioniibb * {$ctz} * {$signo}, 2) AS retencioniibb,
                ROUND(facturaproveedor.percepcioniibb * {$ctz} * {$signo}, 2) AS percepcioniibb,
                ROUND(facturaproveedor.retencionganancias * {$ctz} * {$signo}, 2) AS retencionganancias,
                ROUND(facturaproveedor.percepcionganancias * {$ctz} * {$signo}, 2) AS percepcionganancias,
                ROUND(facturaproveedor.otrosimpuestos * {$ctz} * {$signo}, 2) AS otrosimpuestos,
                ROUND(facturaproveedor.montototal * {$ctz} * {$signo}, 2) AS montototal,
                SUBSTRING(proveedor.razonsocial, 1, 16) AS proveedor_nombre,
                CONCAT(usuario.usuario_nombre,' ',usuario.usuario_apellido) AS usuario_nombre,
                proveedor.cuit,
                ROUND(facturaproveedor.monto27 * 0.27 * {$ctz} * {$signo}, 2) AS i27,
                ROUND(facturaproveedor.montoespecial * 0.105 * {$ctz} * {$signo}, 2) AS i105,
                ROUND(facturaproveedor.montogeneral * {$coef2} * {$ctz} * {$signo}, {$decimales}) AS i21,
                ROUND(facturaproveedor.monto25 * 0.025 * {$ctz} * {$signo}, 2) AS i25,
                plancuenta.plancuenta_nombre
            ", [$monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica, $monedaBasica]);

        // Filtros del listado (equivalentes al filtro del listado CI).
        if ($request->filled('proveedor')) {
            $query->where('facturaproveedor.fk_proveedor_id', (int) $request->input('proveedor'));
        }
        if ($request->filled('numero')) {
            $query->where('facturaproveedor.facturaproveedor_nro', $request->input('numero'));
        }
        if ($request->filled('codigo')) {
            $query->where('reserva.codigo', $request->input('codigo'));
        }
        if ($request->filled('proyecto')) {
            $query->where('facturaproveedor.fk_proyecto_id', (int) $request->input('proyecto'));
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('facturaproveedor.fecha', '>=', $this->parseFecha($request->input('fecha_desde')));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('facturaproveedor.fecha', '<=', $this->parseFecha($request->input('fecha_hasta')));
        }
        if ($request->filled('fechacontable_desde')) {
            $query->whereDate('facturaproveedor.fechacontable', '>=', $this->parseFecha($request->input('fechacontable_desde')));
        }
        if ($request->filled('fechacontable_hasta')) {
            $query->whereDate('facturaproveedor.fechacontable', '<=', $this->parseFecha($request->input('fechacontable_hasta')));
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Crea una factura de tercero: registro, servicio contable asociado,
     * relaciones con ocupaciones y el asiento contable con sus movimientos
     * débito/haber. Replica `factura3ero::save()`.
     */
    public function store(Request $request)
    {
        if (!PermisoHelper::tienePermiso(self::SECTION_ID, 'alta')) {
            return response()->json(['message' => 'No tiene permiso para crear facturas de terceros'], 403);
        }

        $validator = Validator::make($request->all(), [
            'facturaproveedor_nro' => 'required|string',
            'facturaproveedor_tipodocumento' => 'required|string',
            'facturaproveedor_tipofactura' => 'nullable|string',
            'fk_proveedor_id' => 'required|integer',
            'fk_moneda_id' => 'required|string',
            'fecha' => 'required',
            'tipomovimiento' => 'required|string',
            'nocomputable' => 'nullable|numeric',
            'exento' => 'nullable|numeric',
            'especial' => 'nullable|numeric',
            'general' => 'nullable|numeric',
            'monto27' => 'nullable|numeric',
            'monto25' => 'nullable|numeric',
            'ivatur' => 'nullable|numeric',
            'ivatotal' => 'nullable|numeric',
            'otrosimpuestos' => 'nullable|numeric',
            'retencioniva' => 'nullable|numeric',
            'retencioniibb' => 'nullable|numeric',
            'percepcioniva' => 'nullable|numeric',
            'percepcioniibb' => 'nullable|numeric',
            'retencionganancias' => 'nullable|numeric',
            'percepcionganancias' => 'nullable|numeric',
            'ocupacion' => 'nullable|array',
            'ocupacion.*.id' => 'required_with:ocupacion|integer',
            'ocupacion.*.monto' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pst = $request->all();

        // En facturas de Gasto y Boleta la ocupación la generamos nosotros
        // (servicio contable asociado a la reserva 1), por lo que no se aceptan
        // ocupaciones. BSP queda afuera por ser un caso especial.
        if (in_array($pst['tipomovimiento'], ['Gasto', 'Boleta'], true) && !empty($pst['ocupacion'])) {
            return response()->json([
                'message' => 'Las facturas de tipo Gasto o Boleta no admiten ocupaciones; el servicio se genera automáticamente.',
            ], 422);
        }

        // Control de duplicados: no puede repetirse la combinación
        // nro + proveedor + tipo de documento.
        $existe = Facturaproveedor::where('facturaproveedor_nro', trim($pst['facturaproveedor_nro']))
            ->where('fk_proveedor_id', (int) $pst['fk_proveedor_id'])
            ->where('facturaproveedor_tipodocumento', trim($pst['facturaproveedor_tipodocumento']))
            ->exists();
        if ($existe) {
            return response()->json([
                'message' => 'Ya existe una factura con ese número, proveedor y tipo de documento.',
            ], 409);
        }

        $coef2 = $this->coef2();
        $licPais = $this->licPais();

        // --- Cálculo de montos (idéntico a factura3ero::save) ---
        $nocomputable = (float) ($pst['nocomputable'] ?? 0);
        $exento = (float) ($pst['exento'] ?? 0);
        $especial = (float) ($pst['especial'] ?? 0);
        $general = (float) ($pst['general'] ?? 0);
        $monto27 = (float) ($pst['monto27'] ?? 0);
        $monto25 = (float) ($pst['monto25'] ?? 0);
        $ivatur = (float) ($pst['ivatur'] ?? 0);
        $otrosimpuestos = (float) ($pst['otrosimpuestos'] ?? 0);
        $cotizacion = (float) ($pst['cotizacion'] ?? 1);

        $montosiniva = $nocomputable + $exento + $especial + $general + $monto27 + $monto25;

        $soloiva = round($especial * 0.105, 2)
            + round($general * $coef2, 2)
            + round($monto27 * 0.27, 2)
            + round($monto25 * 0.025, 2)
            - round($ivatur, 2);
        if ($licPais === 'CL') {
            $soloiva = round((float) ($pst['ivatotal'] ?? 0));
        }

        $retencioniva = (float) ($pst['retencioniva'] ?? 0);
        $retencioniibb = (float) ($pst['retencioniibb'] ?? 0);
        $percepcioniva = (float) ($pst['percepcioniva'] ?? 0);
        $percepcioniibb = (float) ($pst['percepcioniibb'] ?? 0);
        $retencionganancias = (float) ($pst['retencionganancias'] ?? 0);
        $percepcionganancias = (float) ($pst['percepcionganancias'] ?? 0);

        $retper = $retencioniva + $retencioniibb + $percepcioniva + $percepcioniibb
            + $retencionganancias + $percepcionganancias + $otrosimpuestos;

        $montototal = $montosiniva + $soloiva + $retper;
        $montoperc = $montosiniva + $retper;

        // La factura debe tener montos cargados: el total no puede ser 0.
        if (round($montototal, 2) == 0.0) {
            return response()->json([
                'message' => 'Debe ingresar al menos un monto. El total de la factura no puede ser 0.',
            ], 422);
        }

        if (empty($pst['fechacontable'])) {
            $pst['fechacontable'] = $pst['fecha'];
        }

        $uid = Auth::id() ?? 1;
        $esNotaCredito = $pst['facturaproveedor_tipodocumento'] === 'Nota de Credito';
        $esSecontur = $this->esSecontur();

        try {
            $resultado = DB::transaction(function () use (
                $pst, $uid, $coef2, $licPais, $esNotaCredito, $esSecontur,
                $montototal, $montoperc, $soloiva, $otrosimpuestos, $ivatur, $cotizacion,
                $exento, $general, $especial, $monto27, $monto25
            ) {
                // 1) Cabecera de la factura.
                $factura = Facturaproveedor::create([
                    'facturaproveedor_nro' => $pst['facturaproveedor_nro'],
                    'facturaproveedor_tipodocumento' => $pst['facturaproveedor_tipodocumento'],
                    'facturaproveedor_tipofactura' => $pst['facturaproveedor_tipofactura'] ?? '',
                    'fk_proveedor_id' => (int) $pst['fk_proveedor_id'],
                    'fk_proyecto_id' => isset($pst['fk_proyecto_id']) ? (int) $pst['fk_proyecto_id'] : 0,
                    'fechacarga' => Carbon::today(),
                    'fecha' => $this->parseFecha($pst['fecha']),
                    'fechacontable' => $this->parseFecha($pst['fechacontable']),
                    'vencimiento' => !empty($pst['vencimiento']) ? $this->parseFecha($pst['vencimiento']) : null,
                    'fk_moneda_id' => $pst['fk_moneda_id'],
                    'montonocomputable' => (float) ($pst['nocomputable'] ?? 0),
                    'montoexento' => $exento,
                    'montoespecial' => $especial,
                    'montogeneral' => $general,
                    'monto27' => $monto27,
                    'monto25' => $monto25,
                    'ivatotal' => (float) ($pst['ivatotal'] ?? 0),
                    'percepcioniva' => (float) ($pst['percepcioniva'] ?? 0),
                    'percepcioniibb' => (float) ($pst['percepcioniibb'] ?? 0),
                    'retencioniva' => (float) ($pst['retencioniva'] ?? 0),
                    'retencioniibb' => (float) ($pst['retencioniibb'] ?? 0),
                    'retencionganancias' => (float) ($pst['retencionganancias'] ?? 0),
                    'percepcionganancias' => (float) ($pst['percepcionganancias'] ?? 0),
                    'cotizacion' => $cotizacion,
                    'otrosimpuestos' => $otrosimpuestos,
                    'montototal' => $montototal,
                    'ivatur' => $ivatur,
                    'descripcion' => $pst['observaciones'] ?? ($pst['descripcion'] ?? ''),
                    'fk_itemgasto_id' => isset($pst['fk_itemgasto_id']) ? (int) $pst['fk_itemgasto_id'] : 0,
                    'electronica' => isset($pst['electronica']) ? (string) $pst['electronica'] : '',
                    'fk_plancuenta_id' => (int) ($pst['fk_plancuenta_id'] ?? 0),
                    'tipomovimiento' => $pst['tipomovimiento'],
                    'imputacion' => $this->buildImputacion($pst),
                    'fk_usuario_id' => $uid,
                    'adicionales' => isset($pst['adicionales']) ? json_encode($pst['adicionales']) : null,
                ]);

                $fcpid = $factura->facturaproveedor_id;

                // 2) Servicio contable asociado (para movimientos != 'Servicio').
                $montopercFinal = $montoperc;
                $soloivaFinal = $soloiva;
                if ($esNotaCredito) {
                    $montopercFinal = abs($montoperc) * -1;
                    $soloivaFinal = abs($soloiva) * -1;
                }

                if ($pst['tipomovimiento'] !== 'Servicio') {
                    $servicio = Servicio::create([
                        'fk_proveedor_id' => (int) $pst['fk_proveedor_id'],
                        'servicio_nombre' => 'Factura ' . $pst['facturaproveedor_nro'],
                        'fk_tipoproducto_id' => 'ADM',
                        'fk_reserva_id' => 1,
                        'status' => 'CO',
                        'vigencia_ini' => $this->parseFecha($pst['fechacontable']),
                        'vigencia_fin' => $this->parseFecha($pst['fechacontable']),
                        'vencimiento_proveedor' => !empty($pst['vencimiento']) ? $this->parseFecha($pst['vencimiento']) : null,
                        'fk_moneda_id' => $pst['fk_moneda_id'],
                        'moneda_costo' => $pst['fk_moneda_id'],
                        'costo' => $montopercFinal,
                        'iva_costo' => $soloivaFinal,
                    ]);

                    RelFacturaproveedorocupacion::create([
                        'fk_facturaproveedor_id' => $fcpid,
                        'fk_ocupacion_id' => $servicio->servicio_id,
                    ]);
                }

                // 3) Ocupaciones (servicios ya existentes) enviadas explícitamente,
                //    como objetos { id, monto }.
                if (!empty($pst['ocupacion']) && is_array($pst['ocupacion'])) {
                    foreach ($pst['ocupacion'] as $ocupa) {
                        $ocupacionId = (int) ($ocupa['id'] ?? 0);
                        if ($ocupacionId === 0) {
                            continue;
                        }
                        DB::table('rel_facturaproveedorocupacion')->insertOrIgnore([
                            'fk_facturaproveedor_id' => $fcpid,
                            'fk_ocupacion_id' => $ocupacionId,
                            'monto' => (float) ($ocupa['monto'] ?? 0),
                        ]);
                        // Confirmar servicios que estaban en estado requerido.
                        Servicio::where('servicio_id', $ocupacionId)
                            ->where('status', 'RQ')
                            ->update(['status' => 'CO']);
                    }
                }

                // 4) Asientos contables. En SECONTUR se divide por área de
                //    imputación entre las bases hermanas; el resto va 100% local.
                $dividir = $this->resolverDivision($pst, $fcpid, $esSecontur, $cotizacion, $montototal, $montoperc, $soloiva, $otrosimpuestos, $ivatur, $uid);

                foreach ($dividir as $divide) {
                    [$prc, $conexion, $idFactura] = $divide;
                    if ($esNotaCredito) {
                        $prc = abs($prc) * -1;
                    }
                    $this->generarAsiento($conexion, $pst, $idFactura, $prc, $coef2, $licPais, $uid, $montototal, $ivatur);
                }

                return $factura->fresh(['proveedor', 'plancuenta', 'rel_facturaproveedorocupacions']);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al guardar la factura', 'error' => $e->getMessage()], 500);
        }

        return response()->json($resultado, 201);
    }

    /**
     * Detalle de una factura.
     */
    public function show($id)
    {
        try {
            $item = Facturaproveedor::with(['proveedor', 'plancuenta', 'proyecto', 'usuario'])
                ->findOrFail($id);
            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Actualización limitada: cuenta contable, proyecto, item de gasto,
     * imputación y número. Replica `factura3ero::save_after_edit()`.
     */
    public function update(Request $request, $id)
    {
        if (!PermisoHelper::tienePermiso(self::SECTION_ID, 'edicion')) {
            return response()->json(['message' => 'No tiene permiso para editar facturas de terceros'], 403);
        }

        try {
            $item = Facturaproveedor::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // No permitir editar si la fecha contable cae en un período cerrado.
        if ($this->periodoCerrado($item->fechacontable)) {
            return response()->json(['message' => 'Fecha fuera del periodo contable abierto.'], 422);
        }

        $pst = $request->all();
        $data = [];
        if (array_key_exists('fk_plancuenta_id', $pst)) {
            $data['fk_plancuenta_id'] = (int) $pst['fk_plancuenta_id'];
        }
        if (array_key_exists('fk_proyecto_id', $pst)) {
            $data['fk_proyecto_id'] = (int) $pst['fk_proyecto_id'];
        }
        if (array_key_exists('fk_itemgasto_id', $pst)) {
            $data['fk_itemgasto_id'] = (int) $pst['fk_itemgasto_id'];
        }
        if (array_key_exists('areaimputacion', $pst)) {
            $data['imputacion'] = json_encode($pst['areaimputacion']);
        }
        if (array_key_exists('factura_numero', $pst)) {
            $data['facturaproveedor_nro'] = $pst['factura_numero'];
        }

        $item->update($data);

        return response()->json($item->fresh());
    }

    /**
     * Elimina la factura validando período contable y que no esté pagada.
     * Limpia las relaciones y movimientos huérfanos. Replica
     * `_before_delete()` / `_after_delete()`.
     */
    public function destroy($id)
    {
        if (!PermisoHelper::tienePermiso(self::SECTION_ID, 'baja')) {
            return response()->json(['message' => 'No tiene permiso para eliminar facturas de terceros'], 403);
        }

        try {
            $factura = Facturaproveedor::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // Período contable cerrado.
        if ($this->periodoCerrado($factura->fechacontable)) {
            return response()->json(['message' => 'Fecha fuera del periodo contable abierto.'], 422);
        }

        // Factura ya pagada (excepto las de tipo 'Servicio').
        if ($factura->tipomovimiento !== 'Servicio') {
            $pagada = DB::table('rel_ordenadminocupacion as roo')
                ->join('rel_facturaproveedorocupacion as rfo', 'roo.fk_ocupacion_id', '=', 'rfo.fk_ocupacion_id')
                ->join('ordenadmin as o', 'o.ordenadmin_id', '=', 'roo.fk_ordenadmin_id')
                ->where('rfo.fk_facturaproveedor_id', (int) $id)
                ->where('o.status', '!=', 'AN')
                ->exists();
            if ($pagada) {
                return response()->json(['message' => 'No es posible eliminar una factura con pago realizado.'], 422);
            }
        }

        DB::transaction(function () use ($factura) {
            $factura->delete();

            // Limpiar relaciones y movimientos que quedaron huérfanos.
            DB::table('rel_facturaproveedorocupacion')
                ->whereNotIn('fk_facturaproveedor_id', fn ($q) => $q->select('facturaproveedor_id')->from('facturaproveedor'))
                ->delete();
            DB::table('movimiento')
                ->where('fk_facturaproveedor_id', '!=', 0)
                ->whereNotIn('fk_facturaproveedor_id', fn ($q) => $q->select('facturaproveedor_id')->from('facturaproveedor'))
                ->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Datos para impresión: la factura con su proveedor/plan de cuenta y los
     * servicios asociados (con su reserva). Replica `factura3ero::imprimir()`.
     */
    public function imprimir($id)
    {
        try {
            $factura = Facturaproveedor::with(['proveedor', 'plancuenta'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $servicios = DB::table('servicio')
            ->join('rel_facturaproveedorocupacion', 'rel_facturaproveedorocupacion.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->join('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->where('rel_facturaproveedorocupacion.fk_facturaproveedor_id', (int) $id)
            ->groupBy('servicio.servicio_id')
            ->select('servicio.*', 'reserva.codigo')
            ->get();

        return response()->json([
            'data' => $factura,
            'datasvc' => $servicios,
        ]);
    }

    /**
     * Verifica que no exista una factura con el mismo nro/proveedor/tipo.
     * Replica `factura3ero::control()`. Devuelve { ok: bool }.
     */
    public function control(Request $request)
    {
        $existe = Facturaproveedor::where('fk_proveedor_id', (int) $request->get('proveedor'))
            ->where('facturaproveedor_nro', $request->get('nro'))
            ->where('facturaproveedor_tipodocumento', $request->get('tipo'))
            ->exists();

        return response()->json(['ok' => !$existe]);
    }

    /**
     * Datos auxiliares para el formulario de alta (proveedores con CUIT,
     * cuentas de gasto, proyectos, conceptos adicionales).
     * Replica la carga de `factura3ero::create()`.
     */
    public function create()
    {
        $proveedores = Proveedor::query()
            ->whereIn('habilita', ['Y', '1'])
            ->where('cuit', '!=', '')
            ->orderByRaw('TRIM(proveedor_nombre)')
            ->get(['proveedor_id', 'proveedor_nombre', 'iata', 'cuit'])
            ->map(fn ($p) => [
                'id' => $p->proveedor_id,
                'label' => trim($p->proveedor_nombre) . ' -  (' . $p->cuit . ')',
                'cuenta' => $p->iata,
            ]);

        $plancuenta = Plancuenta::where('cuentagasto', 1)
            ->where('plancuenta_titulo', 0)
            ->orderByRaw('TRIM(plancuenta_nombre)')
            ->pluck('plancuenta_nombre', 'plancuenta_id');

        $proyectos = Proyecto::orderByRaw('TRIM(proyecto_nombre)')
            ->pluck('proyecto_nombre', 'proyecto_id');

        $conceptos = [];
        $adicionales = SysconfigHelper::get('adicionales_fc3');
        if ($adicionales) {
            $decoded = json_decode($adicionales, true);
            if (is_array($decoded)) {
                $conceptos = array_keys($decoded);
            }
        }

        return response()->json([
            'proveedores' => $proveedores,
            'plancuenta' => $plancuenta,
            'proyectos' => $proyectos,
            'conceptos' => $conceptos,
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers de contabilidad / configuración
    // ----------------------------------------------------------------

    /**
     * Determina cómo se divide la factura entre las bases contables.
     * Por defecto, 100% en la base local. En SECONTUR se reparte según
     * `areaimputacion` entre las bases hermanas witwan_seconturN, creando
     * la factura PARCIAL y su servicio en cada una.
     *
     * @return array<int, array{0: float, 1: ?string, 2: int}>  [porcentaje, conexión, facturaproveedor_id]
     */
    private function resolverDivision(array $pst, int $fcpid, bool $esSecontur, float $cotizacion, float $montototal, float $montoperc, float $soloiva, float $otrosimpuestos, float $ivatur, int $uid): array
    {
        // Caso general: todo local.
        $dividir = [[1.0, null, $fcpid]];

        if (!$esSecontur || empty($pst['areaimputacion']) || !is_array($pst['areaimputacion'])) {
            return $dividir;
        }

        $dividir = [];
        foreach ($pst['areaimputacion'] as $k => $porc) {
            if ((float) $porc === 0.0) {
                continue;
            }
            $v = $porc / 100;
            $conexion = 'witwan_secontur' . $k;

            // Si la conexión a la base hermana no está configurada, se omite.
            if (!$this->conexionExiste($conexion)) {
                continue;
            }

            $facturaParcial = Facturaproveedor::on($conexion)->create([
                'facturaproveedor_nro' => $pst['facturaproveedor_nro'],
                'facturaproveedor_tipodocumento' => $pst['facturaproveedor_tipodocumento'],
                'facturaproveedor_tipofactura' => $pst['facturaproveedor_tipofactura'] ?? '',
                'fk_proveedor_id' => (int) $pst['fk_proveedor_id'],
                'fk_proyecto_id' => isset($pst['fk_proyecto_id']) ? (int) $pst['fk_proyecto_id'] : 0,
                'fechacarga' => Carbon::today(),
                'fecha' => $this->parseFecha($pst['fecha']),
                'fechacontable' => $this->parseFecha($pst['fechacontable']),
                'vencimiento' => !empty($pst['vencimiento']) ? $this->parseFecha($pst['vencimiento']) : null,
                'fk_moneda_id' => $pst['fk_moneda_id'],
                'montonocomputable' => (float) ($pst['nocomputable'] ?? 0) * $v,
                'montoexento' => (float) ($pst['exento'] ?? 0) * $v,
                'montoespecial' => (float) ($pst['especial'] ?? 0) * $v,
                'montogeneral' => (float) ($pst['general'] ?? 0) * $v,
                'monto27' => (float) ($pst['monto27'] ?? 0) * $v,
                'monto25' => (float) ($pst['monto25'] ?? 0) * $v,
                'ivatotal' => (float) ($pst['ivatotal'] ?? 0) * $v,
                'retencioniva' => (float) ($pst['retencioniva'] ?? 0) * $v,
                'retencioniibb' => (float) ($pst['retencioniibb'] ?? 0) * $v,
                'percepcioniva' => (float) ($pst['percepcioniva'] ?? 0) * $v,
                'percepcioniibb' => (float) ($pst['percepcioniibb'] ?? 0) * $v,
                'percepcionganancias' => (float) ($pst['percepcionganancias'] ?? 0) * $v,
                'retencionganancias' => (float) ($pst['retencionganancias'] ?? 0) * $v,
                'cotizacion' => $cotizacion,
                'otrosimpuestos' => $otrosimpuestos * $v,
                'montototal' => $montototal * $v,
                'ivatur' => $ivatur * $v,
                'descripcion' => $pst['observaciones'] ?? '',
                'fk_plancuenta_id' => (int) ($pst['fk_plancuenta_id'] ?? 0),
                'tipomovimiento' => $pst['tipomovimiento'],
                'imputacion' => 'PARCIAL',
                'fk_usuario_id' => $uid,
            ]);

            $fcpidParcial = $facturaParcial->facturaproveedor_id;

            if ($pst['facturaproveedor_tipodocumento'] !== 'Nota de Credito..') {
                $servicioParcial = Servicio::on($conexion)->create([
                    'fk_proveedor_id' => (int) $pst['fk_proveedor_id'],
                    'servicio_nombre' => 'Factura ' . $pst['facturaproveedor_nro'],
                    'fk_tipoproducto_id' => 'ADM',
                    'fk_reserva_id' => 1,
                    'status' => 'CO',
                    'vigencia_ini' => Carbon::today(),
                    'vigencia_fin' => Carbon::today(),
                    'vencimiento_proveedor' => !empty($pst['vencimiento']) ? $this->parseFecha($pst['vencimiento']) : null,
                    'fk_moneda_id' => $pst['fk_moneda_id'],
                    'moneda_costo' => $pst['fk_moneda_id'],
                    'costo' => $montoperc * $v,
                    'iva_costo' => $soloiva * $v,
                ]);

                DB::connection($conexion)->table('rel_facturaproveedorocupacion')->insertOrIgnore([
                    'fk_facturaproveedor_id' => $fcpidParcial,
                    'fk_ocupacion_id' => $servicioParcial->servicio_id,
                    'monto' => $montototal * $v,
                ]);
            }

            $dividir[] = [$v, $conexion, $fcpidParcial];
        }

        return $dividir;
    }

    /**
     * Genera el asiento contable y sus movimientos (débito por cada
     * concepto + crédito a la cuenta de proveedor) en la conexión indicada.
     * Replica el bloque `foreach ($dividir as $divide)` de factura3ero::save.
     */
    private function generarAsiento(?string $conexion, array $pst, int $idFactura, float $prc, float $coef2, string $licPais, int $uid, float $montototal, float $ivatur): void
    {
        $db = $conexion ? DB::connection($conexion) : DB::connection();
        $fecha = $this->parseFecha($pst['fechacontable']);
        $moneda = $pst['fk_moneda_id'];
        $cotizacion = (float) ($pst['cotizacion'] ?? 1);
        $proveedorId = (int) $pst['fk_proveedor_id'];

        // Crear asiento contable.
        $asientoId = $db->table('asientocontable')->insertGetId([
            'asientocontable_fecha' => $fecha,
            'debe' => 0,
            'haber' => 0,
        ], 'asientocontable_id');

        // Cuenta destino de los montos gravados/exentos.
        // Para gastos/varios usa la cuenta indicada; si no hay, cae a fc3exento.
        $ctaMontos = (int) ($pst['fk_plancuenta_id'] ?? 0);
        if ($ctaMontos === 0) {
            $ctaMontos = $this->cuenta('fc3exento');
        }

        // --- Débitos por montos netos ---
        $netos = [
            'exento' => 'fc3exento',
            'general' => 'fc3gral',
            'especial' => 'fc3especial',
            'nocomputable' => 'fc3nocomputable',
            'monto27' => 'fc3monto27',
            'monto25' => 'fc3monto25',
        ];
        foreach ($netos as $campo => $claveCuenta) {
            $valor = (float) ($pst[$campo] ?? 0);
            if ($valor === 0.0) {
                continue;
            }
            $cuenta = $ctaMontos !== 0 ? $ctaMontos : $this->cuenta($claveCuenta);
            $this->insertarMovimiento($db, $asientoId, 'D', $cuenta, round($valor * $prc, 2), $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
        }

        // --- Débitos por impuestos / retenciones / percepciones ---
        $impuestos = [
            'ctaivatur' => 'ivatur',
            'fc3retiva' => 'retencioniva',
            'fc3retiibb' => 'retencioniibb',
            'fc3retganancias' => 'retencionganancias',
            'fc3perciibb' => 'percepcioniibb',
            'fc3perciva' => 'percepcioniva',
            'fc3perganancias' => 'percepcionganancias',
            'fc3otros' => 'otrosimpuestos',
        ];
        foreach ($impuestos as $claveCuenta => $campo) {
            $valor = (float) ($pst[$campo] ?? 0);
            if ($valor === 0.0) {
                continue;
            }
            // El IVA turismo se contabiliza en negativo.
            $signo = ($claveCuenta === 'ctaivatur') ? -1 : 1;
            $this->insertarMovimiento($db, $asientoId, 'D', $this->cuenta($claveCuenta), round($valor * $prc * $signo, 2), $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
        }

        $esGasto = ($pst['tipomovimiento'] === 'Gasto');

        // --- Débito del IVA general ---
        $general = (float) ($pst['general'] ?? 0);
        if (($general * $coef2) != 0) {
            $montoIva = ($licPais === 'CL')
                ? round((float) ($pst['ivatotal'] ?? 0) * $prc, 2)
                : round($general * $coef2 * $prc, 2);
            $cuentaIva = $esGasto ? $this->cuenta('fc3ivatotal_i') : $this->cuenta('fc3ivatotal');
            $this->insertarMovimiento($db, $asientoId, 'D', $cuentaIva, $montoIva, $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
        }

        // --- Débito del IVA 10.5% (especial) ---
        $especial = (float) ($pst['especial'] ?? 0);
        if (round($especial * 0.105, 2) != 0) {
            $cuentaIva = $esGasto ? $this->cuenta('fc3ivaespecial_i') : $this->cuenta('fc3ivaespecial');
            $this->insertarMovimiento($db, $asientoId, 'D', $cuentaIva, round($especial * 0.105 * $prc, 2), $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
        }

        // --- Débito del IVA 27% ---
        $monto27 = (float) ($pst['monto27'] ?? 0);
        if (round($monto27 * 0.27, 2) != 0) {
            $this->insertarMovimiento($db, $asientoId, 'D', $this->cuenta('fc3iva27'), round($monto27 * 0.27 * $prc, 2), $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
        }

        // --- Crédito a la cuenta de proveedor (contrapartida del total) ---
        $ctaProveedor = ($moneda === 'USD') ? $this->cuenta('cuentaproveedorusd') : $this->cuenta('cuentaproveedor');
        if (in_array($pst['tipomovimiento'], ['Gasto', 'Boleta'], true)) {
            $ctaProveedor = $this->cuenta('cuentaproveedorvarios');
        }
        if ($pst['tipomovimiento'] === 'BSP') {
            $ctaProveedor = $this->cuenta('provisionBSP');
        }
        if ($this->esSecontur() && $proveedorId === 370) {
            $ctaProveedor = $this->cuenta('provisionBSP');
        }
        $this->insertarMovimiento($db, $asientoId, 'H', $ctaProveedor, $montototal * $prc, $pst, $idFactura, $uid, $proveedorId, $moneda, $cotizacion, $fecha);
    }

    /**
     * Inserta un movimiento contable (débito o haber).
     */
    private function insertarMovimiento($db, int $asientoId, string $deha, int $cuenta, float $monto, array $pst, int $idFactura, int $uid, int $proveedorId, string $moneda, float $cotizacion, $fecha): void
    {
        $row = [
            'fk_plancuenta_id' => $cuenta,
            'fk_moneda_id' => $moneda,
            'cotizacion_moneda' => $cotizacion,
            'monto' => $monto,
            'tipo' => 'E',
            'fecha' => $fecha,
            'fecha_acreditacion' => $fecha,
            'fk_usuario_id' => $uid,
            'fk_proveedor_id' => $proveedorId,
            'fk_asientocontable_id' => $asientoId,
            'fk_facturaproveedor_id' => $idFactura,
        ];
        if ($deha === 'D') {
            $row['deha'] = 'D';
            $row['cuenta_debito'] = $cuenta;
        } else {
            $row['deha'] = 'H';
            $row['cuenta_credito'] = $cuenta;
        }

        $db->table('movimiento')->insert($row);
    }

    /**
     * Arma el JSON de imputación a partir del request.
     */
    private function buildImputacion(array $pst): string
    {
        if (isset($pst['subareaimputacion']) && is_array($pst['subareaimputacion'])) {
            return json_encode([
                'areaimputacion' => $pst['areaimputacion'] ?? null,
                'subareaimputacion' => $pst['subareaimputacion'],
            ]);
        }
        return json_encode($pst['areaimputacion'] ?? '');
    }

    /**
     * Verifica si la fecha contable cae dentro de un cierre de caja.
     */
    private function periodoCerrado($fechacontable): bool
    {
        if (!$fechacontable) {
            return false;
        }
        $fecha = $fechacontable instanceof Carbon ? $fechacontable->toDateString() : $fechacontable;
        return Cierrecaja::whereDate('cierrecaja_fecha', '>=', $fecha)->exists();
    }

    /**
     * ID de cuenta contable resuelto desde sysconfig.
     */
    private function cuenta(string $clave): int
    {
        return (int) SysconfigHelper::get($clave, 0);
    }

    /**
     * Coeficiente de IVA general (alícuota / 100). `tasageneral` en sysconfig
     * puede venir como fracción (0.21) o como porcentaje (21).
     */
    private function coef2(): float
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 21);
        return $tasa < 1 ? $tasa : $tasa / 100;
    }

    /**
     * Decimales para el redondeo del IVA (0 en Chile, 2 en el resto).
     */
    private function valoresDecimales(): int
    {
        return $this->licPais() === 'CL' ? 0 : 2;
    }

    private function licPais(): string
    {
        return (string) SysconfigHelper::get('licpais', config('app.licpais', 'AR'));
    }

    private function esSecontur(): bool
    {
        return SysconfigHelper::get('licencia') === 'witwan_secontur';
    }

    private function monedaBasica(): string
    {
        $moneda = Moneda::where('moneda_basica', 'S')->first();
        return $moneda?->moneda_id ?? 'ARS';
    }

    private function conexionExiste(string $conexion): bool
    {
        return !empty(config("database.connections.{$conexion}"));
    }

    /**
     * Convierte fechas dd/mm/YYYY (formato del front CI) a Carbon.
     * Acepta también fechas ya en formato ISO.
     */
    private function parseFecha($valor): ?Carbon
    {
        if (empty($valor)) {
            return null;
        }
        if ($valor instanceof Carbon) {
            return $valor;
        }
        if (is_string($valor) && preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $valor)) {
            return Carbon::createFromFormat('d/m/Y', $valor)->startOfDay();
        }
        return Carbon::parse($valor);
    }
}
