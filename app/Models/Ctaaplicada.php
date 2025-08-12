<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ctaaplicada
 * 
 * @property int $ctaaplicada_id
 * @property Carbon $regdate
 * @property int $idmovimiento
 * @property string $tipomovimiento
 * @property float $monto
 * @property int $fk_usuario_id
 * @property int $idaplicacion
 * @property int $automatico
 *
 * @package App\Models
 */
class Ctaaplicada extends Model
{
	protected $table = 'ctaaplicada';
	protected $primaryKey = 'ctaaplicada_id';
	public $timestamps = false;

	protected $casts = [
		'regdate' => 'datetime',
		'idmovimiento' => 'int',
		'monto' => 'float',
		'fk_usuario_id' => 'int',
		'idaplicacion' => 'int',
		'automatico' => 'int'
	];

	protected $fillable = [
		'regdate',
		'idmovimiento',
		'tipomovimiento',
		'monto',
		'fk_usuario_id',
		'idaplicacion',
		'automatico'
	];
}
