<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Porveedor extends Model
{
    use HasFactory;
    protected $table = 'proveedor';
    protected $primaryKey = 'proveedor_id';
}
