<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cupoaereo
 * 
 * @property int $fk_producto_id
 * @property int $fk_aerolinea_id
 * @property string $vuelo_ida
 * @property string $vuelo_vuelta
 * @property Carbon $fecha_ida
 * @property Carbon $fecha_vuelta
 * @property int $origen
 * @property int $destino
 * @property int $destino2
 * @property string $destino2_vuelo
 * @property Carbon $destino2_fecha
 * @property string $pnr
 * @property string $pnrdescripcion
 * @property string $puntointermedio
 * @property string $fk_moneda_id
 * @property float $tarifa_neta
 * @property float $netofinal
 * @property float $child_netofinal
 * @property float $ventafinal
 * @property float $child_ventafinal
 * @property float $child_tarifa_neta
 * @property float $cobertura
 * @property float $child_cobertura
 * @property float $markup
 * @property float $impuestos_totales
 * @property float $child_impuestos_totales
 * @property float $impuesto1
 * @property float $child_impuesto1
 * @property float $impuesto2
 * @property float $child_impuesto2
 * @property float $impuesto3
 * @property float $child_impuesto3
 * @property float $impuesto4
 * @property float $child_impuesto4
 * @property float $impuestozk
 * @property float $child_impuestozk
 * @property Carbon $fecha_release
 * @property Carbon $fecha_pago
 * @property string $moneda_pago
 * @property float $monto_pago
 * @property Carbon $fecha_sena
 * @property string $moneda_sena
 * @property float $monto_sena
 * @property int $cantidad
 * @property string $codigoaerolinea
 * @property int $voucher
 * @property string $infopax
 * @property int $promocional
 * @property int $cupo_copa
 * @property int $feecomisionable
 * @property int $cupoweb
 * 
 * @property Aerolinea $aerolinea
 * @property Ciudad $ciudad
 * @property Moneda $moneda
 * @property Producto $producto
 *
 * @package App\Models
 */
class Cupoaereo extends Model
{
	protected $table = 'cupoaereo';
	protected $primaryKey = 'fk_producto_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_aerolinea_id' => 'int',
		'fecha_ida' => 'datetime',
		'fecha_vuelta' => 'datetime',
		'origen' => 'int',
		'destino' => 'int',
		'destino2' => 'int',
		'destino2_fecha' => 'datetime',
		'tarifa_neta' => 'float',
		'netofinal' => 'float',
		'child_netofinal' => 'float',
		'ventafinal' => 'float',
		'child_ventafinal' => 'float',
		'child_tarifa_neta' => 'float',
		'cobertura' => 'float',
		'child_cobertura' => 'float',
		'markup' => 'float',
		'impuestos_totales' => 'float',
		'child_impuestos_totales' => 'float',
		'impuesto1' => 'float',
		'child_impuesto1' => 'float',
		'impuesto2' => 'float',
		'child_impuesto2' => 'float',
		'impuesto3' => 'float',
		'child_impuesto3' => 'float',
		'impuesto4' => 'float',
		'child_impuesto4' => 'float',
		'impuestozk' => 'float',
		'child_impuestozk' => 'float',
		'fecha_release' => 'datetime',
		'fecha_pago' => 'datetime',
		'monto_pago' => 'float',
		'fecha_sena' => 'datetime',
		'monto_sena' => 'float',
		'cantidad' => 'int',
		'voucher' => 'int',
		'promocional' => 'int',
		'cupo_copa' => 'int',
		'feecomisionable' => 'int',
		'cupoweb' => 'int'
	];

	protected $fillable = [
		'fk_aerolinea_id',
		'vuelo_ida',
		'vuelo_vuelta',
		'fecha_ida',
		'fecha_vuelta',
		'origen',
		'destino',
		'destino2',
		'destino2_vuelo',
		'destino2_fecha',
		'pnr',
		'pnrdescripcion',
		'puntointermedio',
		'fk_moneda_id',
		'tarifa_neta',
		'netofinal',
		'child_netofinal',
		'ventafinal',
		'child_ventafinal',
		'child_tarifa_neta',
		'cobertura',
		'child_cobertura',
		'markup',
		'impuestos_totales',
		'child_impuestos_totales',
		'impuesto1',
		'child_impuesto1',
		'impuesto2',
		'child_impuesto2',
		'impuesto3',
		'child_impuesto3',
		'impuesto4',
		'child_impuesto4',
		'impuestozk',
		'child_impuestozk',
		'fecha_release',
		'fecha_pago',
		'moneda_pago',
		'monto_pago',
		'fecha_sena',
		'moneda_sena',
		'monto_sena',
		'cantidad',
		'codigoaerolinea',
		'voucher',
		'infopax',
		'promocional',
		'cupo_copa',
		'feecomisionable',
		'cupoweb'
	];

	public function aerolinea()
	{
		return $this->belongsTo(Aerolinea::class, 'fk_aerolinea_id');
	}

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'destino2');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
