<?php

namespace App\Models\Productos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Empresas\Proveedor;
use App\Models\Empresas\Cliente;

class Aerolinea extends Model
{
    use HasFactory;

    protected $table = 'aerolinea';
    protected $primaryKey = 'aerolinea_id';
    public $timestamps = false;
/*aerolinea_id	int(10) Incremento automático
fk_proveedor_id	int(11)
fk_cliente_id	int(11)
aerolinea_nombre	varchar(150)
comisionbsp	decimal(5,2)
aerolinea_codigo	varchar(4)
aerolinea_bspcode	varchar(3)
aerolinea_icao	varchar(3)
callsign	varchar(200)
test	varchar(150)	 */
    protected $fillable = [
        'aerolinea_nombre',
        'fk_proveedor_id',
        'fk_cliente_id',
        'comisionbsp',
        'aerolinea_codigo',
        'aerolinea_bspcode',
        'aerolinea_icao',
        'callsign',
    ];

    public function proveedor(){
        return $this->belongsTo(Proveedor::class, 'fk_proveedor_id', 'proveedor_id');
    }

    public function cliente(){
        return $this->belongsTo(Cliente::class, 'fk_cliente_id', 'cliente_id');
    }

}
