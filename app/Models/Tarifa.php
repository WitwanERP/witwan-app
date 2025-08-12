<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifa
 * 
 * @property int $tarifa_id
 * @property int $fk_costo_id
 * @property int $fk_vigencia_id
 * @property int $fk_tarifario_id
 * @property int $fk_tarifacategoria_id
 * @property string $fk_base_id
 * @property string $moneda_venta
 * @property string $moneda_costo
 * @property int $min_pax
 * @property int $max_pax
 * @property string $fk_tipopax_id
 * @property float $costo
 * @property float $ivacosto
 * @property float $impuestos
 * @property string $redondear
 * 
 * @property Base $base
 * @property Tarifa $tarifa
 * @property Tarifacategorium $tarifacategorium
 * @property Tarifario $tarifario
 * @property Vigencium $vigencium
 * @property Collection|Tarifa[] $tarifas
 *
 * @package App\Models
 */
class Tarifa extends Model
{
	protected $table = 'tarifa';
	protected $primaryKey = 'tarifa_id';
	public $timestamps = false;

	protected $casts = [
		'fk_costo_id' => 'int',
		'fk_vigencia_id' => 'int',
		'fk_tarifario_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'min_pax' => 'int',
		'max_pax' => 'int',
		'costo' => 'float',
		'ivacosto' => 'float',
		'impuestos' => 'float'
	];

	protected $fillable = [
		'fk_costo_id',
		'fk_vigencia_id',
		'fk_tarifario_id',
		'fk_tarifacategoria_id',
		'fk_base_id',
		'moneda_venta',
		'moneda_costo',
		'min_pax',
		'max_pax',
		'fk_tipopax_id',
		'costo',
		'ivacosto',
		'impuestos',
		'redondear'
	];

	public function base()
	{
		return $this->belongsTo(Base::class, 'fk_base_id');
	}

	public function tarifa()
	{
		return $this->belongsTo(Tarifa::class, 'fk_costo_id');
	}

	public function tarifacategorium()
	{
		return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}

	public function vigencium()
	{
		return $this->belongsTo(Vigencium::class, 'fk_vigencia_id');
	}

	public function tarifas()
	{
		return $this->hasMany(Tarifa::class, 'fk_costo_id');
	}
}
