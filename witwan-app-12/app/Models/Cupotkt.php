<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cupotkt
 * 
 * @property int $fk_producto_id
 * @property int $fk_ciudad_id
 * @property Carbon $fecha_ida
 * @property string $horario
 * @property int $cant_tkt1
 * @property float $costo_tkt1
 * @property float $fee_tkt1
 * @property float $utilidad_tkt1
 * @property float $venta_tkt1
 * @property float $cant_tkt2
 * @property float $costo_tkt2
 * @property float $fee_tkt2
 * @property float $utilidad_tkt2
 * @property float $venta_tkt2
 * @property float $cant_tkt3
 * @property float $costo_tkt3
 * @property float $fee_tkt3
 * @property float $utilidad_tkt3
 * @property float $venta_tkt3
 * @property float $cant_tkt4
 * @property float $costo_tkt4
 * @property float $fee_tkt4
 * @property float $utilidad_tkt4
 * @property float $venta_tkt4
 * @property string $fk_moneda_id
 * @property string $moneda_costo
 * @property Carbon $fecha_release
 * @property float $total_cupo
 * @property float $total_factura
 * @property string $factuanro
 * @property string $comentarios
 * @property float $cobertura_tkt1
 * @property float $cobertura_tkt2
 * @property float $cobertura_tkt3
 * @property float $cobertura_tkt4
 * @property float $rg_tkt1
 * @property float $rg_tkt2
 * @property float $rg_tkt3
 * @property float $rg_tkt4
 * @property string $nombre_tkt1
 * @property string $nombre_tkt2
 * @property string $nombre_tkt3
 * @property string $nombre_tkt4
 * 
 * @property Ciudad $ciudad
 * @property Producto $producto
 *
 * @package App\Models
 */
class Cupotkt extends Model
{
	protected $table = 'cupotkt';
	protected $primaryKey = 'fk_producto_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fecha_ida' => 'datetime',
		'cant_tkt1' => 'int',
		'costo_tkt1' => 'float',
		'fee_tkt1' => 'float',
		'utilidad_tkt1' => 'float',
		'venta_tkt1' => 'float',
		'cant_tkt2' => 'float',
		'costo_tkt2' => 'float',
		'fee_tkt2' => 'float',
		'utilidad_tkt2' => 'float',
		'venta_tkt2' => 'float',
		'cant_tkt3' => 'float',
		'costo_tkt3' => 'float',
		'fee_tkt3' => 'float',
		'utilidad_tkt3' => 'float',
		'venta_tkt3' => 'float',
		'cant_tkt4' => 'float',
		'costo_tkt4' => 'float',
		'fee_tkt4' => 'float',
		'utilidad_tkt4' => 'float',
		'venta_tkt4' => 'float',
		'fecha_release' => 'datetime',
		'total_cupo' => 'float',
		'total_factura' => 'float',
		'cobertura_tkt1' => 'float',
		'cobertura_tkt2' => 'float',
		'cobertura_tkt3' => 'float',
		'cobertura_tkt4' => 'float',
		'rg_tkt1' => 'float',
		'rg_tkt2' => 'float',
		'rg_tkt3' => 'float',
		'rg_tkt4' => 'float'
	];

	protected $fillable = [
		'fk_ciudad_id',
		'fecha_ida',
		'horario',
		'cant_tkt1',
		'costo_tkt1',
		'fee_tkt1',
		'utilidad_tkt1',
		'venta_tkt1',
		'cant_tkt2',
		'costo_tkt2',
		'fee_tkt2',
		'utilidad_tkt2',
		'venta_tkt2',
		'cant_tkt3',
		'costo_tkt3',
		'fee_tkt3',
		'utilidad_tkt3',
		'venta_tkt3',
		'cant_tkt4',
		'costo_tkt4',
		'fee_tkt4',
		'utilidad_tkt4',
		'venta_tkt4',
		'fk_moneda_id',
		'moneda_costo',
		'fecha_release',
		'total_cupo',
		'total_factura',
		'factuanro',
		'comentarios',
		'cobertura_tkt1',
		'cobertura_tkt2',
		'cobertura_tkt3',
		'cobertura_tkt4',
		'rg_tkt1',
		'rg_tkt2',
		'rg_tkt3',
		'rg_tkt4',
		'nombre_tkt1',
		'nombre_tkt2',
		'nombre_tkt3',
		'nombre_tkt4'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
