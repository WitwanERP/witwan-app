<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Recibo
 * 
 * @property int $recibo_id
 * @property string $recibo_tipo
 * @property string $recibo_nro
 * @property Carbon $fecha
 * @property int $fk_cliente_id
 * @property string $fk_moneda_id
 * @property float $monto
 * @property int $fk_usuario_id
 * @property string $statusrecibo
 * @property int $actualiza
 * @property string $observaciones
 * @property int $automatico
 * 
 * @property Cliente $cliente
 * @property Moneda $moneda
 * @property Usuario $usuario
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Factura[] $facturas
 * @property RelFilerecibo|null $rel_filerecibo
 *
 * @package App\Models
 */
class Recibo extends Model
{
	protected $table = 'recibo';
	protected $primaryKey = 'recibo_id';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime',
		'fk_cliente_id' => 'int',
		'monto' => 'float',
		'fk_usuario_id' => 'int',
		'actualiza' => 'int',
		'automatico' => 'int'
	];

	protected $fillable = [
		'recibo_tipo',
		'recibo_nro',
		'fecha',
		'fk_cliente_id',
		'fk_moneda_id',
		'monto',
		'fk_usuario_id',
		'statusrecibo',
		'actualiza',
		'observaciones',
		'automatico'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_recibo_id');
	}

	public function facturas()
	{
		return $this->belongsToMany(Factura::class, 'rel_facturarecibo', 'fk_recibo_id', 'fk_factura_id')
					->withPivot('rel_facturarecibo_id', 'fk_ordenadmin_id', 'fk_notacredito_id', 'monto', 'fecha');
	}

	public function rel_filerecibo()
	{
		return $this->hasOne(RelFilerecibo::class, 'fk_recibo_id');
	}
}
