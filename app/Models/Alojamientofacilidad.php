<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Alojamientofacilidad
 * 
 * @property int $alojamientofacilidad_id
 * @property string $alojamientofacilidad_nombre_en
 * @property string $alojamientofacilidad_nombre
 * @property string $alojamientofacilidad_nombre_pg
 * @property string $fk_submodulo_id
 * 
 * @property Submodulo $submodulo
 * @property Collection|Producto[] $productos
 *
 * @package App\Models
 */
class Alojamientofacilidad extends Model
{
	protected $table = 'alojamientofacilidad';
	protected $primaryKey = 'alojamientofacilidad_id';
	public $timestamps = false;

	protected $fillable = [
		'alojamientofacilidad_nombre_en',
		'alojamientofacilidad_nombre',
		'alojamientofacilidad_nombre_pg',
		'fk_submodulo_id'
	];

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}

	public function productos()
	{
		return $this->belongsToMany(Producto::class, 'rel_productoalojamientofacilidad', 'fk_alojamientofacilidad_id', 'fk_producto_id');
	}
}
