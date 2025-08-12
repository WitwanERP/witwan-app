<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Serviciocontable
 * 
 * @property int $serviciocontable_id
 * @property int $fk_servicio_id
 * @property int $fk_plancuenta_id
 * @property float $monto
 * @property string $cuenta_descripcion
 *
 * @package App\Models
 */
class Serviciocontable extends Model
{
	protected $table = 'serviciocontable';
	protected $primaryKey = 'serviciocontable_id';
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int',
		'fk_plancuenta_id' => 'int',
		'monto' => 'float'
	];

	protected $fillable = [
		'fk_servicio_id',
		'fk_plancuenta_id',
		'monto',
		'cuenta_descripcion'
	];
}
