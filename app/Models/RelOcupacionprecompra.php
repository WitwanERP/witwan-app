<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelOcupacionprecompra
 * 
 * @property int $fk_ocupacion_id
 * @property int $fk_precompra_id
 * 
 * @property Precompra $precompra
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class RelOcupacionprecompra extends Model
{
	protected $table = 'rel_ocupacionprecompra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_ocupacion_id' => 'int',
		'fk_precompra_id' => 'int'
	];

	protected $fillable = [
		'fk_ocupacion_id',
		'fk_precompra_id'
	];

	public function precompra()
	{
		return $this->belongsTo(Precompra::class, 'fk_precompra_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}
}
