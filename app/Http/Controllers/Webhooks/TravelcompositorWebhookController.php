<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Colaevento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TravelcompositorWebhookController extends Controller
{
    /**
     * Recibe las notificaciones de webhook de Travelcompositor y las encola
     * en colaevento para su procesamiento asíncrono por QueueEventService.
     *
     * El secreto viaja como segmento de URL porque Travelcompositor solo
     * permite configurar una URL (no headers personalizados).
     */
    public function handle(Request $request, string $secret): JsonResponse
    {
        $expectedSecret = config('services.travelcompositor.webhook_secret');

        if (empty($expectedSecret) || ! hash_equals((string) $expectedSecret, $secret)) {
            Log::warning('Webhook Travelcompositor rechazado: secreto inválido');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'timestamp' => 'required|string',
            'type' => 'required|string|in:CREATED,MODIFIED,CANCELED',
            'micrositeId' => 'required|string|max:255',
            'bookingReference' => 'required|string|max:255',
        ]);

        try {
            // No seteamos 'frecuencia' a propósito: esa columna no existe en todos
            // los tenants (migración por-tenant). Si existe, la DB aplica su DEFAULT.
            Colaevento::create([
                'regdate' => now(),
                'tipo_evento' => 'create',          // siempre create; el type real va en datos
                'estado' => 'pendiente',
                'modelo' => 'travelcompositor',
                'id_relacionado' => 0,               // INT NOT NULL; aún no hay reserva_id interno
                'datos' => json_encode($validated),
            ]);
        } catch (Throwable $e) {
            Log::error('Error al encolar webhook Travelcompositor: '.$e->getMessage(), [
                'bookingReference' => $validated['bookingReference'],
                'type' => $validated['type'],
            ]);

            return response()->json(['error' => 'Internal Server Error'], 500);
        }

        return response()->json(['success' => true], 200);
    }
}
