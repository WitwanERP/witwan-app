<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moneda extends Model
{
    use HasFactory;

    protected $table = 'moneda';
    protected $primaryKey = 'moneda_id';

    protected $fillable = [
        // Agrega los campos correspondientes
    ];
}
