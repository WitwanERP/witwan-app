<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Facturaproveedor
 *
 * @property int $facturaproveedor_id
 * @property string $facturaproveedor_nro
 * @property string $facturaproveedor_tipodocumento
 * @property string $facturaproveedor_tipofactura
 * @property int $fk_proveedor_id
 * @property Carbon $fecha
 * @property Carbon $fechacontable
 * @property Carbon $vencimiento
 * @property string $fk_moneda_id
 * @property float $montoexento
 * @property float $montonocomputable
 * @property float $montoespecial
 * @property float $montogeneral
 * @property float $monto27
 * @property float $monto25
 * @property float $ivatotal
 * @property float $retencioniva
 * @property float $retencioniibb
 * @property float $percepcioniva
 * @property float $percepcioniibb
 * @property float $retencionganancias
 * @property float $percepcionganancias
 * @property float $cotizacion
 * @property float $rg3450
 * @property float $otrosimpuestos
 * @property float $montototal
 * @property float $ivatur
 * @property string $descripcion
 * @property Carbon $fechacarga
 * @property string $tipomovimiento
 * @property int $fk_plancuenta_id
 * @property string $imputacion
 * @property int $fk_usuario_id
 * @property int $fk_proyecto_id
 * @property string $adicionales
 * @property string $tiporedondeo
 * @property string $electronica
 * @property string $archivo
 * @property int $fk_itemgasto_id
 * @property int $deesc
 * @property int $splitted
 *
 * @property Itemgasto $itemgasto
 * @property Moneda $moneda
 * @property Plancuenta $plancuenta
 * @property Proveedor $proveedor
 * @property Proyecto $proyecto
 * @property Usuario $usuario
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|RelFacturaproveedorocupacion[] $rel_facturaproveedorocupacions
 *
 * @package App\Models
 */
class Facturaproveedor extends Model
{
	protected $table = 'facturaproveedor';
	protected $primaryKey = 'facturaproveedor_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'fecha' => 'datetime',
		'fechacontable' => 'datetime',
		'vencimiento' => 'datetime',
		'montoexento' => 'float',
		'montonocomputable' => 'float',
		'montoespecial' => 'float',
		'montogeneral' => 'float',
		'monto27' => 'float',
		'monto25' => 'float',
		'ivatotal' => 'float',
		'retencioniva' => 'float',
		'retencioniibb' => 'float',
		'percepcioniva' => 'float',
		'percepcioniibb' => 'float',
		'retencionganancias' => 'float',
		'percepcionganancias' => 'float',
		'cotizacion' => 'float',
		'rg3450' => 'float',
		'otrosimpuestos' => 'float',
		'montototal' => 'float',
		'ivatur' => 'float',
		'fechacarga' => 'datetime',
		'fk_plancuenta_id' => 'int',
		'fk_usuario_id' => 'int',
		'fk_proyecto_id' => 'int',
		'fk_itemgasto_id' => 'int',
		'deesc' => 'int',
		'splitted' => 'int'
	];

	protected $fillable = [
		'facturaproveedor_nro',
		'facturaproveedor_tipodocumento',
		'facturaproveedor_tipofactura',
		'fk_proveedor_id',
		'fecha',
		'fechacontable',
		'vencimiento',
		'fk_moneda_id',
		'montoexento',
		'montonocomputable',
		'montoespecial',
		'montogeneral',
		'monto27',
		'monto25',
		'ivatotal',
		'retencioniva',
		'retencioniibb',
		'percepcioniva',
		'percepcioniibb',
		'retencionganancias',
		'percepcionganancias',
		'cotizacion',
		'rg3450',
		'otrosimpuestos',
		'montototal',
		'ivatur',
		'descripcion',
		'fechacarga',
		'tipomovimiento',
		'fk_plancuenta_id',
		'imputacion',
		'fk_usuario_id',
		'fk_proyecto_id',
		'adicionales',
		'tiporedondeo',
		'electronica',
		'archivo',
		'fk_itemgasto_id',
		'deesc',
		'splitted'
	];

	public function itemgasto()
	{
		return $this->belongsTo(Itemgasto::class, 'fk_itemgasto_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function plancuenta()
	{
		return $this->belongsTo(Plancuenta::class, 'fk_plancuenta_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function proyecto()
	{
		return $this->belongsTo(Proyecto::class, 'fk_proyecto_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_facturaproveedor_id');
	}

	public function rel_facturaproveedorocupacions()
	{
		return $this->hasMany(RelFacturaproveedorocupacion::class, 'fk_facturaproveedor_id');
	}
}
