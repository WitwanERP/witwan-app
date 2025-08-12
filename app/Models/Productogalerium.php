<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Productogalerium
 * 
 * @property int $productogaleria_id
 * @property int $fk_producto_id
 * @property string $productogaleria_archivo
 * @property int $orden
 * 
 * @property Producto $producto
 *
 * @package App\Models
 */
class Productogalerium extends Model
{
	protected $table = 'productogaleria';
	protected $primaryKey = 'productogaleria_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'orden' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'productogaleria_archivo',
		'orden'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
