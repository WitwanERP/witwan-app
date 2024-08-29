<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Empresas\Cliente;

class Reserva extends Model
{
    use HasFactory;
    protected $table = 'reserva';
    protected $primaryKey = 'reserva_id';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente_id', 'cliente_id');
    }

    public function facturara(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'facturar_a', 'cliente_id');
    }
}
