<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Alojamiento
 * 
 * @property int $fk_producto_id
 * @property int $fk_alojamientotipo_id
 * @property int $fk_hotelcategoria_id
 * @property int $fk_ciudad_id
 * @property string $alojamiento_descripcion
 * @property string $alojamiento_descripcion_en
 * @property string $zona
 * @property int $capacidad
 * @property int $tripulacion
 * @property float $ancho
 * @property float $largo
 * @property float $velocidad
 * @property string $tonelaje
 * @property int|null $venc_post_reserva
 * @property int|null $venc_pre_checkin
 * @property string $telefono
 * @property string $descripcion_en
 * @property string|null $descripcion_pg
 * @property string $fk_moneda_id
 * @property string $subcategorias
 * @property string $ubicacion
 * @property string $politica_edades
 * @property string $politica_edades_en
 * @property string $politica_edades_pg
 * @property Carbon|null $hora_checkin
 * @property Carbon|null $hora_checkout
 * @property string $bases
 * @property int $edad_menores
 * @property string $dropoff
 * 
 * @property Alojamientotipo $alojamientotipo
 * @property Ciudad $ciudad
 * @property Hotelcategorium $hotelcategorium
 * @property Moneda $moneda
 * @property Producto $producto
 *
 * @package App\Models
 */
class Alojamiento extends Model
{
	protected $table = 'alojamiento';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_alojamientotipo_id' => 'int',
		'fk_hotelcategoria_id' => 'int',
		'fk_ciudad_id' => 'int',
		'capacidad' => 'int',
		'tripulacion' => 'int',
		'ancho' => 'float',
		'largo' => 'float',
		'velocidad' => 'float',
		'venc_post_reserva' => 'int',
		'venc_pre_checkin' => 'int',
		'hora_checkin' => 'datetime',
		'hora_checkout' => 'datetime',
		'edad_menores' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'fk_alojamientotipo_id',
		'fk_hotelcategoria_id',
		'fk_ciudad_id',
		'alojamiento_descripcion',
		'alojamiento_descripcion_en',
		'zona',
		'capacidad',
		'tripulacion',
		'ancho',
		'largo',
		'velocidad',
		'tonelaje',
		'venc_post_reserva',
		'venc_pre_checkin',
		'telefono',
		'descripcion_en',
		'descripcion_pg',
		'fk_moneda_id',
		'subcategorias',
		'ubicacion',
		'politica_edades',
		'politica_edades_en',
		'politica_edades_pg',
		'hora_checkin',
		'hora_checkout',
		'bases',
		'edad_menores',
		'dropoff'
	];

	public function alojamientotipo()
	{
		return $this->belongsTo(Alojamientotipo::class, 'fk_alojamientotipo_id');
	}

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function hotelcategorium()
	{
		return $this->belongsTo(Hotelcategorium::class, 'fk_hotelcategoria_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
