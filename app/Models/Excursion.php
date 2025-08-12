<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Excursion
 * 
 * @property int $fk_producto_id
 * @property int $origen
 * @property string $horario_pickup
 * 
 * @property Producto $producto
 *
 * @package App\Models
 */
class Excursion extends Model
{
	protected $table = 'excursion';
	protected $primaryKey = 'fk_producto_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'origen' => 'int'
	];

	protected $fillable = [
		'origen',
		'horario_pickup'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
