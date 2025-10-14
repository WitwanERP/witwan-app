<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Recibo;
use App\Models\Movimiento;
use App\Models\Cliente;
use App\Models\Plancuenta;
use App\Models\Asientocontable;
use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controller para generar files de anticipo con recibos y movimientos contables
 */
class AdvancePaymentController extends Controller
{
    /**
     * Crear un file de anticipo completo
     */
    public function create(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validatedData = $request->validate([
                'cliente_id' => 'required|integer|exists:cliente,cliente_id',
                'monto' => 'required|numeric|min:0.01',
                'moneda' => 'required|string|size:3',
                'forma_pago_id' => 'required|integer|exists:plancuenta,plancuenta_id',
                'observaciones' => 'nullable|string|max:500',
                'nro_documento' => 'nullable|string|max:100'
            ]);

            // Iniciar transacción
            DB::beginTransaction();

            // Obtener datos del cliente
            $cliente = Cliente::findOrFail($validatedData['cliente_id']);

            // Obtener cotización de la moneda
            $cotizacion = $this->getCurrencyRate($validatedData['moneda'], now());

            // Obtener cuentas contables necesarias
            $cuentaRecibos = $this->getCuentaRecibos();
            $cuentaFormaPago = Plancuenta::findOrFail($validatedData['forma_pago_id']);

            // 0. Crear asiento contable
            $asientoContable = Asientocontable::create(['asientocontable_fecha' => now(), 'debe' => 0, 'haber' => 0]);

            // 1. Crear la reserva
            $reserva = $this->createReserva($cliente, $validatedData, $cotizacion);

            // 2. Crear el servicio ANT
            $servicio = $this->createServicioAnticipo($reserva, $validatedData, $cotizacion);

            // 3. Crear el recibo
            $recibo = $this->createRecibo($cliente, $validatedData, $cotizacion);

            // 4. Crear movimientos contables
            $movimientos = $this->createMovimientosContables(
                $recibo,
                $asientoContable,
                $cuentaRecibos,
                $cuentaFormaPago,
                $validatedData,
                $cotizacion
            );

            // Crear relación en rel_filerecibo
            DB::table('rel_filerecibo')->insert([
                'fk_file_id' => $reserva->reserva_id,
                'fk_recibo_id' => $recibo->recibo_id,
                'fecha' => now()->format('Y-m-d'),
                'fk_moneda_id' => $validatedData['moneda'],
                'monto' => $validatedData['monto']
            ]);

            // Confirmar transacción
            DB::commit();

            // Preparar respuesta
            $response = [
                'success' => true,
                'message' => 'File de anticipo creado exitosamente',
                'data' => [
                    'reserva_id' => $reserva->reserva_id,
                    'servicio_id' => $servicio->servicio_id,
                    'recibo_id' => $recibo->recibo_id,
                    'movimientos' => array_map(fn($mov) => $mov->movimiento_id, $movimientos),
                    'resumen' => [
                        'cliente' => $cliente->cliente_nombre,
                        'monto' => $validatedData['monto'],
                        'moneda' => $validatedData['moneda'],
                        'fecha' => now()->format('Y-m-d'),
                        'cotizacion' => $cotizacion,
                        'asiento_contable_id' => $asientoContable->asientocontable_id
                    ]
                ]
            ];

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            DB::rollback();

            \Log::error('Error creating advance payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar el anticipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear la reserva base
     */
    private function createReserva(Cliente $cliente, array $data, float $cotizacion): Reserva
    {
        $userId = Auth::id() ?? 1; // Usuario por defecto si no hay autenticación
        $fechaAlta = now();
        $fechaVencimiento = $fechaAlta->copy()->addMonths(6); // 6 meses de vencimiento por defecto

        $reservaData = [
            'fk_cliente_id' => $cliente->cliente_id,
            'cliente_usuario' => $cliente->cliente_id,
            'facturar_a' => $cliente->cliente_id,
            'fk_sistema_id' => 2,
            'fk_sistemaaplicacion_id' => 2,
            'fk_usuario_id' => $userId,
            'agente' => $userId,
            'fk_filestatus_id' => 'CO', // los valores de este campo son CO, CL, CA, RQ, DE, DV
            'fecha_alta' => $fechaAlta,
            'fecha_vencimiento' => $fechaVencimiento,
            'regdate' => $fechaAlta,
            'um' => $fechaAlta,
            'umu' => $userId,
            'codigo' => $this->generateReservaCode(),
            'tipocodigo' => 'MA',
            'titular_nombre' => $cliente->cliente_nombre,
            'titular_apellido' => $cliente->cliente_apellido ?? '',
            'titular_email' => $cliente->cliente_email ?? '',
            'titular_celular' => $cliente->cliente_telefono ?? '',
            'cerrada' => 0,
            'autorizado' => 1,
            'observaciones' => $data['observaciones'] ?? 'Anticipo generado automáticamente',
            'observaciones_publicas' => '',
            'fk_moneda_id' => $data['moneda'],
            'total' => $data['monto'],
            'comision' => 0,
            'impuestos' => 0,
            'totalservicios' => $data['monto'],
            'iva' => 0,
            'gastos' => 0,
            'rg_terrestre' => 0,
            'rg_trasnporte' => 0,
            'cobrado' => 0,
            'renta' => 0,
            'costo' => 0,
            'ivacosto' => 0,
            'ajuste' => 0,
            'extra1' => 0,
            'extra2' => 0,
            'extra3' => 0,
            'extra4' => 0,
            'moneda_factura' => $data['moneda'],
            'fk_negocio_id' => 0,
            'promotor' => 0,
            'promotoraereo' => 0,
            'vencimiento_senia' => $fechaVencimiento,
            'areaanalitica' => '',
            'escotizacion' => 0,
            'codigo_externo' => '',
            'mostrarreprogramados' => 0,
            'operativo' => 0
        ];

        return Reserva::create($reservaData);
    }

    /**
     * Crear el servicio de anticipo
     */
    private function createServicioAnticipo(Reserva $reserva, array $data, float $cotizacion): Servicio
    {
        $userId = Auth::id() ?? 1;
        $fechaServicio = now();

        $servicioData = [
            'servicio_nombre' => 'ANTICIPO',
            'fk_reserva_id' => $reserva->reserva_id,
            'fk_tipoproducto_id' => 'ANT',
            'fk_producto_id' => 0, // No hay producto específico para anticipos
            'fk_proveedor_id' => 0,
            'fk_prestador_id' => 0,
            'fk_ciudad_id' => 0,
            'vigencia_ini' => $fechaServicio,
            'vigencia_fin' => $fechaServicio,
            'inicio' => $fechaServicio,
            'adultos' => 1,
            'menores' => 0,
            'juniors' => 0,
            'infante' => 0,
            'jubilado' => 0,
            'fk_tarifacategoria_id' => 0,
            'fk_regimen_id' => 0,
            'fk_base_id' => 0,
            'status' => 'CO', // los valores de este campo son CO, CL, CA, RQ, DE, DV
            'moneda_costo' => $data['moneda'],
            'cotcosto' => $cotizacion,
            'cotventa' => $cotizacion,
            'iva' => 0,
            'fk_moneda_id' => $data['moneda'],
            'impuestos' => 0,
            'comision' => 0,
            'costo' => 0,
            'iva_costo' => 0,
            'total' => $data['monto'],
            'totalservicio' => $data['monto'],
            'rg_terrestre' => 0,
            'rg_aereo' => 0,
            'extra1' => 0,
            'extra2' => 0,
            'extra3' => 0,
            'extra4' => 0,
            'paxes' => '',
            'info' => 'Anticipo de ' . $data['monto'] . ' ' . $data['moneda'],
            'vencimiento_proveedor' => null,
            'nro_confirmacion' => '',
            'mail_proveedor' => '',
            'comentarios' => $data['observaciones'] ?? '',
            'retira_voucher' => '',
            'autoriza_evoucher' => '',
            'texto_voucher' => '',
            'prev_file_id' => null,
            'regdate' => $fechaServicio
        ];

        return Servicio::create($servicioData);
    }

    /**
     * Crear el recibo
     */
    private function createRecibo(Cliente $cliente, array $data, float $cotizacion): Recibo
    {
        $userId = Auth::id() ?? 1;
        $fechaRecibo = now();

        $reciboData = [
            'recibo_tipo' => 'R',
            'recibo_nro' => $this->generateReciboNumber(),
            'fecha' => $fechaRecibo,
            'fk_cliente_id' => $cliente->cliente_id,
            'fk_moneda_id' => $data['moneda'],
            'monto' => $data['monto'],
            'fk_usuario_id' => $userId,
            'statusrecibo' => 'OK',
            'actualiza' => 1,
            'observaciones' => $data['observaciones'] ?? 'Anticipo',
            'automatico' => 1,
            'cotizacion_moneda' => $cotizacion,
            'tipo' => ''
        ];

        return Recibo::create($reciboData);
    }

    /**
     * Crear los movimientos contables (2 líneas)
     */
    private function createMovimientosContables(
        Recibo $recibo,
        Asientocontable $asientoContable,
        Plancuenta $cuentaRecibos,
        Plancuenta $cuentaFormaPago,
        array $data,
        float $cotizacion
    ): array {
        $userId = Auth::id() ?? 1;
        $fecha = now();
        $movimientos = [];
        $nroDocumento = $data['nro_documento'] ?? '';

        // Línea 1: Débito a forma de pago (entrada de dinero)
        $movimiento1Data = [
            'fk_asientocontable_id' => $asientoContable->asientocontable_id,
            'statusmovimiento' => 'OK',
            'fk_file_id' => null,
            'fk_plancuenta_id' => $cuentaFormaPago->plancuenta_id,
            'fk_moneda_id' => $data['moneda'],
            'cuenta_debito' => $cuentaFormaPago->plancuenta_id,
            'cuenta_credito' => 0,
            'cotizacion_moneda' => $cotizacion,
            'monto' => $data['monto'],
            'montofinal' => $data['monto'] * $cotizacion,
            'tipo' => '',
            'fecha' => $fecha,
            'fecha_acreditacion' => $fecha,
            'regdate' => $fecha,
            'fk_usuario_id' => $userId,
            'fk_cliente_id' => $recibo->fk_cliente_id,
            'fk_proveedor_id' => null,
            'fk_factura_id' => null,
            'fk_notacredito_id' => null,
            'fk_notadebito_id' => null,
            'fk_ordenadmin_id' => null,
            'fk_facturaproveedor_id' => null,
            'descripcion' => 'Anticipo recibido - ' . ($data['observaciones'] ?? 'Sin observaciones'),
            'banco' => '',
            'nrodocumento' => $nroDocumento,
            'operacion' => '',
            'fk_recibo_id' => $recibo->recibo_id,
            'porcentajeadministracion' => 0,
            'porcentajereceptivo' => 0,
            'porcentajemayorista' => 0,
            'porcentajeminorista' => 0,
            'porcentajeconsolidador' => 0,
            'fk_movimiento_id' => null,
            'utilizado' => 0,
            'afecta_cobranza' => 1,
            'fk_itemgasto_id' => null,
            'statusdocumento' => 1,
            'relaciones' => '',
            'filtro_cliente' => $recibo->fk_cliente_id,
            'filtro_proveedor' => 0,
            'filtro_documento' => $recibo->recibo_id,
            'filtro_file' => 0,
            'filtro_servicio' => 0,
            'auxiliar' => 0
        ];

        $movimiento1 = Movimiento::create($movimiento1Data);
        $movimientos[] = $movimiento1;

        // Línea 2: Crédito a cuenta de recibos (anticipo del cliente)
        $movimiento2Data = [
            'fk_asientocontable_id' => $asientoContable->asientocontable_id,
            'statusmovimiento' => 'OK',
            'fk_file_id' => null,
            'fk_plancuenta_id' => $cuentaFormaPago->plancuenta_id,
            'fk_moneda_id' => $data['moneda'],
            'cuenta_debito' => $cuentaFormaPago->plancuenta_id,
            'cuenta_credito' => $cuentaRecibos->plancuenta_id,
            'cotizacion_moneda' => $cotizacion,
            'monto' => $data['monto'],
            'montofinal' => $data['monto'] * $cotizacion,
            'tipo' => '',
            'fecha' => $fecha,
            'fecha_acreditacion' => $fecha,
            'regdate' => $fecha,
            'fk_usuario_id' => $userId,
            'fk_cliente_id' => $recibo->fk_cliente_id,
            'fk_proveedor_id' => null,
            'fk_factura_id' => null,
            'fk_notacredito_id' => null,
            'fk_notadebito_id' => null,
            'fk_ordenadmin_id' => null,
            'fk_facturaproveedor_id' => null,
            'descripcion' => 'Anticipo del cliente - ' . ($data['observaciones'] ?? 'Sin observaciones'),
            'banco' => '',
            'nrodocumento' => $nroDocumento,
            'operacion' => '',
            'fk_recibo_id' => $recibo->recibo_id,
            'porcentajeadministracion' => 0,
            'porcentajereceptivo' => 0,
            'porcentajemayorista' => 0,
            'porcentajeminorista' => 0,
            'porcentajeconsolidador' => 0,
            'fk_movimiento_id' => null,
            'utilizado' => 0,
            'afecta_cobranza' => 1,
            'fk_itemgasto_id' => null,
            'statusdocumento' => 1,
            'relaciones' => '',
            'filtro_cliente' => $recibo->fk_cliente_id,
            'filtro_proveedor' => 0,
            'filtro_documento' => $recibo->recibo_id,
            'filtro_file' => 0,
            'filtro_servicio' => 0,
            'auxiliar' => 0
        ];

        $movimiento2 = Movimiento::create($movimiento2Data);
        $movimientos[] = $movimiento2;

        return $movimientos;
    }

    /**
     * Obtener cuenta contable para recibos desde sysconfig
     */
    private function getCuentaRecibos(): Plancuenta
    {
        // Obtener ID de cuenta desde sysconfig
        $cuentaRecibosCfg = DB::table('sysconfig')
            ->where('sysconfig_key', 'cuentarecibos')
            ->first();

        if (!$cuentaRecibosCfg || empty($cuentaRecibosCfg->sysconfig_value)) {
            throw new \Exception('No se encontró configuración de cuenta de recibos en sysconfig (key: cuentarecibos)');
        }

        $cuentaId = (int) $cuentaRecibosCfg->sysconfig_value;
        $cuenta = Plancuenta::find($cuentaId);

        if (!$cuenta) {
            throw new \Exception("La cuenta de recibos configurada (ID: $cuentaId) no existe en plancuenta");
        }

        return $cuenta;
    }

    /**
     * Generar código único para reserva
     */
    private function generateReservaCode(): int
    {
        $maxCodigo = Reserva::max('codigo');
        return $maxCodigo ? $maxCodigo + 1 : 1000;
    }

    /**
     * Obtener cotización de moneda
     */
    private function getCurrencyRate(string $moneda, $fecha): float
    {
        $cotizacion = Cotizacion::where('cotizacion_moneda', $moneda)
            ->whereDate('cotizacion_fecha', '<=', $fecha)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        if ($cotizacion) {
            return $cotizacion->cotizacion_relacion;
        }

        // Fallback: buscar la cotización más reciente
        $cotizacion = Cotizacion::where('cotizacion_moneda', $moneda)
            ->orderBy('cotizacion_fecha', 'desc')
            ->first();

        return $cotizacion ? $cotizacion->cotizacion_relacion : 1.0;
    }

    /**
     * Generar número único para recibo
     */
    private function generateReciboNumber(): string
    {
        // Obtener el último número de recibo (numeración global única)
        $lastRecibo = Recibo::orderBy('recibo_id', 'desc')->first();

        if ($lastRecibo && is_numeric($lastRecibo->recibo_nro)) {
            return (string) ((int) $lastRecibo->recibo_nro + 1);
        }

        // Si no hay recibos o el último no es numérico, buscar el máximo valor numérico
        $maxNumerico = Recibo::whereRaw('recibo_nro REGEXP "^[0-9]+$"')
            ->orderByRaw('CAST(recibo_nro AS UNSIGNED) DESC')
            ->first();

        if ($maxNumerico) {
            return (string) ((int) $maxNumerico->recibo_nro + 1);
        }

        // Si no hay ningún recibo numérico, empezar desde 1
        return '1';
    }
}
