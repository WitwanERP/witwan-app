<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class ReservaService
{
    public function guardarReserva($data)
    {
        return DB::transaction(function () use ($data) {

            if (!$this->validarDatos($data)) {
                throw ValidationException::withMessages([
                    'reserva' => 'La reserva no cumple con las reglas de negocio.',
                ]);
            }

            $reserva = Reserva::updateOrCreate(
                ['id' => $data['reserva']['id'] ?? null],
                $data['reserva']
            );

            foreach ($data['servicios'] as $servicioData) {
                $servicio = Servicio::updateOrCreate(
                    ['id' => $servicioData['id'] ?? null],
                    array_merge($servicioData, ['reserva_id' => $reserva->id])
                );
            }

            return $reserva;
        });

    }

    protected function validarDatos($data)
    {
        // Validaciones adicionales, por ejemplo, reglas de negocio específicas
        return true;
    }
}
