<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Serviciofactura
 * 
 * @property int $fk_servicio_id
 * @property int $fk_factura_id
 * @property int $tipodocumento
 * @property Carbon $fecha
 * @property float $monto
 * @property float $renta
 * @property float $iva
 * @property int $activo
 * @property string $contable
 * 
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class Serviciofactura extends Model
{
	protected $table = 'serviciofactura';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int',
		'fk_factura_id' => 'int',
		'tipodocumento' => 'int',
		'fecha' => 'datetime',
		'monto' => 'float',
		'renta' => 'float',
		'iva' => 'float',
		'activo' => 'int'
	];

	protected $fillable = [
		'fecha',
		'monto',
		'renta',
		'iva',
		'activo',
		'contable'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}
}
