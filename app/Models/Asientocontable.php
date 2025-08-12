<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Asientocontable
 * 
 * @property int $asientocontable_id
 * @property Carbon $asientocontable_fecha
 * @property float $debe
 * @property float $haber
 * 
 * @property Collection|Movimiento[] $movimientos
 *
 * @package App\Models
 */
class Asientocontable extends Model
{
	protected $table = 'asientocontable';
	protected $primaryKey = 'asientocontable_id';
	public $timestamps = false;

	protected $casts = [
		'asientocontable_fecha' => 'datetime',
		'debe' => 'float',
		'haber' => 'float'
	];

	protected $fillable = [
		'asientocontable_fecha',
		'debe',
		'haber'
	];

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_asientocontable_id');
	}
}
