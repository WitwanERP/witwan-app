<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Pais;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudad';
    protected $primaryKey = 'ciudad_id';
    public $timestamps = false;

    protected $fillable = [
        'ciudad_activo',
        'fk_pais_id',
        'fk_ciudad_id',
        'ciudad_nombre',
        'ciudad_codigo',
        'nombre_en',
        'nombre_pg',
        'ap',
        'codigo_tourico',
        'codigo_amex',
        'codigo_hb',
        'codigo_ws1',
        'codigo_ws2',
        'codigo_ws3',
        'codigo_ws4',
        'codigo_ws5',
        'latitud',
        'longitud'
    ];

    protected $casts = [
        'ciudad_activo' => 'boolean',
        'ap' => 'boolean',
        'latitud' => 'decimal:10',
        'longitud' => 'decimal:10',
        'codigo_amex' => 'integer'
    ];

    // Relationship with Pais
    public function pais()
    {
        return $this->belongsTo(Pais::class, 'fk_pais_id', 'pais_id');
    }

    // Self-referential relationship for internal city points
    public function ciudadPrincipal()
    {
        return $this->belongsTo(Ciudad::class, 'fk_ciudad_id', 'ciudad_id');
    }

    public function puntosInternos()
    {
        return $this->hasMany(Ciudad::class, 'fk_ciudad_id', 'ciudad_id');
    }
}
