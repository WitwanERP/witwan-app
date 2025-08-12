<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Productogrupo
 * 
 * @property int $productogrupo_id
 * @property string $productogrupo_nombre
 * @property int $eliminar
 * 
 * @property Collection|Producto[] $productos
 *
 * @package App\Models
 */
class Productogrupo extends Model
{
	protected $table = 'productogrupo';
	protected $primaryKey = 'productogrupo_id';
	public $timestamps = false;

	protected $casts = [
		'eliminar' => 'int'
	];

	protected $fillable = [
		'productogrupo_nombre',
		'eliminar'
	];

	public function productos()
	{
		return $this->hasMany(Producto::class, 'fk_productogrupo_id');
	}
}
