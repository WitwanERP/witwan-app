<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Region;
use App\Models\Configuracion\Ciudad;

class Pais extends Model
{
    use HasFactory;

    protected $table = 'pais';
    protected $primaryKey = 'pais_id';
    public $timestamps = false;

    protected $fillable = [
        'pais_nombre',
        'pais_codigo',
        'pais_cuit',
        'codigoafip',
        'fk_region_id',
    ];


    public function region()
    {
        return $this->belongsTo(Region::class, 'fk_region_id', 'region_id');
    }

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class, 'fk_pais_id', 'pais_id');
    }
}
