<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Historialsql
 * 
 * @property int $id
 * @property Carbon $fecha
 * @property string $nombre
 * @property string $consulta
 *
 * @package App\Models
 */
class Historialsql extends Model
{
	protected $table = 'historialsql';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'fecha',
		'nombre',
		'consulta'
	];
}
