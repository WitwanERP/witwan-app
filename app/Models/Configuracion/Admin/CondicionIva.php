<?php

namespace App\Models\Configuracion\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CondicionIva extends Model
{
    use HasFactory;
    protected $table = 'condicioniva';
    protected $primaryKey = 'condicioniva_id';
    public $timestamps = false;
    protected $fillable = [
        'condicioniva_nombre',
        'fk_tipofactura_id',
        'porcentaje',
        'incluido',
    ];
    protected $casts = [
        'porcentaje' => 'decimal:2',
        'incluido' => 'boolean',
    ];
}
