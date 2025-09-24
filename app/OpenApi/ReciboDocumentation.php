<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="Recibo",
 *     @OA\Property(property="recibo_id", type="integer", example=1),
 *     @OA\Property(property="recibo_tipo", type="string", example="PAGO"),
 *     @OA\Property(property="recibo_nro", type="string", example="REC-00000001"),
 *     @OA\Property(property="fecha", type="string", format="date", example="2025-01-15"),
 *     @OA\Property(property="fk_cliente_id", type="integer", example=123),
 *     @OA\Property(property="fk_moneda_id", type="string", example="USD"),
 *     @OA\Property(property="monto", type="number", format="float", example=5000.00),
 *     @OA\Property(property="statusrecibo", type="string", example="OK"),
 *     @OA\Property(property="observaciones", type="string", example="Pago reservas enero")
 * )
 *
 * @OA\Post(
 *     path="/admin/caja/recibos/process",
 *     operationId="reciboProcess",
 *     tags={"Recibos"},
 *     summary="Procesar recibo con reservas y facturas",
 *     description="Procesa un recibo completo incluyendo asiento contable, relaciones con reservas y facturas",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"fecha", "cliente_id", "moneda_id", "monto_total", "reservas", "movimientos"},
 *             @OA\Property(property="fecha", type="string", format="date", example="2025-01-15"),
 *             @OA\Property(property="cliente_id", type="integer", example=123),
 *             @OA\Property(property="moneda_id", type="string", example="USD"),
 *             @OA\Property(property="monto_total", type="number", format="float", example=5000.00),
 *             @OA\Property(
 *                 property="reservas",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="reserva_id", type="integer", example=456),
 *                     @OA\Property(property="monto", type="number", format="float", example=3000.00)
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="facturas",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="factura_id", type="integer", example=789),
 *                     @OA\Property(property="monto_aplicado", type="number", format="float", example=2000.00)
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="movimientos",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="cuenta_id", type="integer", example=1101),
 *                     @OA\Property(property="tipo", type="string", enum={"debe", "haber"}, example="debe"),
 *                     @OA\Property(property="monto", type="number", format="float", example=5000.00),
 *                     @OA\Property(property="descripcion", type="string", example="Pago en efectivo"),
 *                     @OA\Property(property="banco", type="string", example="Banco Estado"),
 *                     @OA\Property(property="operacion", type="string", example="TRANSFERENCIA")
 *                 )
 *             ),
 *             @OA\Property(property="cotizacion_personalizada", type="number", format="float", example=950.50),
 *             @OA\Property(property="tipo_cambio_general", type="number", format="float", example=900.00),
 *             @OA\Property(property="recibo_tipo", type="string", example="PAGO"),
 *             @OA\Property(property="observaciones", type="string", example="Pago reservas enero 2025")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Recibo procesado exitosamente",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Recibo procesado exitosamente"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="recibo_id", type="integer", example=1),
 *                 @OA\Property(property="recibo_nro", type="string", example="REC-00000001"),
 *                 @OA\Property(property="asiento_id", type="integer", example=1),
 *                 @OA\Property(property="monto_total", type="number", format="float", example=5000.00),
 *                 @OA\Property(property="cotizacion_aplicada", type="number", format="float", example=950.50)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Error en el procesamiento",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Error al procesar el recibo"),
 *             @OA\Property(property="error", type="string", example="El asiento contable no está balanceado")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Error de validación",
 *         @OA\JsonContent(
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class ReciboDocumentation {}