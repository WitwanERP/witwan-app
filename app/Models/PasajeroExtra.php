<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PasajeroExtra
 * 
 * @property int $fk_pasajero_id
 * @property Carbon $regdate
 * @property string $extra_nombre
 * @property string $extra_valor
 *
 * @package App\Models
 */
class PasajeroExtra extends Model
{
	protected $table = 'pasajero_extra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_pasajero_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_pasajero_id',
		'regdate',
		'extra_nombre',
		'extra_valor'
	];
}
