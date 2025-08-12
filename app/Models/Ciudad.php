<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ciudad
 * 
 * @property int $ciudad_id
 * @property bool $ciudad_activo
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property string $ciudad_nombre
 * @property string $ciudad_codigo
 * @property string $nombre_en
 * @property string $nombre_pg
 * @property bool $ap
 * @property string $codigo_tourico
 * @property int $codigo_amex
 * @property string $codigo_hb
 * @property string $codigo_ws1
 * @property string $codigo_ws2
 * @property string $codigo_ws3
 * @property string $codigo_ws4
 * @property string $codigo_ws5
 * @property float $latitud
 * @property float $longitud
 * 
 * @property Ciudad $ciudad
 * @property Pai $pai
 * @property Alojamiento|null $alojamiento
 * @property Collection|Canje[] $canjes
 * @property Collection|Ciudad[] $ciudads
 * @property Collection|Ciudadauxiliar[] $ciudadauxiliars
 * @property Collection|Ciudadxml[] $ciudadxmls
 * @property Collection|Cliente[] $clientes
 * @property Collection|Cupoaereo[] $cupoaereos
 * @property Collection|Cupotkt[] $cupotkts
 * @property Collection|Guium[] $guia
 * @property Collection|Iva[] $ivas
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Negocio[] $negocios
 * @property Collection|Pkditem[] $pkditems
 * @property Collection|Proveedor[] $proveedors
 * @property Collection|Producto[] $productos
 * @property Collection|Servicio[] $servicios
 * @property Collection|Tarifariocomision[] $tarifariocomisions
 *
 * @package App\Models
 */
class Ciudad extends Model
{
	protected $table = 'ciudad';
	protected $primaryKey = 'ciudad_id';
	public $timestamps = false;

	protected $casts = [
		'ciudad_activo' => 'bool',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'ap' => 'bool',
		'codigo_amex' => 'int',
		'latitud' => 'float',
		'longitud' => 'float'
	];

	protected $fillable = [
		'ciudad_activo',
		'fk_pais_id',
		'fk_ciudad_id',
		'ciudad_nombre',
		'ciudad_codigo',
		'nombre_en',
		'nombre_pg',
		'ap',
		'codigo_tourico',
		'codigo_amex',
		'codigo_hb',
		'codigo_ws1',
		'codigo_ws2',
		'codigo_ws3',
		'codigo_ws4',
		'codigo_ws5',
		'latitud',
		'longitud'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function pai()
	{
		return $this->belongsTo(Pai::class, 'fk_pais_id');
	}

	public function alojamiento()
	{
		return $this->hasOne(Alojamiento::class, 'fk_ciudad_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_ciudad_id');
	}

	public function ciudads()
	{
		return $this->hasMany(Ciudad::class, 'fk_ciudad_id');
	}

	public function ciudadauxiliars()
	{
		return $this->hasMany(Ciudadauxiliar::class, 'fk_ciudad_id');
	}

	public function ciudadxmls()
	{
		return $this->hasMany(Ciudadxml::class, 'fk_ciudad_id');
	}

	public function clientes()
	{
		return $this->hasMany(Cliente::class, 'fk_ciudad_id');
	}

	public function cupoaereos()
	{
		return $this->hasMany(Cupoaereo::class, 'destino2');
	}

	public function cupotkts()
	{
		return $this->hasMany(Cupotkt::class, 'fk_ciudad_id');
	}

	public function guia()
	{
		return $this->hasMany(Guium::class, 'fk_ciudad_id');
	}

	public function ivas()
	{
		return $this->hasMany(Iva::class, 'fk_ciudad_id');
	}

	public function modelofees()
	{
		return $this->hasMany(Modelofee::class, 'fk_ciudad_id');
	}

	public function negocios()
	{
		return $this->hasMany(Negocio::class, 'fk_ciudad_id');
	}

	public function pkditems()
	{
		return $this->hasMany(Pkditem::class, 'fk_ciudad_id');
	}

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'fk_ciudad_id');
	}

	public function productos()
	{
		return $this->belongsToMany(Producto::class, 'rel_productociudad', 'fk_ciudad_id', 'fk_producto_id')
					->withPivot('tipo');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_ciudad_id');
	}

	public function tarifariocomisions()
	{
		return $this->hasMany(Tarifariocomision::class, 'fk_ciudad_id');
	}
}
