<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelProductoalojamientofacilidad
 * 
 * @property int $fk_producto_id
 * @property int $fk_alojamientofacilidad_id
 * 
 * @property Producto $producto
 * @property Alojamientofacilidad $alojamientofacilidad
 *
 * @package App\Models
 */
class RelProductoalojamientofacilidad extends Model
{
	protected $table = 'rel_productoalojamientofacilidad';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_alojamientofacilidad_id' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'fk_alojamientofacilidad_id'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function alojamientofacilidad()
	{
		return $this->belongsTo(Alojamientofacilidad::class, 'fk_alojamientofacilidad_id');
	}
}
