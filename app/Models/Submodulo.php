<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Submodulo
 *
 * @property string $tipoproducto_id
 * @property string $tipoproducto_nombre
 * @property int $tipoproducto_eventual
 * @property int $fk_plancuenta_id
 * @property int $cuenta_renta
 * @property int $fk_proveedor_id
 * @property int $tipoproducto_relacionado
 * @property string $tipoproducto_tipo
 * @property int $tipoproducto_activo
 * @property int|null $tipoproducto_migra
 * @property string $submodulo_id
 * @property string $submodulo_nombre
 * @property int $submodulo_receptivo
 * @property int $submodulo_mayorista
 * @property int $submodulo_minorista
 * @property int $submodulo_aereos
 * @property int $submodulo_orden
 * @property string $campoendocumento
 *
 * @property Plancuenta $plancuenta
 * @property Proveedor $proveedor
 * @property Collection|Alojamientofacilidad[] $alojamientofacilidads
 * @property Collection|Iva[] $ivas
 * @property Collection|Modelocomision[] $modelocomisions
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Pkditem[] $pkditems
 * @property Collection|Producto[] $productos
 * @property Collection|Servicio[] $servicios
 * @property Collection|Tarifacategorium[] $tarifacategoria
 * @property Collection|Tarifariocomision[] $tarifariocomisions
 *
 * @package App\Models
 */
class Submodulo extends Model
{
	protected $table = 'submodulo';
	protected $primaryKey = 'tipoproducto_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'tipoproducto_eventual' => 'int',
		'fk_plancuenta_id' => 'int',
		'cuenta_renta' => 'int',
		'fk_proveedor_id' => 'int',
		'tipoproducto_relacionado' => 'int',
		'tipoproducto_activo' => 'int',
		'tipoproducto_migra' => 'int',
		'submodulo_receptivo' => 'int',
		'submodulo_mayorista' => 'int',
		'submodulo_minorista' => 'int',
		'submodulo_aereos' => 'int',
		'submodulo_orden' => 'int'
	];

	protected $fillable = [
		'tipoproducto_nombre',
		'tipoproducto_eventual',
		'fk_plancuenta_id',
		'cuenta_renta',
		'fk_proveedor_id',
		'tipoproducto_relacionado',
		'tipoproducto_tipo',
		'tipoproducto_activo',
		'tipoproducto_migra',
		'submodulo_id',
		'submodulo_nombre',
		'submodulo_receptivo',
		'submodulo_mayorista',
		'submodulo_minorista',
		'submodulo_aereos',
		'submodulo_orden',
		'campoendocumento'
	];

	public function plancuenta()
	{
		return $this->belongsTo(Plancuenta::class, 'cuenta_renta');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function alojamientofacilidads()
	{
		return $this->hasMany(Alojamientofacilidad::class, 'fk_submodulo_id');
	}

	public function ivas()
	{
		return $this->hasMany(Iva::class, 'fk_submodulo_id');
	}

	public function modelocomisions()
	{
		return $this->hasMany(Modelocomision::class, 'fk_submodulo_id');
	}

	public function modelofees()
	{
		return $this->hasMany(Modelofee::class, 'fk_submodulo_id');
	}

	public function pkditems()
	{
		return $this->hasMany(Pkditem::class, 'fk_tipoproducto_id');
	}

	public function productos()
	{
		return $this->hasMany(Producto::class, 'fk_tipoproducto_id');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_tipoproducto_id');
	}

	public function tarifacategoria()
	{
		return $this->hasMany(Tarifacategorium::class, 'fk_submodulo_id');
	}

	public function tarifariocomisions()
	{
		return $this->hasMany(Tarifariocomision::class, 'fk_submodulo_id');
	}
}
