<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Factura
 * 
 * @property int $factura_id
 * @property string $conceptos
 * @property string $statusfactura
 * @property Carbon $factura_fecha
 * @property string $factura_cae
 * @property Carbon $factura_vcae
 * @property string $factura_tipo
 * @property int $factura_sucursal
 * @property string $factura_nro
 * @property int $factura_numero
 * @property string $factura_conceptos_gravados
 * @property string $factura_conceptos_gravadosespecial
 * @property string $factura_conceptos_exentos
 * @property string $factura_conceptos_nogravados
 * @property float $factura_rgaereos
 * @property float $factura_rgterrestres
 * @property float $factura_ivatur
 * @property float $factura_impuesto1
 * @property float $factura_impuesto2
 * @property float $factura_impuesto3
 * @property float $factura_impuesto4
 * @property float $factura_impuesto5
 * @property float $factura_total
 * @property float $factura_ivatotal
 * @property string $factura_tipo_cambio
 * @property int $fk_cliente_id
 * @property int $fk_usuario_id
 * @property int $fk_file_id
 * @property string $remitofull
 * @property string $observaciones
 * @property int $fk_lotedocumento_id
 * @property string $fk_moneda_id
 * @property Carbon $vencimiento_pago
 * @property int $codigo_comprobante
 * @property string $factura_extra
 * 
 * @property Cliente $cliente
 * @property Moneda $moneda
 * @property Reserva $reserva
 * @property Usuario $usuario
 * @property Collection|Canje[] $canjes
 * @property Collection|FacturaEnvio[] $factura_envios
 * @property Facturaaerolinea|null $facturaaerolinea
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notacredito[] $notacreditos
 * @property Collection|Recibo[] $recibos
 * @property Collection|RelFilefactura[] $rel_filefacturas
 *
 * @package App\Models
 */
class Factura extends Model
{
	protected $table = 'factura';
	protected $primaryKey = 'factura_id';
	public $timestamps = false;

	protected $casts = [
		'factura_fecha' => 'datetime',
		'factura_vcae' => 'datetime',
		'factura_sucursal' => 'int',
		'factura_numero' => 'int',
		'factura_rgaereos' => 'float',
		'factura_rgterrestres' => 'float',
		'factura_ivatur' => 'float',
		'factura_impuesto1' => 'float',
		'factura_impuesto2' => 'float',
		'factura_impuesto3' => 'float',
		'factura_impuesto4' => 'float',
		'factura_impuesto5' => 'float',
		'factura_total' => 'float',
		'factura_ivatotal' => 'float',
		'fk_cliente_id' => 'int',
		'fk_usuario_id' => 'int',
		'fk_file_id' => 'int',
		'fk_lotedocumento_id' => 'int',
		'vencimiento_pago' => 'datetime',
		'codigo_comprobante' => 'int'
	];

	protected $fillable = [
		'conceptos',
		'statusfactura',
		'factura_fecha',
		'factura_cae',
		'factura_vcae',
		'factura_tipo',
		'factura_sucursal',
		'factura_nro',
		'factura_numero',
		'factura_conceptos_gravados',
		'factura_conceptos_gravadosespecial',
		'factura_conceptos_exentos',
		'factura_conceptos_nogravados',
		'factura_rgaereos',
		'factura_rgterrestres',
		'factura_ivatur',
		'factura_impuesto1',
		'factura_impuesto2',
		'factura_impuesto3',
		'factura_impuesto4',
		'factura_impuesto5',
		'factura_total',
		'factura_ivatotal',
		'factura_tipo_cambio',
		'fk_cliente_id',
		'fk_usuario_id',
		'fk_file_id',
		'remitofull',
		'observaciones',
		'fk_lotedocumento_id',
		'fk_moneda_id',
		'vencimiento_pago',
		'codigo_comprobante',
		'factura_extra'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_factura_id');
	}

	public function factura_envios()
	{
		return $this->hasMany(FacturaEnvio::class, 'fk_factura_id');
	}

	public function facturaaerolinea()
	{
		return $this->hasOne(Facturaaerolinea::class, 'facturaaerolinea_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_factura_id');
	}

	public function notacreditos()
	{
		return $this->hasMany(Notacredito::class, 'fk_factura_id');
	}

	public function recibos()
	{
		return $this->belongsToMany(Recibo::class, 'rel_facturarecibo', 'fk_factura_id', 'fk_recibo_id')
					->withPivot('rel_facturarecibo_id', 'fk_ordenadmin_id', 'fk_notacredito_id', 'monto', 'fecha');
	}

	public function rel_filefacturas()
	{
		return $this->hasMany(RelFilefactura::class, 'fk_factura_id');
	}
}
