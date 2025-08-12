<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelOcupacionvigencium
 * 
 * @property int $fk_ocupacion_id
 * @property int $fk_vigencia_id
 * 
 * @property Servicio $servicio
 * @property Vigencium $vigencium
 *
 * @package App\Models
 */
class RelOcupacionvigencium extends Model
{
	protected $table = 'rel_ocupacionvigencia';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_ocupacion_id' => 'int',
		'fk_vigencia_id' => 'int'
	];

	protected $fillable = [
		'fk_ocupacion_id',
		'fk_vigencia_id'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}

	public function vigencium()
	{
		return $this->belongsTo(Vigencium::class, 'fk_vigencia_id');
	}
}
