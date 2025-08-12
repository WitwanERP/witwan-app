<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifario
 * 
 * @property int $tarifario_id
 * @property float $cotizacion
 * @property string $crol
 * @property int $fk_sistema_id
 * @property string|null $tarifario_nombre
 * @property string $fk_moneda_id
 * @property int $orden
 * @property string $archivo
 * @property int $interno
 * 
 * @property Moneda $moneda
 * @property Sistema $sistema
 * @property Collection|Cupo[] $cupos
 * @property Collection|Pkd[] $pkds
 * @property Collection|RelClientesistema[] $rel_clientesistemas
 * @property Collection|Tarifa[] $tarifas
 * @property Collection|Tarifarioarchivo[] $tarifarioarchivos
 * @property Collection|Tarifariocomision[] $tarifariocomisions
 * @property Collection|Vigencium[] $vigencia
 *
 * @package App\Models
 */
class Tarifario extends Model
{
	protected $table = 'tarifario';
	protected $primaryKey = 'tarifario_id';
	public $timestamps = false;

	protected $casts = [
		'cotizacion' => 'float',
		'fk_sistema_id' => 'int',
		'orden' => 'int',
		'interno' => 'int'
	];

	protected $fillable = [
		'cotizacion',
		'crol',
		'fk_sistema_id',
		'tarifario_nombre',
		'fk_moneda_id',
		'orden',
		'archivo',
		'interno'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function cupos()
	{
		return $this->hasMany(Cupo::class, 'fk_tarifario_id');
	}

	public function pkds()
	{
		return $this->hasMany(Pkd::class, 'fk_tarifario_id');
	}

	public function rel_clientesistemas()
	{
		return $this->hasMany(RelClientesistema::class, 'fk_tarifario_id');
	}

	public function tarifas()
	{
		return $this->hasMany(Tarifa::class, 'fk_tarifario_id');
	}

	public function tarifarioarchivos()
	{
		return $this->hasMany(Tarifarioarchivo::class, 'fk_tarifario_id');
	}

	public function tarifariocomisions()
	{
		return $this->hasMany(Tarifariocomision::class, 'fk_tarifario_id');
	}

	public function vigencia()
	{
		return $this->hasMany(Vigencium::class, 'fk_tarifario_id');
	}
}
