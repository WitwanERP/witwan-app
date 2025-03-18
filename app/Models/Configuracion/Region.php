<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Pais;

class Region extends Model
{
    use HasFactory;

    protected $table = 'region';
    protected $primaryKey = 'region_id';
    public $timestamps = false;

    protected $fillable = [
        'region_nombre'
    ];



    public function paises()
    {
        return $this->hasMany(Pais::class, 'fk_region_id', 'region_id');
    }
}
