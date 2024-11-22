<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestador extends Model
{
    use HasFactory;

    protected $table = 'proveedor';
    protected $primaryKey = 'proveedor_id';

    protected $fillable = [
        // Agrega los campos correspondientes
    ];
}
