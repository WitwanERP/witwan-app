<?php

namespace App\OpenApi;

/**
 * @OA\Post(
 *     path="/webhooks/travelcompositor",
 *     operationId="travelcompositorWebhook",
 *     tags={"Webhooks"},
 *     summary="Webhook de Travelcompositor",
 *     description="Recibe notificaciones de reservas de Travelcompositor (CREATED/MODIFIED/CANCELED) y las encola en colaevento para procesamiento asíncrono. Endpoint público: el secreto compartido se envía como query (?token=), header (X-Webhook-Token) o segmento opcional de la URL. El 'test push' de cuerpo vacío que envía TC para validar el endpoint responde 200 sin encolar.",
 *
 *     @OA\Parameter(
 *         name="token",
 *         in="query",
 *         required=false,
 *         description="Secreto compartido para validar el origen del webhook",
 *
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"timestamp", "type", "micrositeId", "bookingReference"},
 *
 *             @OA\Property(property="timestamp", type="string", format="date-time", example="2023-11-27T12:48:52"),
 *             @OA\Property(property="type", type="string", enum={"CREATED", "MODIFIED", "CANCELED"}, example="CREATED"),
 *             @OA\Property(property="micrositeId", type="string", example="fake"),
 *             @OA\Property(property="bookingReference", type="string", example="TEST-12345")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Evento encolado correctamente (o test push validado)",
 *
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(property="success", type="boolean", example=true)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Secreto inválido",
 *
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(property="error", type="string", example="Unauthorized")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Payload inválido",
 *
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class TravelcompositorWebhookDocumentation {}
