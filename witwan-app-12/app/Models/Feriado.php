<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Feriado
 * 
 * @property int $feriado_id
 * @property Carbon $feriado_fecha
 *
 * @package App\Models
 */
class Feriado extends Model
{
	protected $table = 'feriado';
	protected $primaryKey = 'feriado_id';
	public $timestamps = false;

	protected $casts = [
		'feriado_fecha' => 'datetime'
	];

	protected $fillable = [
		'feriado_fecha'
	];
}
