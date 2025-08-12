<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Plancuentum
 * 
 * @property int $plancuenta_id
 * @property string $fk_moneda_id
 * @property string $plancuenta_nombre
 * @property int $fk_plancuenta_id
 * @property string $plancuenta_codigo
 * @property int $plancuenta_titulo
 * @property int $plancuenta_g
 * @property string $plancuenta_imp
 * @property string $plancuenta_aju
 * @property string $plancuenta_cli
 * @property string $plancuenta_pro
 * @property string $plancuenta_res
 * @property string $plancuenta_total
 * @property string $plancuenta_saldo
 * @property bool $cuentagasto
 * @property int $arqueo
 * @property int $conceptos_adicionales
 * @property int $cartera
 * @property int $requiereanalitico
 * @property int $reportegastos
 * @property int $ajusteinflacion
 * 
 * @property Moneda $moneda
 * @property Plancuentum $plancuentum
 * @property Collection|Conciliabanco[] $conciliabancos
 * @property Collection|Facturaproveedor[] $facturaproveedors
 * @property Collection|Formapago[] $formapagos
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Plancuentum[] $plancuenta
 * @property Collection|Submodulo[] $submodulos
 * @property Collection|Usuariocomision[] $usuariocomisions
 *
 * @package App\Models
 */
class Plancuentum extends Model
{
	protected $table = 'plancuenta';
	protected $primaryKey = 'plancuenta_id';
	public $timestamps = false;

	protected $casts = [
		'fk_plancuenta_id' => 'int',
		'plancuenta_titulo' => 'int',
		'plancuenta_g' => 'int',
		'cuentagasto' => 'bool',
		'arqueo' => 'int',
		'conceptos_adicionales' => 'int',
		'cartera' => 'int',
		'requiereanalitico' => 'int',
		'reportegastos' => 'int',
		'ajusteinflacion' => 'int'
	];

	protected $fillable = [
		'fk_moneda_id',
		'plancuenta_nombre',
		'fk_plancuenta_id',
		'plancuenta_codigo',
		'plancuenta_titulo',
		'plancuenta_g',
		'plancuenta_imp',
		'plancuenta_aju',
		'plancuenta_cli',
		'plancuenta_pro',
		'plancuenta_res',
		'plancuenta_total',
		'plancuenta_saldo',
		'cuentagasto',
		'arqueo',
		'conceptos_adicionales',
		'cartera',
		'requiereanalitico',
		'reportegastos',
		'ajusteinflacion'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function plancuentum()
	{
		return $this->belongsTo(Plancuentum::class, 'fk_plancuenta_id');
	}

	public function conciliabancos()
	{
		return $this->hasMany(Conciliabanco::class, 'fk_plancuenta_id');
	}

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_plancuenta_id');
	}

	public function formapagos()
	{
		return $this->hasMany(Formapago::class, 'fk_plancuenta_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_plancuenta_id');
	}

	public function plancuenta()
	{
		return $this->hasMany(Plancuentum::class, 'fk_plancuenta_id');
	}

	public function submodulos()
	{
		return $this->hasMany(Submodulo::class, 'cuenta_renta');
	}

	public function usuariocomisions()
	{
		return $this->hasMany(Usuariocomision::class, 'fk_plancuenta_id');
	}
}
