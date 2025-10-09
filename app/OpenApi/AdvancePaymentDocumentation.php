<?php

namespace App\OpenApi;

/**
 * @OA\Post(
 *     path="/advance-payment/create",
 *     operationId="advancePaymentCreate",
 *     tags={"Advance Payment"},
 *     summary="Crear anticipo con reserva y recibo",
 *     description="Crea un file de anticipo generando automáticamente una reserva, servicio tipo ANT, recibo y movimientos contables correspondientes",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"cliente_id", "monto", "moneda"},
 *             @OA\Property(
 *                 property="cliente_id",
 *                 type="integer",
 *                 description="ID del cliente para el anticipo",
 *                 example=123
 *             ),
 *             @OA\Property(
 *                 property="monto",
 *                 type="number",
 *                 format="float",
 *                 description="Monto del anticipo",
 *                 example=500.00
 *             ),
 *             @OA\Property(
 *                 property="moneda",
 *                 type="string",
 *                 description="Código de moneda (USD, EUR, etc.)",
 *                 example="USD"
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Anticipo creado exitosamente",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="File de anticipo creado exitosamente"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="reserva_id", type="integer", example=456),
 *                 @OA\Property(property="servicio_id", type="integer", example=789),
 *                 @OA\Property(property="recibo_id", type="integer", example=321),
 *                 @OA\Property(
 *                     property="movimientos",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     example={101, 102}
 *                 ),
 *                 @OA\Property(
 *                     property="resumen",
 *                     type="object",
 *                     @OA\Property(property="cliente", type="string", example="Juan Pérez"),
 *                     @OA\Property(property="monto", type="number", example=500.00),
 *                     @OA\Property(property="moneda", type="string", example="USD"),
 *                     @OA\Property(property="fecha", type="string", format="date", example="2025-01-15"),
 *                     @OA\Property(property="forma_pago", type="string", example="Efectivo")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Error de validación",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Error de validación"),
 *             @OA\Property(
 *                 property="errors",
 *                 type="object",
 *                 @OA\Property(
 *                     property="cliente_id",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     example={"El campo cliente_id es obligatorio"}
 *                 ),
 *                 @OA\Property(
 *                     property="monto",
 *                     type="array",
 *                     @OA\Items(type="string"),
 *                     example={"El monto debe ser mayor a 0"}
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Cliente no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Cliente no encontrado")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Error interno del servidor"),
 *             @OA\Property(property="error", type="string", example="Descripción del error técnico")
 *         )
 *     )
 * )
 */
class AdvancePaymentDocumentation
{
    // Esta clase solo contiene la documentación OpenAPI
    // No necesita implementación de métodos
}