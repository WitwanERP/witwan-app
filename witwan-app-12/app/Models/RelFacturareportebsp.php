<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RelFacturareportebsp
 * 
 * @property int $rel_facturareportebsp_id
 * @property int $fk_factura_id
 * @property int $fk_reportebsp_id
 * @property int $fk_cliente_id
 * @property float $monto
 * @property Carbon $fecha
 *
 * @package App\Models
 */
class RelFacturareportebsp extends Model
{
	protected $table = 'rel_facturareportebsp';
	protected $primaryKey = 'rel_facturareportebsp_id';
	public $timestamps = false;

	protected $casts = [
		'fk_factura_id' => 'int',
		'fk_reportebsp_id' => 'int',
		'fk_cliente_id' => 'int',
		'monto' => 'float',
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'fk_factura_id',
		'fk_reportebsp_id',
		'fk_cliente_id',
		'monto',
		'fecha'
	];
}
