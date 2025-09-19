<?php

namespace App\Observers;

use App\Models\Reserva;
use Illuminate\Support\Facades\Log;

class ReservaObserver
{
    public function updating(Reserva $reserva)
    {
        // Verificar si un campo cambió
        if ($reserva->isDirty('fk_filestatus_id')) {
            $oldEstado = $reserva->getOriginal('fk_filestatus_id');
            $newEstado = $reserva->fk_filestatus_id;

            // aplicar los cambios de estado a los servicios relacionados servicio.status donde servicio.reserva_id = reserva.id
            foreach ($reserva->servicios as $servicio) {
                if ($servicio->status != 'CA') {
                    $servicio->status = $newEstado;
                    $servicio->save();
                }
            }
            //\Log::info("Estado cambió de $oldEstado a $newEstado");
        }
    }
}
