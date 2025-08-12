<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Iva
 * 
 * @property int $iva_id
 * @property int $fk_sistema_id
 * @property string $fk_submodulo_id
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property int $fk_producto_id
 * @property int $fk_modoivaventa_id
 * @property float $iva_valor
 * @property float $iva_costo
 * 
 * @property Ciudad $ciudad
 * @property Modoivaventum $modoivaventum
 * @property Pai $pai
 * @property Producto $producto
 * @property Sistema $sistema
 * @property Submodulo $submodulo
 *
 * @package App\Models
 */
class Iva extends Model
{
	protected $table = 'iva';
	protected $primaryKey = 'iva_id';
	public $timestamps = false;

	protected $casts = [
		'fk_sistema_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_modoivaventa_id' => 'int',
		'iva_valor' => 'float',
		'iva_costo' => 'float'
	];

	protected $fillable = [
		'fk_sistema_id',
		'fk_submodulo_id',
		'fk_pais_id',
		'fk_ciudad_id',
		'fk_producto_id',
		'fk_modoivaventa_id',
		'iva_valor',
		'iva_costo'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function modoivaventum()
	{
		return $this->belongsTo(Modoivaventum::class, 'fk_modoivaventa_id');
	}

	public function pai()
	{
		return $this->belongsTo(Pai::class, 'fk_pais_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}
}
