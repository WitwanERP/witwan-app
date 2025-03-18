<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submodulo extends Model
{
    use HasFactory;

    protected $table = 'submodulo';
    protected $primaryKey = 'submodulo_id';

    protected $fillable = [
        // Agrega los campos correspondientes
        'submodulo_nombre',
    ];
}
