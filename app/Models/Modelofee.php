<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Modelofee
 * 
 * @property int $modelofee_id
 * @property int $fk_cliente_id
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property int $region_origen
 * @property int $fk_region_id
 * @property string $fk_moneda_id
 * @property string $tipocodigo
 * @property string $modelofee_tipo
 * @property string $modelofee_nombre
 * @property string $fk_submodulo_id
 * @property float $modelofee_normal
 * @property float $modelofee_offline
 * @property float $modelofee_emergencia
 * @property float $modelofee_normal_r
 * @property float $modelofee_offline_r
 * @property float $modelofee_emergencia_r
 * @property float $modelofee_minimo
 * @property float $modelofee_maximo
 * 
 * @property Ciudad $ciudad
 * @property Cliente $cliente
 * @property Moneda $moneda
 * @property Pai $pai
 * @property Region $region
 * @property Submodulo $submodulo
 *
 * @package App\Models
 */
class Modelofee extends Model
{
	protected $table = 'modelofee';
	protected $primaryKey = 'modelofee_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'region_origen' => 'int',
		'fk_region_id' => 'int',
		'modelofee_normal' => 'float',
		'modelofee_offline' => 'float',
		'modelofee_emergencia' => 'float',
		'modelofee_normal_r' => 'float',
		'modelofee_offline_r' => 'float',
		'modelofee_emergencia_r' => 'float',
		'modelofee_minimo' => 'float',
		'modelofee_maximo' => 'float'
	];

	protected $fillable = [
		'fk_cliente_id',
		'fk_pais_id',
		'fk_ciudad_id',
		'region_origen',
		'fk_region_id',
		'fk_moneda_id',
		'tipocodigo',
		'modelofee_tipo',
		'modelofee_nombre',
		'fk_submodulo_id',
		'modelofee_normal',
		'modelofee_offline',
		'modelofee_emergencia',
		'modelofee_normal_r',
		'modelofee_offline_r',
		'modelofee_emergencia_r',
		'modelofee_minimo',
		'modelofee_maximo'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function pai()
	{
		return $this->belongsTo(Pai::class, 'fk_pais_id');
	}

	public function region()
	{
		return $this->belongsTo(Region::class, 'fk_region_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}
}
