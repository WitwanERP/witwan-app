<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaCategoria extends Model
{
    use HasFactory;

    protected $table = 'tarifacategoria';
    protected $primaryKey = 'tarifacategoria_id';

    protected $fillable = [
        // Agrega los campos correspondientes
        'tarifacategoria_nombre',

    ];
}
