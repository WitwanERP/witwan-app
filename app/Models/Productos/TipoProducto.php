<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    use HasFactory;

    protected $table = 'submodulo';
    protected $primaryKey = 'tipoproducto_id';

    protected $fillable = [
        // Agrega los campos correspondientes
    ];
}
