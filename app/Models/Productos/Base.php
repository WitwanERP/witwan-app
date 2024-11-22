<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    use HasFactory;

    protected $table = 'base';
    protected $primaryKey = 'base_id';

    protected $fillable = [
        // Agrega los campos correspondientes
    ];
}
