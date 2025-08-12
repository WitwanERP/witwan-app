<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cotizacion
 * 
 * @property int $cotizacion_id
 * @property string $cotizacion_moneda
 * @property Carbon $cotizacion_fecha
 * @property float $cotizacion_relacion
 * @property float $cotizacion_costo
 * 
 * @property Moneda $moneda
 *
 * @package App\Models
 */
class Cotizacion extends Model
{
	protected $table = 'cotizacion';
	protected $primaryKey = 'cotizacion_id';
	public $timestamps = false;

	protected $casts = [
		'cotizacion_fecha' => 'datetime',
		'cotizacion_relacion' => 'float',
		'cotizacion_costo' => 'float'
	];

	protected $fillable = [
		'cotizacion_moneda',
		'cotizacion_fecha',
		'cotizacion_relacion',
		'cotizacion_costo'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'cotizacion_moneda', 'moneda_id');
	}
}
