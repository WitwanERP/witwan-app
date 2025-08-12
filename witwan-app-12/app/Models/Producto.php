<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Producto
 * 
 * @property int $producto_id
 * @property int $fk_usuario_id
 * @property int $fk_proveedor_id
 * @property int $fk_prestador_id
 * @property int $fk_submodulo_id
 * @property int $fk_sistema_id
 * @property int $fk_productogrupo_id
 * @property string $fk_tipoproducto_id
 * @property int $origen
 * @property int $destino
 * @property string $producto_nombre
 * @property string $producto_nombre_en
 * @property string $producto_nombre_pt
 * @property string $producto_descripcion
 * @property string $producto_descripcion_en
 * @property string $producto_descripcion_pt
 * @property int $habilitar
 * @property string $modotarifa
 * @property string $gmaps
 * @property bool $aparece_tarifario
 * @property string $disponibilidad
 * @property string $producto_codigo
 * @property bool $eliminar
 * @property bool $destacar
 * @property string $politica_cancelacion
 * @property string $destinos
 * 
 * @property Productogrupo $productogrupo
 * @property Proveedor $proveedor
 * @property Sistema $sistema
 * @property Submodulo $submodulo
 * @property Usuario $usuario
 * @property Collection|Aereo[] $aereos
 * @property Alojamiento|null $alojamiento
 * @property Collection|Alojamientohabitacion[] $alojamientohabitacions
 * @property Collection|Canje[] $canjes
 * @property Collection|Cupo[] $cupos
 * @property Cupoaereo|null $cupoaereo
 * @property Cupotkt|null $cupotkt
 * @property Excursion|null $excursion
 * @property Collection|Iva[] $ivas
 * @property Collection|Pkd[] $pkds
 * @property Collection|Pkdproducto[] $pkdproductos
 * @property ProductoExtra|null $producto_extra
 * @property Collection|Productogalerium[] $productogaleria
 * @property Collection|Alojamientofacilidad[] $alojamientofacilidads
 * @property Collection|Ciudad[] $ciudads
 * @property Collection|Servicio[] $servicios
 * @property Collection|Soldout[] $soldouts
 * @property Collection|Tarifacategorium[] $tarifacategoria
 * @property Collection|Tarifariocomision[] $tarifariocomisions
 * @property Traslado|null $traslado
 * @property Collection|Vigencium[] $vigencia
 *
 * @package App\Models
 */
class Producto extends Model
{
	protected $table = 'producto';
	protected $primaryKey = 'producto_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_proveedor_id' => 'int',
		'fk_prestador_id' => 'int',
		'fk_submodulo_id' => 'int',
		'fk_sistema_id' => 'int',
		'fk_productogrupo_id' => 'int',
		'origen' => 'int',
		'destino' => 'int',
		'habilitar' => 'int',
		'aparece_tarifario' => 'bool',
		'eliminar' => 'bool',
		'destacar' => 'bool'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_proveedor_id',
		'fk_prestador_id',
		'fk_submodulo_id',
		'fk_sistema_id',
		'fk_productogrupo_id',
		'fk_tipoproducto_id',
		'origen',
		'destino',
		'producto_nombre',
		'producto_nombre_en',
		'producto_nombre_pt',
		'producto_descripcion',
		'producto_descripcion_en',
		'producto_descripcion_pt',
		'habilitar',
		'modotarifa',
		'gmaps',
		'aparece_tarifario',
		'disponibilidad',
		'producto_codigo',
		'eliminar',
		'destacar',
		'politica_cancelacion',
		'destinos'
	];

	public function productogrupo()
	{
		return $this->belongsTo(Productogrupo::class, 'fk_productogrupo_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_prestador_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_tipoproducto_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function aereos()
	{
		return $this->hasMany(Aereo::class, 'fk_producto_id');
	}

	public function alojamiento()
	{
		return $this->hasOne(Alojamiento::class, 'fk_producto_id');
	}

	public function alojamientohabitacions()
	{
		return $this->hasMany(Alojamientohabitacion::class, 'fk_producto_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_producto_id');
	}

	public function cupos()
	{
		return $this->hasMany(Cupo::class, 'fk_producto_id');
	}

	public function cupoaereo()
	{
		return $this->hasOne(Cupoaereo::class, 'fk_producto_id');
	}

	public function cupotkt()
	{
		return $this->hasOne(Cupotkt::class, 'fk_producto_id');
	}

	public function excursion()
	{
		return $this->hasOne(Excursion::class, 'fk_producto_id');
	}

	public function ivas()
	{
		return $this->hasMany(Iva::class, 'fk_producto_id');
	}

	public function pkds()
	{
		return $this->hasMany(Pkd::class, 'fk_producto_id');
	}

	public function pkdproductos()
	{
		return $this->hasMany(Pkdproducto::class, 'fk_producto_id');
	}

	public function producto_extra()
	{
		return $this->hasOne(ProductoExtra::class, 'fk_producto_id');
	}

	public function productogaleria()
	{
		return $this->hasMany(Productogalerium::class, 'fk_producto_id');
	}

	public function alojamientofacilidads()
	{
		return $this->belongsToMany(Alojamientofacilidad::class, 'rel_productoalojamientofacilidad', 'fk_producto_id', 'fk_alojamientofacilidad_id');
	}

	public function ciudads()
	{
		return $this->belongsToMany(Ciudad::class, 'rel_productociudad', 'fk_producto_id', 'fk_ciudad_id')
					->withPivot('tipo');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_producto_id');
	}

	public function soldouts()
	{
		return $this->hasMany(Soldout::class, 'fk_producto_id');
	}

	public function tarifacategoria()
	{
		return $this->hasMany(Tarifacategorium::class, 'fk_producto_id');
	}

	public function tarifariocomisions()
	{
		return $this->hasMany(Tarifariocomision::class, 'fk_producto_id');
	}

	public function traslado()
	{
		return $this->hasOne(Traslado::class, 'fk_producto_id');
	}

	public function vigencia()
	{
		return $this->hasMany(Vigencium::class, 'fk_producto_id');
	}
}
