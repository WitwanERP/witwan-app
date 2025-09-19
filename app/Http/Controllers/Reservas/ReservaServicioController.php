<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Cliente;
use App\Models\Usuario;
use App\Models\Filestatus;
use App\Models\Tipoproducto;
use App\Helpers\PermisoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReservaServicioController extends Controller
{
    /**
     * Crear una nueva reserva con servicios
     */
    public function store(Request $request)
    {
        try {
            // Validar permisos básicos
            if (!PermisoHelper::tienePermiso(0, 'CREATE')) {
                return response()->json([
                    'message' => 'No tiene permisos para crear reservas',
                    'errors' => ['permission' => ['Acceso denegado']]
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                // Campos obligatorios para la reserva
                'fk_cliente_id' => 'required|integer|exists:cliente,cliente_id',
                'fk_sistemaaplicacion_id' => 'nullable|integer',
                'fk_sistema_id' => 'required|integer',
                'fecha_alta' => 'required|date',
                'titular_nombre' => 'required|string|max:255',
                'titular_apellido' => 'required|string|max:255',
                'fk_moneda_id' => 'required|string',
                'fk_filestatus_id' => 'nullable|string|in:RQ,CO,CL,CA,DV',
                'agente' => 'nullable|integer',

                // Servicios (opcional para reserva vacía)
                'servicios' => 'nullable|array',
                'servicios.*.servicio_nombre' => 'required_with:servicios|string|max:255',
                'servicios.*.fk_tipoproducto_id' => 'required_with:servicios|string',
                'servicios.*.fk_ciudad_id' => 'required_with:servicios|integer',
                'servicios.*.fk_proveedor_id' => 'required_with:servicios|integer',
                'servicios.*.adultos' => 'nullable|integer|min:0',
                'servicios.*.menores' => 'nullable|integer|min:0',
                'servicios.*.infante' => 'nullable|integer|min:0',
                'servicios.*.jubilado' => 'nullable|integer|min:0',
                'servicios.*.vigencia_ini' => 'required_with:servicios|date',
                'servicios.*.vigencia_fin' => 'nullable|date',
                'servicios.*.total' => 'required_with:servicios|numeric|min:0',
                'servicios.*.costo' => 'nullable|numeric|min:0',
                'servicios.*.costo_exento' => 'nullable|numeric|min:0',
                'servicios.*.costo_10' => 'nullable|numeric|min:0',
                'servicios.*.costo_afecto' => 'nullable|numeric|min:0',
                'servicios.*.costo_nocomputable' => 'nullable|numeric|min:0',
                'servicios.*.status' => 'nullable|string|in:RQ,CO,CL,CA,DV',
                'servicios.*.cotcosto' => 'nullable|numeric|min:0',
                'servicios.*.cotventa' => 'nullable|numeric|min:0',
                'servicios.*.iva' => 'nullable|numeric|min:0',
                'servicios.*.iva_costo' => 'nullable|numeric|min:0',
                'servicios.*.fk_moneda_id' => 'nullable|string|exists:moneda,moneda_id',

                // Opción para crear reserva vacía con anticipo
                'crear_reserva_vacia' => 'nullable|boolean',
                'anticipo_monto' => 'required_if:crear_reserva_vacia,true|nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validar permisos específicos del cliente
            if (!$this->usuarioPuedeAccederCliente($request->fk_cliente_id)) {
                return response()->json([
                    'message' => 'No tiene permisos para trabajar con este cliente',
                    'errors' => ['fk_cliente_id' => ['Acceso denegado a este cliente']]
                ], 403);
            }

            return DB::transaction(function () use ($request) {
                // Calcular el agente si no viene
                $agente = $request->agente;
                if (!$agente) {
                    $cliente = Cliente::find($request->fk_cliente_id);
                    $agente = $cliente->fk_usuario_vendedor ?? $request->fk_usuario_id;
                }


                // Calcular o asignar status
                $fk_filestatus_id = $request->fk_filestatus_id ?? $this->calcularStatusInicial();

                // Preparar datos de la reserva
                $reservaData = [
                    'fk_cliente_id' => $request->fk_cliente_id,
                    'fk_sistema_id' => $request->fk_sistema_id,
                    'fk_sistemaaplicacion_id' => $request->fk_sistemaaplicacion_id ?? $request->fk_sistema_id,
                    'fk_usuario_id' => $request->fk_usuario_id,
                    'agente' => $agente,
                    'fecha_alta' => $request->fecha_alta,
                    'titular_nombre' => $request->titular_nombre,
                    'titular_apellido' => $request->titular_apellido,
                    'fk_moneda_id' => $request->fk_moneda_id,
                    'fk_filestatus_id' => $fk_filestatus_id,
                    'regdate' => now(),
                    'total' => 0, // Se calculará después
                    'totalservicios' => 0,
                    'renta' => 0,
                    'costo' => 0,
                    'comision' => 0,
                    'iva' => 0
                ];

                // Si no viene fk_usuario_id, usar el autenticado
                if (!$request->fk_usuario_id) {
                    $reservaData['fk_usuario_id'] = auth()->id();
                }
                $reservaData['regdate'] = now();
                $reservaData['um'] = now();


                // Agregar campos opcionales
                $optionalFields = [
                    'titular_email',
                    'titular_celular',
                    'observaciones',
                    'observaciones_publicas',
                    'fk_identidadfiscal_id'
                ];

                foreach ($optionalFields as $field) {
                    if ($request->has($field)) {
                        $reservaData[$field] = $request->$field;
                    }
                }

                // Crear la reserva
                $reserva = Reserva::create($reservaData);

                $serviciosCreados = [];
                $totalReserva = 0;
                $totalCosto = 0;
                $totalRenta = 0;
                $totalComision = 0;
                $totalIva = 0;

                // Si es reserva vacía, crear servicio de anticipo
                if ($request->crear_reserva_vacia && $request->anticipo_monto > 0) {
                    $servicioAnticipo = $this->crearServicioAnticipo(
                        $reserva->reserva_id,
                        $request->anticipo_monto,
                        $request->fk_moneda_id
                    );
                    $serviciosCreados[] = $servicioAnticipo;
                    $totalReserva += $request->anticipo_monto;
                }

                // Crear servicios adicionales si se proporcionan
                if ($request->has('servicios') && is_array($request->servicios)) {
                    foreach ($request->servicios as $servicioData) {
                        $servicio = $this->crearServicio($reserva->reserva_id, $servicioData);
                        $serviciosCreados[] = $servicio;

                        // Sumar totales
                        $totalReserva += $servicio->total;
                        $totalCosto += $servicio->costo;
                        $totalRenta += $servicio->renta;
                        $totalComision += $servicio->comision;
                        $totalIva += $servicio->iva;
                    }
                }

                // Actualizar totales de la reserva
                $reserva->update([
                    'total' => $totalReserva,
                    'totalservicios' => $totalReserva,
                    'costo' => $totalCosto,
                    'renta' => $totalRenta,
                    'comision' => $totalComision,
                    'iva' => $totalIva
                ]);

                return response()->json([
                    'message' => 'Reserva creada exitosamente',
                    'reserva' => $reserva->load(['servicios', 'cliente']),
                    'servicios' => $serviciosCreados
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una reserva y sus servicios
     */
    public function update(Request $request, $id)
    {
        try {
            // Validar permisos básicos
            if (!PermisoHelper::tienePermiso(0, 'UPDATE')) {
                return response()->json([
                    'message' => 'No tiene permisos para actualizar reservas',
                    'errors' => ['permission' => ['Acceso denegado']]
                ], 403);
            }

            $reserva = Reserva::findOrFail($id);

            // Validar permisos del cliente existente
            if (!$this->usuarioPuedeAccederCliente($reserva->fk_cliente_id)) {
                return response()->json([
                    'message' => 'No tiene permisos para trabajar con esta reserva',
                    'errors' => ['permission' => ['Acceso denegado a esta reserva']]
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'fk_cliente_id' => 'nullable|integer|exists:cliente,cliente_id',
                'titular_nombre' => 'nullable|string|max:255',
                'titular_apellido' => 'nullable|string|max:255',
                'fk_filestatus_id' => 'nullable|string',
                'observaciones' => 'nullable|string',
                'observaciones_publicas' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            return DB::transaction(function () use ($request, $reserva) {
                $dataToUpdate = $request->only([
                    'fk_cliente_id',
                    'titular_nombre',
                    'titular_apellido',
                    'titular_email',
                    'titular_celular',
                    'observaciones',
                    'observaciones_publicas'
                ]);

                // Si se actualiza el status, aplicar reglas
                if ($request->has('fk_filestatus_id')) {
                    $this->actualizarStatusReservaYServicios(
                        $reserva,
                        $request->fk_filestatus_id
                    );
                    $dataToUpdate['fk_filestatus_id'] = $request->fk_filestatus_id;
                }

                $dataToUpdate['um'] = now();

                $reserva->update($dataToUpdate);

                return response()->json([
                    'message' => 'Reserva actualizada exitosamente',
                    'reserva' => $reserva->load(['servicios', 'cliente'])
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar el status de una reserva
     */
    public function cambiarStatus(Request $request, $id)
    {
        try {
            $reserva = Reserva::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'fk_filestatus_id' => 'required|string',
                'motivo' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            return DB::transaction(function () use ($request, $reserva) {
                $this->actualizarStatusReservaYServicios(
                    $reserva,
                    $request->fk_filestatus_id,
                    $request->motivo
                );

                $reserva->update([
                    'fk_filestatus_id' => $request->fk_filestatus_id,
                    'um' => now()
                ]);

                return response()->json([
                    'message' => 'Status actualizado exitosamente',
                    'reserva' => $reserva->load(['servicios'])
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una reserva con sus servicios
     */
    public function show($id)
    {
        try {
            $reserva = Reserva::with([
                'servicios',
                'cliente',
                'usuario',
                'moneda'
            ])->findOrFail($id);

            return response()->json([
                'reserva' => $reserva
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }
    }

    /**
     * Crear servicio de anticipo para reserva vacía
     */
    private function crearServicioAnticipo($reservaId, $monto, $monedaId)
    {
        return Servicio::create([
            'servicio_nombre' => 'ANTICIPO',
            'fk_reserva_id' => $reservaId,
            'fk_tipoproducto_id' => $this->getTipoProductoAnticipo(),
            'fk_moneda_id' => $monedaId,
            'total' => $monto,
            'totalservicio' => $monto,
            'status' => 'OK',
            'vigencia_ini' => now(),
            'vigencia_fin' => now(),
            'adultos' => 0,
            'menores' => 0,
            'infante' => 0,
            'jubilado' => 0,
            'costo' => 0,
            'costo_exento' => 0,
            'costo_10' => 0,
            'costo_afecto' => 0,
            'costo_nocomputable' => 0,
            'cotcosto' => 1,
            'cotventa' => 1,
            'renta' => $monto,
            'iva_costo' => 0,
            'iva' => 0,
            'regdate' => now()
        ]);
    }

    /**
     * Crear un servicio con cálculos automáticos
     */
    private function crearServicio($reservaId, $servicioData)
    {
        // Si vigencia_fin es null, usar vigencia_ini
        if (empty($servicioData['vigencia_fin'])) {
            $servicioData['vigencia_fin'] = $servicioData['vigencia_ini'];
        }

        // Calcular cotizaciones (ejemplo simplificado)
        $cotCosto = $servicioData['cotcosto'] ?? 1;
        $cotVenta = $servicioData['cotventa'] ?? 1;

        // Calcular renta (ejemplo: total - costo)
        $costo = $servicioData['costo'] ?? 0;
        $total = $servicioData['total'];
        $renta = $total - $costo;

        // Calcular IVA (ejemplo simplificado)
        $iva = $servicioData['iva'] ?? ($total * 0.21); // 21% IVA por defecto
        $ivaCosto = $servicioData['iva_costo'] ?? ($costo * 0.21);

        $servicioCrear = [
            'servicio_nombre' => $servicioData['servicio_nombre'],
            'fk_reserva_id' => $reservaId,
            'fk_tipoproducto_id' => $servicioData['fk_tipoproducto_id'],
            'fk_ciudad_id' => $servicioData['fk_ciudad_id'],
            'fk_proveedor_id' => $servicioData['fk_proveedor_id'],
            'fk_prestador_id' => $servicioData['fk_proveedor_id'], // Mismo que proveedor por defecto
            'vigencia_ini' => $servicioData['vigencia_ini'],
            'vigencia_fin' => $servicioData['vigencia_fin'],
            'total' => $total,
            'totalservicio' => $total,
            'costo' => $costo,
            'costo_exento' => $servicioData['costo_exento'] ?? 0,
            'costo_10' => $servicioData['costo_10'] ?? 0,
            'costo_afecto' => $servicioData['costo_afecto'] ?? 0,
            'costo_nocomputable' => $servicioData['costo_nocomputable'] ?? 0,
            'renta' => $renta,
            'iva' => $iva,
            'iva_costo' => $ivaCosto,
            'cotcosto' => $cotCosto,
            'cotventa' => $cotVenta,
            'adultos' => $servicioData['adultos'] ?? 0,
            'menores' => $servicioData['menores'] ?? 0,
            'infante' => $servicioData['infante'] ?? 0,
            'jubilado' => $servicioData['jubilado'] ?? 0,
            'fk_moneda_id' => $servicioData['fk_moneda_id'] ?? 'USD',
            'moneda_costo' => $servicioData['moneda_costo'] ?? 'USD',
            'status' => 'OK',
            'regdate' => now()
        ];

        // Agregar campos opcionales
        $optionalFields = [
            'fk_producto_id',
            'info',
            'comentarios',
            'nro_confirmacion',
            'mail_proveedor',
            'paxes'
        ];

        foreach ($optionalFields as $field) {
            if (isset($servicioData[$field])) {
                $servicioCrear[$field] = $servicioData[$field];
            }
        }

        return Servicio::create($servicioCrear);
    }

    /**
     * Actualizar status de reserva y aplicar reglas a servicios
     */
    private function actualizarStatusReservaYServicios($reserva, $nuevoStatus, $motivo = null)
    {
        // Reglas de status (ejemplo - ajustar según tu lógica de negocio)
        $reglasStatus = [
            'CONFIRMADA' => ['status_servicios' => 'OK'],
            'CANCELADA' => ['status_servicios' => 'XX'],
            'PENDIENTE' => ['status_servicios' => 'RQ'],
            'VENCIDA' => ['status_servicios' => 'XX']
        ];

        if (isset($reglasStatus[$nuevoStatus])) {
            $statusServicios = $reglasStatus[$nuevoStatus]['status_servicios'];

            // Actualizar todos los servicios de la reserva
            Servicio::where('fk_reserva_id', $reserva->reserva_id)
                ->update(['status' => $statusServicios]);
        }

        // Log del cambio de status si se requiere
        if ($motivo) {
            // Aquí podrías agregar un registro en historial
            // Historial::create([...]);
        }
    }

    /**
     * Obtener el ID del tipo de producto para anticipo
     */
    private function getTipoProductoAnticipo()
    {
        // Buscar el tipo de producto "ANTICIPO" o crear uno por defecto
        return 'ANTIC'; // Ajustar según tu tabla tipoproducto
    }

    /**
     * Calcular status inicial de la reserva
     */
    private function calcularStatusInicial()
    {
        return 'PENDIENTE'; // Status por defecto, ajustar según lógica de negocio
    }

    /**
     * Recalcular totales de una reserva basado en sus servicios
     */
    public function recalcularTotales($id)
    {
        try {
            $reserva = Reserva::findOrFail($id);

            return DB::transaction(function () use ($reserva) {
                $servicios = Servicio::where('fk_reserva_id', $reserva->reserva_id)->get();

                $totales = $servicios->reduce(function ($carry, $servicio) {
                    return [
                        'total' => $carry['total'] + $servicio->total,
                        'costo' => $carry['costo'] + $servicio->costo,
                        'renta' => $carry['renta'] + $servicio->renta,
                        'comision' => $carry['comision'] + $servicio->comision,
                        'iva' => $carry['iva'] + $servicio->iva
                    ];
                }, ['total' => 0, 'costo' => 0, 'renta' => 0, 'comision' => 0, 'iva' => 0]);

                $reserva->update([
                    'total' => $totales['total'],
                    'totalservicios' => $totales['total'],
                    'costo' => $totales['costo'],
                    'renta' => $totales['renta'],
                    'comision' => $totales['comision'],
                    'iva' => $totales['iva'],
                    'um' => now()
                ]);

                return response()->json([
                    'message' => 'Totales recalculados exitosamente',
                    'reserva' => $reserva->load(['servicios'])
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recalcular totales',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function usuarioPuedeAccederCliente($fk_cliente_id)
    {
        return true;
        $usuario = Auth::user();

        // Si el usuario es admin, puede acceder a todos los clientes
        if ($usuario->es_admin) {
            return true;
        }

        // Verificar si el cliente pertenece al usuario o a su grupo
        $cliente = Cliente::find($fk_cliente_id);
        if (!$cliente) {
            return false;
        }

        if ($cliente->fk_usuario_vendedor === $usuario->usuario_id) {
            return true;
        }

        // Aquí podrías agregar lógica adicional para grupos o jerarquías
        return false;
    }
}
