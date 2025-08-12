<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ordenadmin
 * 
 * @property int $ordenadmin_id
 * @property int $fk_ordenadmin_id
 * @property int $fk_proveedor_id
 * @property int $fk_usuario_id
 * @property Carbon $fecha
 * @property string $nroservicio
 * @property string $nropago
 * @property string $tipo
 * @property string $fk_moneda_id
 * @property float $cotizacion
 * @property float $monto
 * @property string $status
 * @property string $observaciones
 * @property int $fk_proyecto_id
 * 
 * @property Moneda $moneda
 * @property Ordenadmin $ordenadmin
 * @property Proveedor $proveedor
 * @property Proyecto $proyecto
 * @property Usuario $usuario
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Ordenadmin[] $ordenadmins
 * @property Collection|RelFacturarecibo[] $rel_facturarecibos
 * @property RelOrdenadminocupacion|null $rel_ordenadminocupacion
 *
 * @package App\Models
 */
class Ordenadmin extends Model
{
	protected $table = 'ordenadmin';
	protected $primaryKey = 'ordenadmin_id';
	public $timestamps = false;

	protected $casts = [
		'fk_ordenadmin_id' => 'int',
		'fk_proveedor_id' => 'int',
		'fk_usuario_id' => 'int',
		'fecha' => 'datetime',
		'cotizacion' => 'float',
		'monto' => 'float',
		'fk_proyecto_id' => 'int'
	];

	protected $fillable = [
		'fk_ordenadmin_id',
		'fk_proveedor_id',
		'fk_usuario_id',
		'fecha',
		'nroservicio',
		'nropago',
		'tipo',
		'fk_moneda_id',
		'cotizacion',
		'monto',
		'status',
		'observaciones',
		'fk_proyecto_id'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function ordenadmin()
	{
		return $this->belongsTo(Ordenadmin::class, 'fk_ordenadmin_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function proyecto()
	{
		return $this->belongsTo(Proyecto::class, 'fk_proyecto_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_ordenadmin_id');
	}

	public function ordenadmins()
	{
		return $this->hasMany(Ordenadmin::class, 'fk_ordenadmin_id');
	}

	public function rel_facturarecibos()
	{
		return $this->hasMany(RelFacturarecibo::class, 'fk_ordenadmin_id');
	}

	public function rel_ordenadminocupacion()
	{
		return $this->hasOne(RelOrdenadminocupacion::class, 'fk_ordenadmin_id');
	}
}
