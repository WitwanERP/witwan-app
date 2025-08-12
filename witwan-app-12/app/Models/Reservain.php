<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Reservain
 * 
 * @property int $fk_reserva_id
 * @property Carbon $inicio
 *
 * @package App\Models
 */
class Reservain extends Model
{
	protected $table = 'reservain';
	protected $primaryKey = 'fk_reserva_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_reserva_id' => 'int',
		'inicio' => 'datetime'
	];

	protected $fillable = [
		'inicio'
	];
}
