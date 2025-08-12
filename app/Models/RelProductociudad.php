<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelProductociudad
 * 
 * @property int $fk_producto_id
 * @property int $fk_ciudad_id
 * @property string $tipo
 * 
 * @property Ciudad $ciudad
 * @property Producto $producto
 *
 * @package App\Models
 */
class RelProductociudad extends Model
{
	protected $table = 'rel_productociudad';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_ciudad_id' => 'int'
	];

	protected $fillable = [
		'tipo'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
