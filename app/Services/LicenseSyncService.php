<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Pnraereo;
use App\Models\ServicioNomina;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LicenseSyncService
{
    /**
     * Sincroniza una reserva con una licencia externa
     *
     * @param int $reservaId
     * @param array $jsonReserva
     * @return bool
     * @throws Exception
     */
    public function syncReserva(int $reservaId, array $jsonReserva): bool
    {
        // Validar que tenga licencia
        if (!isset($jsonReserva['licencia']) || intval($jsonReserva['licencia']) <= 0) {
            Log::info("Reserva {$reservaId} sin licencia asociada, omitiendo sincronización");
            return false;
        }

        $licenciaId = intval($jsonReserva['licencia']);

        // Obtener datos de la reserva
        $reserva = Reserva::findOrFail($reservaId);
        $servicios = Servicio::where('fk_reserva_id', $reservaId)->get();

        // Construir objeto para enviar
        $objeto = [
            'proveedor' => config('app.licencia_id', env('LICENCIA')),
            'reserva' => $reserva->toArray(),
            'servicios' => []
        ];

        // Procesar cada servicio
        foreach ($servicios as $servicio) {
            $aereorelacionado = Pnraereo::where('fk_ocupacion_id', $servicio->servicio_id)->first();

            $pasajeros = ServicioNomina::where('fk_servicio_id', $servicio->servicio_id)
                ->get()
                ->map(fn($p) => $p->toArray())
                ->toArray();

            $objetoServicio = $servicio->toArray();

            // Calcular venta_total y venta_neta para el receptor
            // venta_total = total (precio de venta del proveedor)
            // venta_iva = iva + iva_costo (IVA total de la venta del proveedor)
            // venta_neta = venta_total - venta_iva
            $objetoServicio['venta_total'] = $servicio->total;
            $objetoServicio['venta_iva'] = $servicio->iva + $servicio->iva_costo;
            $objetoServicio['venta_neta'] = $servicio->total - $objetoServicio['venta_iva'];

            $objetoServicio['pnraereo'] = $aereorelacionado ? $aereorelacionado->toArray() : null;
            $objetoServicio['pasajeros'] = $pasajeros;

            $objeto['servicios'][] = $objetoServicio;
        }

        // Obtener información de la licencia
        $licencia = $this->getLicenciaInfo($licenciaId);

        if (!$licencia) {
            throw new Exception("Licencia {$licenciaId} no encontrada");
        }

        // Enviar a la API de la licencia
        return $this->sendToLicense($licencia, $objeto);
    }

    /**
     * Obtiene información de una licencia
     *
     * @param int $licenciaId
     * @return object|null
     */
    protected function getLicenciaInfo(int $licenciaId): ?object
    {
        $dbLicencia = DB::connection('license');

        $licencia = $dbLicencia->table('licencia')
            ->where('licencia_id', $licenciaId)
            ->first();

        return $licencia;
    }

    /**
     * Envía los datos a la API de la licencia
     *
     * @param object $licencia
     * @param array $datos
     * @return bool
     * @throws Exception
     */
    protected function sendToLicense(object $licencia, array $datos): bool
    {
        $url = "https://{$licencia->licencia_url}/migrator/witwan";

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $datos);

            // Verificar HTTP status code
            if (!$response->successful()) {
                Log::error('HTTP Error en LicenseSyncService', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception("HTTP Error: {$response->status()}");
            }

            // Decodificar respuesta
            $responseData = $response->json();

            // Verificar respuesta exitosa
            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                Log::info('Sincronización exitosa con licencia', [
                    'url' => $url,
                    'response' => $responseData
                ]);
                return true;
            } else {
                $errorMsg = $responseData['message'] ?? 'Error desconocido';
                Log::error('Error al sincronizar con licencia', [
                    'url' => $url,
                    'error' => $errorMsg
                ]);
                throw new Exception("Error en respuesta: {$errorMsg}");
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Request Error en LicenseSyncService', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Error de conexión: {$e->getMessage()}");
        }
    }
}
