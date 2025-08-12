<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Canje
 * 
 * @property int $canje_id
 * @property int $fk_sistema_id
 * @property int $fk_producto_id
 * @property string $fk_moneda_id
 * @property string $canje_contrato
 * @property Carbon $canje_inicio
 * @property int $fk_proveedor_id
 * @property int $fk_ciudad_id
 * @property int $fk_factura_id
 * @property int $canje_noches
 * @property float $canje_dinero
 * @property float $canje_utilizado
 * @property Carbon $canje_fin
 * @property string $observaciones
 * @property int $fk_usuario_id
 * @property Carbon $regdate
 * @property Carbon $um
 * 
 * @property Ciudad $ciudad
 * @property Factura $factura
 * @property Moneda $moneda
 * @property Producto $producto
 * @property Proveedor $proveedor
 * @property Sistema $sistema
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Canje extends Model
{
	protected $table = 'canje';
	protected $primaryKey = 'canje_id';
	public $timestamps = false;

	protected $casts = [
		'fk_sistema_id' => 'int',
		'fk_producto_id' => 'int',
		'canje_inicio' => 'datetime',
		'fk_proveedor_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fk_factura_id' => 'int',
		'canje_noches' => 'int',
		'canje_dinero' => 'float',
		'canje_utilizado' => 'float',
		'canje_fin' => 'datetime',
		'fk_usuario_id' => 'int',
		'regdate' => 'datetime',
		'um' => 'datetime'
	];

	protected $fillable = [
		'fk_sistema_id',
		'fk_producto_id',
		'fk_moneda_id',
		'canje_contrato',
		'canje_inicio',
		'fk_proveedor_id',
		'fk_ciudad_id',
		'fk_factura_id',
		'canje_noches',
		'canje_dinero',
		'canje_utilizado',
		'canje_fin',
		'observaciones',
		'fk_usuario_id',
		'regdate',
		'um'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'fk_factura_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
