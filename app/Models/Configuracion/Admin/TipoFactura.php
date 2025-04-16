<?php

namespace App\Models\Configuracion\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoFactura extends Model
{
    use HasFactory;
    protected $table = 'tipofactura';
    protected $primaryKey = 'tipofactura_id';
    public $timestamps = false;
    protected $fillable = [
        'tipofactura_nombre',
    ];
}
/**
Columna	Tipo	Comentario
tipofactura_id	int(11) [0]
tipofactura_nombre	text
 */
