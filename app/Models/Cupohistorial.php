<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cupohistorial
 * 
 * @property Carbon $cupohistorial_fecha
 * @property int $fk_ocupacion_id
 * @property int $fk_producto_id
 * @property string $cupohistorial_info
 *
 * @package App\Models
 */
class Cupohistorial extends Model
{
	protected $table = 'cupohistorial';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'cupohistorial_fecha' => 'datetime',
		'fk_ocupacion_id' => 'int',
		'fk_producto_id' => 'int'
	];

	protected $fillable = [
		'cupohistorial_info'
	];
}
