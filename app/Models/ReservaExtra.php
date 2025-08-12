<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ReservaExtra
 * 
 * @property int $fk_reserva_id
 * @property Carbon $regdate
 * @property string $extra_nombre
 * @property string $extra_valor
 * 
 * @property Reserva $reserva
 *
 * @package App\Models
 */
class ReservaExtra extends Model
{
	protected $table = 'reserva_extra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_reserva_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_reserva_id',
		'regdate',
		'extra_nombre',
		'extra_valor'
	];

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_reserva_id');
	}
}
