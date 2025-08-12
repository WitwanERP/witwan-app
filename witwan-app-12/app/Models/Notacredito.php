<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Notacredito
 * 
 * @property int $notacredito_id
 * @property int $fk_lotedocumento_id
 * @property string $statusfactura
 * @property Carbon $notacredito_fecha
 * @property string $notacredito_cae
 * @property Carbon $notacredito_vcae
 * @property string $notacredito_tipo
 * @property int $notacredito_sucursal
 * @property string $notacredito_nro
 * @property string $notacredito_conceptos_gravados
 * @property string $notacredito_conceptos_gravadosespecial
 * @property string $notacredito_conceptos_exentos
 * @property string $notacredito_conceptos_nogravados
 * @property float $notacredito_rgterrestres
 * @property float $notacredito_ivatur
 * @property float $notacredito_impuesto1
 * @property float $notacredito_impuesto2
 * @property float $notacredito_impuesto3
 * @property float $notacredito_impuesto4
 * @property float $notacredito_impuesto5
 * @property float $notacredito_ivatotal
 * @property float $notacredito_total
 * @property string $notacredito_tipo_cambio
 * @property int $fk_cliente_id
 * @property int $fk_usuario_id
 * @property int $fk_factura_id
 * @property string $remitofull
 * @property string $observaciones
 * @property string $notacredito_conceptos
 * @property string $fk_moneda_id
 * @property int $fk_file_id
 * @property int $codigo_comprobante
 * 
 * @property Cliente $cliente
 * @property Factura $factura
 * @property Reserva $reserva
 * @property Usuario $usuario
 * @property Collection|Facturaaerolinea[] $facturaaerolineas
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notadebito[] $notadebitos
 * @property Collection|RelFacturarecibo[] $rel_facturarecibos
 *
 * @package App\Models
 */
class Notacredito extends Model
{
	protected $table = 'notacredito';
	protected $primaryKey = 'notacredito_id';
	public $timestamps = false;

	protected $casts = [
		'fk_lotedocumento_id' => 'int',
		'notacredito_fecha' => 'datetime',
		'notacredito_vcae' => 'datetime',
		'notacredito_sucursal' => 'int',
		'notacredito_rgterrestres' => 'float',
		'notacredito_ivatur' => 'float',
		'notacredito_impuesto1' => 'float',
		'notacredito_impuesto2' => 'float',
		'notacredito_impuesto3' => 'float',
		'notacredito_impuesto4' => 'float',
		'notacredito_impuesto5' => 'float',
		'notacredito_ivatotal' => 'float',
		'notacredito_total' => 'float',
		'fk_cliente_id' => 'int',
		'fk_usuario_id' => 'int',
		'fk_factura_id' => 'int',
		'fk_file_id' => 'int',
		'codigo_comprobante' => 'int'
	];

	protected $fillable = [
		'fk_lotedocumento_id',
		'statusfactura',
		'notacredito_fecha',
		'notacredito_cae',
		'notacredito_vcae',
		'notacredito_tipo',
		'notacredito_sucursal',
		'notacredito_nro',
		'notacredito_conceptos_gravados',
		'notacredito_conceptos_gravadosespecial',
		'notacredito_conceptos_exentos',
		'notacredito_conceptos_nogravados',
		'notacredito_rgterrestres',
		'notacredito_ivatur',
		'notacredito_impuesto1',
		'notacredito_impuesto2',
		'notacredito_impuesto3',
		'notacredito_impuesto4',
		'notacredito_impuesto5',
		'notacredito_ivatotal',
		'notacredito_total',
		'notacredito_tipo_cambio',
		'fk_cliente_id',
		'fk_usuario_id',
		'fk_factura_id',
		'remitofull',
		'observaciones',
		'notacredito_conceptos',
		'fk_moneda_id',
		'fk_file_id',
		'codigo_comprobante'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'fk_factura_id');
	}

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function facturaaerolineas()
	{
		return $this->hasMany(Facturaaerolinea::class, 'fk_notacredito_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_notacredito_id');
	}

	public function notadebitos()
	{
		return $this->hasMany(Notadebito::class, 'fk_notacredito_id');
	}

	public function rel_facturarecibos()
	{
		return $this->hasMany(RelFacturarecibo::class, 'fk_notacredito_id');
	}
}
