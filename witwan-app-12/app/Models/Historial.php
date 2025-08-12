<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Historial
 * 
 * @property int $historial_id
 * @property Carbon $historial_fecha
 * @property string $historial_texto
 *
 * @package App\Models
 */
class Historial extends Model
{
	protected $table = 'historial';
	protected $primaryKey = 'historial_id';
	public $timestamps = false;

	protected $casts = [
		'historial_fecha' => 'datetime'
	];

	protected $fillable = [
		'historial_fecha',
		'historial_texto'
	];
}
