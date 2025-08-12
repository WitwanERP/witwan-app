<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifacategorium
 * 
 * @property int $tarifacategoria_id
 * @property string $fk_submodulo_id
 * @property string $tarifacategoria_nombre
 * @property int $fk_producto_id
 * @property string $tarifacategoria_nombre_en
 * @property string $tarifacategoria_nombre_pg
 * 
 * @property Producto $producto
 * @property Submodulo $submodulo
 * @property Collection|Alojamientohabitacion[] $alojamientohabitacions
 * @property Collection|Cupo[] $cupos
 * @property Collection|Pkdproducto[] $pkdproductos
 * @property Collection|Servicio[] $servicios
 * @property Collection|Soldout[] $soldouts
 * @property Collection|Tarifa[] $tarifas
 *
 * @package App\Models
 */
class Tarifacategorium extends Model
{
	protected $table = 'tarifacategoria';
	protected $primaryKey = 'tarifacategoria_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int'
	];

	protected $fillable = [
		'fk_submodulo_id',
		'tarifacategoria_nombre',
		'fk_producto_id',
		'tarifacategoria_nombre_en',
		'tarifacategoria_nombre_pg'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}

	public function alojamientohabitacions()
	{
		return $this->hasMany(Alojamientohabitacion::class, 'fk_tarifacategoria_id');
	}

	public function cupos()
	{
		return $this->hasMany(Cupo::class, 'fk_tarifacategoria_id');
	}

	public function pkdproductos()
	{
		return $this->hasMany(Pkdproducto::class, 'fk_tarifacategoria_id');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_tarifacategoria_id');
	}

	public function soldouts()
	{
		return $this->hasMany(Soldout::class, 'fk_tarifacategoria_id');
	}

	public function tarifas()
	{
		return $this->hasMany(Tarifa::class, 'fk_tarifacategoria_id');
	}
}
