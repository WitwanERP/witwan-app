<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Conciliacion
 * 
 * @property int $fk_movimiento_id
 * @property int $fk_usuario_id
 * @property int $fk_conciliabanco_id
 * @property Carbon $regdate
 *
 * @package App\Models
 */
class Conciliacion extends Model
{
	protected $table = 'conciliacion';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_movimiento_id' => 'int',
		'fk_usuario_id' => 'int',
		'fk_conciliabanco_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_movimiento_id',
		'fk_usuario_id',
		'fk_conciliabanco_id',
		'regdate'
	];
}
