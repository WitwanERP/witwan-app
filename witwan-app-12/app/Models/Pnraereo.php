<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pnraereo
 * 
 * @property int $pnraereo_id
 * @property string $ciudad_gds
 * @property string $office_gds
 * @property string $user_gds
 * @property int $fk_proveedor_id
 * @property string $ciudades
 * @property string $archivogds
 * @property string $pnraereo_tipopax
 * @property string $pnraereo_tipo
 * @property string $pnraereo_nombre
 * @property string $pnraereo_apellido
 * @property int $fk_ocupacion_id
 * @property int $fk_aerolinea_id
 * @property string $pnraereo_ruta
 * @property Carbon $pnraereo_fechaemision
 * @property Carbon $pnraereo_fechavuelo
 * @property string $pnraereo_tkt
 * @property string $pnraereo_reemision
 * @property float $pnraereo_tarifa
 * @property float $pnraereo_roe
 * @property float $pnraereo_equivalente
 * @property string $fk_moneda_id
 * @property float $pnraereo_comision_old
 * @property float $pnraereo_comision
 * @property float $pnraereo_montocomision
 * @property float $pnraereo_over
 * @property float $pnraereo_porcentajeover
 * @property string $pnraereo_porcentajetotalover
 * @property float $pnraereo_dnt
 * @property float $pnraereo_iva
 * @property float $pnraereo_qn
 * @property float $pnraereo_queue
 * @property float $pnraereo_CO
 * @property float $pnraereo_impuestos
 * @property string $pnraereo_formapago
 * @property float $pnraereo_cash
 * @property float $pnraereo_pagotarjeta
 * @property string $pnraereo_tarjeta
 * @property string $pnraereo_tourcode
 * @property string $pnraereo_bt
 * @property string $pnraereo_comentarios
 * @property int $pnraereo_void
 * @property Carbon $pnraereo_voiddate
 * @property float $pnraereo_fee
 * @property string $fk_gds_id
 * @property string $fk_agenteemisor_id
 * @property string $pnraereo_cruzar
 * @property Carbon $regdate
 * @property float $porcentajecomision
 * @property float $porcentajetotalcomision
 * @property float $comisioncliente
 * @property string $desestimado
 * @property string $conciliadobsp
 * @property string $team
 * @property int $internacional
 * @property string $codigo_reserva
 * @property string $codigo_recloc
 * @property float $pnraereo_impuestopais
 * @property float $pnraereo_rgqatar
 * @property string $pricing_code
 * @property int $is_ndc
 * @property string $extra_info
 * @property int $version
 * @property bool $es_airplus
 * 
 * @property Aerolinea $aerolinea
 * @property Gd $gd
 * @property Moneda $moneda
 * @property Proveedor $proveedor
 * @property Servicio $servicio
 * @property Collection|Pnrremark[] $pnrremarks
 * @property Collection|Pnrsegment[] $pnrsegments
 *
 * @package App\Models
 */
class Pnraereo extends Model
{
	protected $table = 'pnraereo';
	protected $primaryKey = 'pnraereo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'fk_ocupacion_id' => 'int',
		'fk_aerolinea_id' => 'int',
		'pnraereo_fechaemision' => 'datetime',
		'pnraereo_fechavuelo' => 'datetime',
		'pnraereo_tarifa' => 'float',
		'pnraereo_roe' => 'float',
		'pnraereo_equivalente' => 'float',
		'pnraereo_comision_old' => 'float',
		'pnraereo_comision' => 'float',
		'pnraereo_montocomision' => 'float',
		'pnraereo_over' => 'float',
		'pnraereo_porcentajeover' => 'float',
		'pnraereo_dnt' => 'float',
		'pnraereo_iva' => 'float',
		'pnraereo_qn' => 'float',
		'pnraereo_queue' => 'float',
		'pnraereo_CO' => 'float',
		'pnraereo_impuestos' => 'float',
		'pnraereo_cash' => 'float',
		'pnraereo_pagotarjeta' => 'float',
		'pnraereo_void' => 'int',
		'pnraereo_voiddate' => 'datetime',
		'pnraereo_fee' => 'float',
		'regdate' => 'datetime',
		'porcentajecomision' => 'float',
		'porcentajetotalcomision' => 'float',
		'comisioncliente' => 'float',
		'internacional' => 'int',
		'pnraereo_impuestopais' => 'float',
		'pnraereo_rgqatar' => 'float',
		'is_ndc' => 'int',
		'version' => 'int',
		'es_airplus' => 'bool'
	];

	protected $fillable = [
		'ciudad_gds',
		'office_gds',
		'user_gds',
		'fk_proveedor_id',
		'ciudades',
		'archivogds',
		'pnraereo_tipopax',
		'pnraereo_tipo',
		'pnraereo_nombre',
		'pnraereo_apellido',
		'fk_ocupacion_id',
		'fk_aerolinea_id',
		'pnraereo_ruta',
		'pnraereo_fechaemision',
		'pnraereo_fechavuelo',
		'pnraereo_tkt',
		'pnraereo_reemision',
		'pnraereo_tarifa',
		'pnraereo_roe',
		'pnraereo_equivalente',
		'fk_moneda_id',
		'pnraereo_comision_old',
		'pnraereo_comision',
		'pnraereo_montocomision',
		'pnraereo_over',
		'pnraereo_porcentajeover',
		'pnraereo_porcentajetotalover',
		'pnraereo_dnt',
		'pnraereo_iva',
		'pnraereo_qn',
		'pnraereo_queue',
		'pnraereo_CO',
		'pnraereo_impuestos',
		'pnraereo_formapago',
		'pnraereo_cash',
		'pnraereo_pagotarjeta',
		'pnraereo_tarjeta',
		'pnraereo_tourcode',
		'pnraereo_bt',
		'pnraereo_comentarios',
		'pnraereo_void',
		'pnraereo_voiddate',
		'pnraereo_fee',
		'fk_gds_id',
		'fk_agenteemisor_id',
		'pnraereo_cruzar',
		'regdate',
		'porcentajecomision',
		'porcentajetotalcomision',
		'comisioncliente',
		'desestimado',
		'conciliadobsp',
		'team',
		'internacional',
		'codigo_reserva',
		'codigo_recloc',
		'pnraereo_impuestopais',
		'pnraereo_rgqatar',
		'pricing_code',
		'is_ndc',
		'extra_info',
		'version',
		'es_airplus'
	];

	public function aerolinea()
	{
		return $this->belongsTo(Aerolinea::class, 'fk_aerolinea_id');
	}

	public function gd()
	{
		return $this->belongsTo(Gd::class, 'fk_gds_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}

	public function pnrremarks()
	{
		return $this->hasMany(Pnrremark::class, 'fk_pnraereo_id');
	}

	public function pnrsegments()
	{
		return $this->hasMany(Pnrsegment::class, 'fk_pnraereo_id');
	}
}
