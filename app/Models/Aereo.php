<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Aereo
 * 
 * @property int $aereo_id
 * @property int $fk_producto_id
 * @property int $fk_aerolinea_id
 * @property int $aereo_origen
 * @property int $aereo_destino
 * @property Carbon $fecha_ida
 * @property Carbon $fecha_vuelta
 * @property string $aereo_pnr
 * @property int $fk_moneda_id
 * @property float $aereo_tarifa
 * @property Carbon $fecha_release
 * @property Carbon $fecha_pago
 * 
 * @property Aerolinea $aerolinea
 * @property Producto $producto
 *
 * @package App\Models
 */
class Aereo extends Model
{
	protected $table = 'aereo';
	protected $primaryKey = 'aereo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_aerolinea_id' => 'int',
		'aereo_origen' => 'int',
		'aereo_destino' => 'int',
		'fecha_ida' => 'datetime',
		'fecha_vuelta' => 'datetime',
		'fk_moneda_id' => 'int',
		'aereo_tarifa' => 'float',
		'fecha_release' => 'datetime',
		'fecha_pago' => 'datetime'
	];

	protected $fillable = [
		'fk_producto_id',
		'fk_aerolinea_id',
		'aereo_origen',
		'aereo_destino',
		'fecha_ida',
		'fecha_vuelta',
		'aereo_pnr',
		'fk_moneda_id',
		'aereo_tarifa',
		'fecha_release',
		'fecha_pago'
	];

	public function aerolinea()
	{
		return $this->belongsTo(Aerolinea::class, 'fk_aerolinea_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
