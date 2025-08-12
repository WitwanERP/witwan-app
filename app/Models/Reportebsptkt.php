<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Reportebsptkt
 * 
 * @property int $reportebsptkt_id
 * @property int $fk_ocupacion_id
 * @property int $fk_reportebsp_id
 * @property string $reportebsptkt_tipo
 * @property string $reportebsptkt_tipodestino
 * @property string $reportebsptkt_tkt
 * @property string $reportebsptkt_cia
 * @property float $reportebsptkt_tarifa
 * @property string $reportebsptkt_impuestos
 * @property float $impuestos
 * @property float $reportebsptkt_comision
 * @property float $reportebsptkt_ivacomision
 * @property float $reportebsptkt_over
 * @property float $reportebsptkt_cash
 * @property float $reportebsptkt_tarjeta
 * @property float $reportebsptkt_costo
 * @property float $reportebsptkt_final
 * @property float $reportebsptkt_roe
 * @property string $reportebsptkt_observaciones
 * @property string $reportebsptkt_errores
 * @property int $reportebsptkt_ok
 * @property string $reportebsptkt_reclamo
 * @property string $fk_moneda_id
 * @property Carbon $reportebsptkt_emision
 * 
 * @property Moneda $moneda
 * @property Reportebsp $reportebsp
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class Reportebsptkt extends Model
{
	protected $table = 'reportebsptkt';
	protected $primaryKey = 'reportebsptkt_id';
	public $timestamps = false;

	protected $casts = [
		'fk_ocupacion_id' => 'int',
		'fk_reportebsp_id' => 'int',
		'reportebsptkt_tarifa' => 'float',
		'impuestos' => 'float',
		'reportebsptkt_comision' => 'float',
		'reportebsptkt_ivacomision' => 'float',
		'reportebsptkt_over' => 'float',
		'reportebsptkt_cash' => 'float',
		'reportebsptkt_tarjeta' => 'float',
		'reportebsptkt_costo' => 'float',
		'reportebsptkt_final' => 'float',
		'reportebsptkt_roe' => 'float',
		'reportebsptkt_ok' => 'int',
		'reportebsptkt_emision' => 'datetime'
	];

	protected $fillable = [
		'fk_ocupacion_id',
		'fk_reportebsp_id',
		'reportebsptkt_tipo',
		'reportebsptkt_tipodestino',
		'reportebsptkt_tkt',
		'reportebsptkt_cia',
		'reportebsptkt_tarifa',
		'reportebsptkt_impuestos',
		'impuestos',
		'reportebsptkt_comision',
		'reportebsptkt_ivacomision',
		'reportebsptkt_over',
		'reportebsptkt_cash',
		'reportebsptkt_tarjeta',
		'reportebsptkt_costo',
		'reportebsptkt_final',
		'reportebsptkt_roe',
		'reportebsptkt_observaciones',
		'reportebsptkt_errores',
		'reportebsptkt_ok',
		'reportebsptkt_reclamo',
		'fk_moneda_id',
		'reportebsptkt_emision'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function reportebsp()
	{
		return $this->belongsTo(Reportebsp::class, 'fk_reportebsp_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}
}
