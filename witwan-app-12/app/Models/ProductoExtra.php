<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductoExtra
 * 
 * @property int $fk_producto_id
 * @property Carbon $regdate
 * @property string $extra_nombre
 * @property string $extra_valor
 * 
 * @property Producto $producto
 *
 * @package App\Models
 */
class ProductoExtra extends Model
{
	protected $table = 'producto_extra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_producto_id',
		'regdate',
		'extra_nombre',
		'extra_valor'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
