<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Notadebito
 * 
 * @property int $notadebito_id
 * @property int $fk_lotedocumento_id
 * @property string $statusfactura
 * @property Carbon $notadebito_fecha
 * @property string $notadebito_cae
 * @property Carbon $notadebito_vcae
 * @property string $notadebito_tipo
 * @property int $notadebito_sucursal
 * @property string $notadebito_nro
 * @property string $notadebito_conceptos_gravados
 * @property string $notadebito_conceptos_gravadosespecial
 * @property string $notadebito_conceptos_exentos
 * @property string $notadebito_conceptos_nogravados
 * @property float $notadebito_rgterrestres
 * @property float $notadebito_ivatur
 * @property float $notadebito_impuesto1
 * @property float $notadebito_impuesto2
 * @property float $notadebito_impuesto3
 * @property float $notadebito_impuesto4
 * @property float $notadebito_impuesto5
 * @property float $notadebito_ivatotal
 * @property float $notadebito_total
 * @property string $notadebito_tipo_cambio
 * @property int $fk_cliente_id
 * @property int $fk_usuario_id
 * @property int $fk_notacredito_id
 * @property string $remitofull
 * @property string $observaciones
 * @property string $notadebito_conceptos
 * @property string $fk_moneda_id
 * @property int $codigo_comprobante
 * 
 * @property Cliente $cliente
 * @property Moneda $moneda
 * @property Notacredito $notacredito
 * @property Usuario $usuario
 * @property Collection|Movimiento[] $movimientos
 *
 * @package App\Models
 */
class Notadebito extends Model
{
	protected $table = 'notadebito';
	protected $primaryKey = 'notadebito_id';
	public $timestamps = false;

	protected $casts = [
		'fk_lotedocumento_id' => 'int',
		'notadebito_fecha' => 'datetime',
		'notadebito_vcae' => 'datetime',
		'notadebito_sucursal' => 'int',
		'notadebito_rgterrestres' => 'float',
		'notadebito_ivatur' => 'float',
		'notadebito_impuesto1' => 'float',
		'notadebito_impuesto2' => 'float',
		'notadebito_impuesto3' => 'float',
		'notadebito_impuesto4' => 'float',
		'notadebito_impuesto5' => 'float',
		'notadebito_ivatotal' => 'float',
		'notadebito_total' => 'float',
		'fk_cliente_id' => 'int',
		'fk_usuario_id' => 'int',
		'fk_notacredito_id' => 'int',
		'codigo_comprobante' => 'int'
	];

	protected $fillable = [
		'fk_lotedocumento_id',
		'statusfactura',
		'notadebito_fecha',
		'notadebito_cae',
		'notadebito_vcae',
		'notadebito_tipo',
		'notadebito_sucursal',
		'notadebito_nro',
		'notadebito_conceptos_gravados',
		'notadebito_conceptos_gravadosespecial',
		'notadebito_conceptos_exentos',
		'notadebito_conceptos_nogravados',
		'notadebito_rgterrestres',
		'notadebito_ivatur',
		'notadebito_impuesto1',
		'notadebito_impuesto2',
		'notadebito_impuesto3',
		'notadebito_impuesto4',
		'notadebito_impuesto5',
		'notadebito_ivatotal',
		'notadebito_total',
		'notadebito_tipo_cambio',
		'fk_cliente_id',
		'fk_usuario_id',
		'fk_notacredito_id',
		'remitofull',
		'observaciones',
		'notadebito_conceptos',
		'fk_moneda_id',
		'codigo_comprobante'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function notacredito()
	{
		return $this->belongsTo(Notacredito::class, 'fk_notacredito_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_notadebito_id');
	}
}
