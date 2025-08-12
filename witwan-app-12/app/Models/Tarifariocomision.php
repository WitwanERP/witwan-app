<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifariocomision
 * 
 * @property int $tarifariocomision_id
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property string $oldid
 * @property string $crol
 * @property int $fk_tarifario_id
 * @property string $fk_submodulo_id
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property int $fk_producto_id
 * @property float $porcentaje_comision
 * @property float $divisor_markup
 * @property string $origen
 * 
 * @property Ciudad $ciudad
 * @property Pai $pai
 * @property Producto $producto
 * @property Submodulo $submodulo
 * @property Tarifario $tarifario
 *
 * @package App\Models
 */
class Tarifariocomision extends Model
{
	protected $table = 'tarifariocomision';
	protected $primaryKey = 'tarifariocomision_id';
	public $timestamps = false;

	protected $casts = [
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'fk_tarifario_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fk_producto_id' => 'int',
		'porcentaje_comision' => 'float',
		'divisor_markup' => 'float'
	];

	protected $fillable = [
		'vigencia_ini',
		'vigencia_fin',
		'oldid',
		'crol',
		'fk_tarifario_id',
		'fk_submodulo_id',
		'fk_pais_id',
		'fk_ciudad_id',
		'fk_producto_id',
		'porcentaje_comision',
		'divisor_markup',
		'origen'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function pai()
	{
		return $this->belongsTo(Pai::class, 'fk_pais_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}
}
