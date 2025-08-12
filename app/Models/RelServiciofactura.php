<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RelServiciofactura
 * 
 * @property int $fk_servicio_id
 * @property int $fk_factura_id
 * @property int $tipodocumento
 * @property Carbon $fecha
 * @property float $monto
 * 
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class RelServiciofactura extends Model
{
	protected $table = 'rel_serviciofactura';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int',
		'fk_factura_id' => 'int',
		'tipodocumento' => 'int',
		'fecha' => 'datetime',
		'monto' => 'float'
	];

	protected $fillable = [
		'fecha',
		'monto'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}
}
