<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Recibo;
use App\Models\Movimiento;
use App\Models\Cliente;
use App\Models\Formapago;
use App\Models\Plancuentum;
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
                'forma_pago_id' => 'nullable|integer|exists:formapago,formapago_id',
                'observaciones' => 'nullable|string|max:500'
            ]);

            // Iniciar transacción
            DB::beginTransaction();

            // Obtener datos del cliente
            $cliente = Cliente::findOrFail($validatedData['cliente_id']);

            // Obtener forma de pago (efectivo por defecto)
            $formaPago = $this->getFormaPago($validatedData['forma_pago_id'] ?? null);

            // Obtener cuentas contables necesarias
            $cuentaRecibos = $this->getCuentaRecibos();

            // 1. Crear la reserva
            $reserva = $this->createReserva($cliente, $validatedData);

            // 2. Crear el servicio ANT
            $servicio = $this->createServicioAnticipo($reserva, $validatedData);

            // 3. Crear el recibo
            $recibo = $this->createRecibo($cliente, $validatedData);

            // 4. Crear movimientos contables
            $movimientos = $this->createMovimientosContables(
                $recibo,
                $cuentaRecibos,
                $formaPago,
                $validatedData
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
                        'forma_pago' => $formaPago->formapago_nombre
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
    private function createReserva(Cliente $cliente, array $data): Reserva
    {
        $userId = Auth::id() ?? 1; // Usuario por defecto si no hay autenticación
        $fechaAlta = now();
        $fechaVencimiento = $fechaAlta->copy()->addMonths(6); // 6 meses de vencimiento por defecto

        $reservaData = [
            'fk_cliente_id' => $cliente->cliente_id,
            'cliente_usuario' => $cliente->cliente_id,
            'facturar_a' => $cliente->cliente_id,
            'fk_sistema_id' => 1, // Sistema por defecto
            'fk_usuario_id' => $userId,
            'agente' => $userId,
            'fk_filestatus_id' => 'ACT', // Activo
            'fecha_alta' => $fechaAlta,
            'fecha_vencimiento' => $fechaVencimiento,
            'regdate' => $fechaAlta,
            'um' => $fechaAlta,
            'umu' => $userId,
            'codigo' => $this->generateReservaCode(),
            'tipocodigo' => 'ANT',
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
            'extra3' => 0
        ];

        return Reserva::create($reservaData);
    }

    /**
     * Crear el servicio de anticipo
     */
    private function createServicioAnticipo(Reserva $reserva, array $data): Servicio
    {
        $userId = Auth::id() ?? 1;
        $fechaServicio = now();

        $servicioData = [
            'servicio_nombre' => 'ANTICIPO',
            'fk_reserva_id' => $reserva->reserva_id,
            'fk_tipoproducto_id' => 'ANT',
            'fk_producto_id' => null, // No hay producto específico para anticipos
            'fk_proveedor_id' => null,
            'fk_prestador_id' => null,
            'fk_ciudad_id' => null,
            'vigencia_ini' => $fechaServicio,
            'vigencia_fin' => $fechaServicio,
            'adultos' => 0,
            'menores' => 0,
            'juniors' => 0,
            'infante' => 0,
            'jubilado' => 0,
            'fk_tarifacategoria_id' => null,
            'fk_regimen_id' => null,
            'fk_base_id' => null,
            'status' => 'OK',
            'moneda_costo' => $data['moneda'],
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
    private function createRecibo(Cliente $cliente, array $data): Recibo
    {
        $userId = Auth::id() ?? 1;
        $fechaRecibo = now();

        $reciboData = [
            'recibo_tipo' => 'ANT',
            'recibo_nro' => $this->generateReciboNumber(),
            'fecha' => $fechaRecibo,
            'fk_cliente_id' => $cliente->cliente_id,
            'fk_moneda_id' => $data['moneda'],
            'monto' => $data['monto'],
            'fk_usuario_id' => $userId,
            'statusrecibo' => 'ACT',
            'actualiza' => 1,
            'observaciones' => $data['observaciones'] ?? 'Anticipo',
            'automatico' => 1
        ];

        return Recibo::create($reciboData);
    }

    /**
     * Crear los movimientos contables (2 líneas)
     */
    private function createMovimientosContables(
        Recibo $recibo,
        Plancuentum $cuentaRecibos,
        Formapago $formaPago,
        array $data
    ): array {
        $userId = Auth::id() ?? 1;
        $fecha = now();
        $movimientos = [];

        // Línea 1: Débito a cuenta de recibos
        $movimiento1Data = [
            'fk_asientocontable_id' => null,
            'statusmovimiento' => 'ACT',
            'fk_file_id' => null,
            'fk_plancuenta_id' => $cuentaRecibos->plancuenta_id,
            'fk_moneda_id' => $data['moneda'],
            'cuenta_debito' => 1, // Débito
            'cuenta_credito' => 0,
            'cotizacion_moneda' => 1.0,
            'monto' => $data['monto'],
            'montofinal' => $data['monto'],
            'tipo' => 'REC',
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
            'nrodocumento' => $recibo->recibo_nro,
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

        // Línea 2: Crédito a forma de pago
        $movimiento2Data = [
            'fk_asientocontable_id' => null,
            'statusmovimiento' => 'ACT',
            'fk_file_id' => null,
            'fk_plancuenta_id' => $formaPago->fk_plancuenta_id,
            'fk_moneda_id' => $data['moneda'],
            'cuenta_debito' => 0,
            'cuenta_credito' => 1, // Crédito
            'cotizacion_moneda' => 1.0,
            'monto' => $data['monto'],
            'montofinal' => $data['monto'],
            'tipo' => 'REC',
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
            'descripcion' => 'Anticipo por ' . $formaPago->formapago_nombre . ' - ' . ($data['observaciones'] ?? 'Sin observaciones'),
            'banco' => '',
            'nrodocumento' => $recibo->recibo_nro,
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
     * Obtener forma de pago (efectivo por defecto)
     */
    private function getFormaPago(?int $formaPagoId): Formapago
    {
        if ($formaPagoId) {
            $formaPago = Formapago::find($formaPagoId);
            if ($formaPago) {
                return $formaPago;
            }
        }

        // Buscar forma de pago "Efectivo" por defecto
        $efectivo = Formapago::where('formapago_nombre', 'LIKE', '%efectivo%')
            ->orWhere('formapago_nombre', 'LIKE', '%cash%')
            ->first();

        if ($efectivo) {
            return $efectivo;
        }

        // Si no existe, usar la primera forma de pago disponible
        $primera = Formapago::first();
        if ($primera) {
            return $primera;
        }

        throw new \Exception('No se encontró ninguna forma de pago configurada en el sistema');
    }

    /**
     * Obtener cuenta contable para recibos
     */
    private function getCuentaRecibos(): Plancuentum
    {
        // Buscar cuenta de recibos
        $cuentaRecibos = Plancuentum::where('plancuenta_nombre', 'LIKE', '%recibo%')
            ->orWhere('plancuenta_nombre', 'LIKE', '%anticipo%')
            ->orWhere('plancuenta_codigo', 'LIKE', '%1101%') // Código típico para cuentas por cobrar
            ->first();

        if ($cuentaRecibos) {
            return $cuentaRecibos;
        }

        throw new \Exception('No se encontró cuenta contable para recibos. Configure una cuenta con "recibos" en el nombre.');
    }

    /**
     * Generar código único para reserva
     */
    private function generateReservaCode(): int
    {
        $lastReserva = Reserva::orderBy('reserva_id', 'desc')->first();
        return $lastReserva ? $lastReserva->codigo + 1 : 1000;
    }

    /**
     * Generar número único para recibo
     */
    private function generateReciboNumber(): string
    {
        $prefix = 'ANT';
        $year = date('Y');
        $lastRecibo = Recibo::where('recibo_nro', 'LIKE', $prefix . $year . '%')
            ->orderBy('recibo_id', 'desc')
            ->first();

        if ($lastRecibo) {
            $lastNumber = (int) substr($lastRecibo->recibo_nro, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}