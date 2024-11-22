<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regimen extends Model
{
    use HasFactory;

    protected $table = 'regimen';
    protected $primaryKey = 'regimen_id';

    protected $fillable = [
        // Agrega los campos correspondientes
        'regimen_nombre',
        'regimen_nombre_en',
        'regimen_nombre_pg',
    ];
}
