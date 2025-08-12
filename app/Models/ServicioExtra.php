<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ServicioExtra
 * 
 * @property int $fk_servicio_id
 * @property Carbon $regdate
 * @property string $extra_nombre
 * @property string $extra_valor
 * 
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class ServicioExtra extends Model
{
	protected $table = 'servicio_extra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'extra_valor'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}
}
