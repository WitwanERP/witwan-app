<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RelFacturarecibo
 * 
 * @property int $rel_facturarecibo_id
 * @property int $fk_factura_id
 * @property int $fk_recibo_id
 * @property int $fk_ordenadmin_id
 * @property int $fk_notacredito_id
 * @property float $monto
 * @property Carbon $fecha
 * 
 * @property Factura $factura
 * @property Notacredito $notacredito
 * @property Ordenadmin $ordenadmin
 * @property Recibo $recibo
 *
 * @package App\Models
 */
class RelFacturarecibo extends Model
{
	protected $table = 'rel_facturarecibo';
	protected $primaryKey = 'rel_facturarecibo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_factura_id' => 'int',
		'fk_recibo_id' => 'int',
		'fk_ordenadmin_id' => 'int',
		'fk_notacredito_id' => 'int',
		'monto' => 'float',
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'fk_factura_id',
		'fk_recibo_id',
		'fk_ordenadmin_id',
		'fk_notacredito_id',
		'monto',
		'fecha'
	];

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'fk_factura_id');
	}

	public function notacredito()
	{
		return $this->belongsTo(Notacredito::class, 'fk_notacredito_id');
	}

	public function ordenadmin()
	{
		return $this->belongsTo(Ordenadmin::class, 'fk_ordenadmin_id');
	}

	public function recibo()
	{
		return $this->belongsTo(Recibo::class, 'fk_recibo_id');
	}
}
