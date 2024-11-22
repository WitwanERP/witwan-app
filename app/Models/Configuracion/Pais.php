<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    use HasFactory;

    protected $table = 'pais';
    protected $primaryKey = 'pais_id';

    protected $fillable = [
        // Agrega los campos correspondientes
        'pais_nombre',
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
