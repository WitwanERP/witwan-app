<?php

namespace App\Services;

use App\Models\Colaevento;
use App\Models\Servicio;
use Illuminate\Support\Facades\Log;
use Exception;

class QueueEventService
{
    protected LicenseSyncService $licenseSyncService;

    public function __construct(LicenseSyncService $licenseSyncService)
    {
        $this->licenseSyncService = $licenseSyncService;
    }

    /**
     * Procesa eventos pendientes en la cola
     *
     * @param int $limit Número máximo de eventos a procesar
     * @param string $frecuencia Tipo de frecuencia: minuto, horario, nocturno
     * @return array Estadísticas del procesamiento
     */
    public function processPendingEvents(int $limit = 1, string $frecuencia = 'minuto'): array
    {
        $stats = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'success' => [],
        ];

        $eventos = Colaevento::where('estado', 'pendiente')
            //->where('frecuencia', $frecuencia)
            ->limit($limit)
            ->get();

        foreach ($eventos as $evento) {
            try {
                $this->processEvent($evento);
                $stats['processed']++;
                $stats['success'][] = [
                    'evento_id' => $evento->colaevento_id,
                    'tipo' => $evento->tipo_evento,
                    'modelo' => $evento->modelo,
                    'id_relacionado' => $evento->id_relacionado,
                ];
            } catch (Exception $e) {
                $this->markEventAsError($evento, $e);
                $stats['failed']++;
                $stats['errors'][] = [
                    'evento_id' => $evento->colaevento_id,
                    'tipo' => $evento->tipo_evento,
                    'modelo' => $evento->modelo,
                    'id_relacionado' => $evento->id_relacionado,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => $this->getShortTrace($e),
                ];
            }
        }

        return $stats;
    }

    /**
     * Obtiene un trace corto de la excepción (primeras 5 líneas)
     */
    protected function getShortTrace(Exception $e): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        return implode("\n", array_slice($lines, 0, 5));
    }

    /**
     * Procesa un evento individual
     *
     * @param Colaevento $evento
     * @throws Exception
     */
    protected function processEvent(Colaevento $evento): void
    {
        Log::info("Procesando evento {$evento->colaevento_id}", [
            'tipo' => $evento->tipo_evento,
            'modelo' => $evento->modelo,
            'id_relacionado' => $evento->id_relacionado
        ]);

        if ($evento->tipo_evento === 'create') {
            $this->processCreateEvent($evento);
        } else {
            Log::warning("Tipo de evento no soportado: {$evento->tipo_evento}");
        }
    }

    /**
     * Procesa eventos de tipo 'create'
     *
     * @param Colaevento $evento
     * @throws Exception
     */
    protected function processCreateEvent(Colaevento $evento): void
    {
        switch ($evento->modelo) {
            case 'reserva':
                $this->processReservaEvent($evento);
                break;

            case 'factura_automatica':
                $this->processFacturaAutomaticaEvent($evento);
                break;

            default:
                Log::warning("Modelo no soportado: {$evento->modelo}");
        }
    }

    /**
     * Procesa evento de creación de reserva
     *
     * @param Colaevento $evento
     * @throws Exception
     */
    protected function processReservaEvent(Colaevento $evento): void
    {
        $jsonReserva = json_decode($evento->datos, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido en datos del evento: ' . json_last_error_msg());
        }

        // Intentar sincronizar con licencia
        $synced = $this->licenseSyncService->syncReserva($evento->id_relacionado, $jsonReserva);

        if ($synced) {
            $this->markEventAsProcessed($evento);
            Log::info("Reserva {$evento->id_relacionado} sincronizada exitosamente");
        } else {
            // Si no tiene licencia, también marcamos como procesado
            $this->markEventAsProcessed($evento);
            Log::info("Reserva {$evento->id_relacionado} procesada sin sincronización (sin licencia)");
        }
    }

    /**
     * Procesa evento de factura automática
     *
     * @param Colaevento $evento
     * @throws Exception
     */
    protected function processFacturaAutomaticaEvent(Colaevento $evento): void
    {
        $servicios = Servicio::where('fk_reserva_id', $evento->id_relacionado)->get();

        foreach ($servicios as $servicio) {
            // Aquí iría la lógica de facturación automática
            // Por ahora solo registramos que se procesó
            Log::info("Procesando factura automática para servicio {$servicio->servicio_id}");

            // TODO: Implementar lógica de facturación
            // Ejemplo: $this->facturaService->calcularContable($servicio->servicio_id);
        }

        $this->markEventAsProcessed($evento);
        Log::info("Factura automática procesada para reserva {$evento->id_relacionado}");
    }

    /**
     * Marca un evento como procesado
     *
     * @param Colaevento $evento
     */
    protected function markEventAsProcessed(Colaevento $evento): void
    {
        $evento->estado = 'procesado';
        $evento->save();
    }

    /**
     * Marca un evento como error
     *
     * @param Colaevento $evento
     * @param Exception $exception
     */
    protected function markEventAsError(Colaevento $evento, Exception $exception): void
    {
        $evento->estado = 'error';
        $evento->save();

        Log::error("Error procesando evento {$evento->colaevento_id}", [
            'tipo' => $evento->tipo_evento,
            'modelo' => $evento->modelo,
            'id_relacionado' => $evento->id_relacionado,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
