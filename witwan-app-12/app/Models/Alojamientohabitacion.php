<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Alojamientohabitacion
 * 
 * @property int $alojamientohabitacion_id
 * @property int $fk_producto_id
 * @property int $fk_tarifacategoria_id
 * @property string $textolibre
 * @property string $alojamientohabitacion_nombre
 * @property int $min_adultos
 * @property int|null $max_child
 * @property int|null $max_adultos
 * @property int|null $min_adultos_child
 * @property int|null $capacidad
 * @property int $max_adultos_child
 * @property int|null $orden
 * @property int $habilitar
 * 
 * @property Producto $producto
 * @property Tarifacategorium $tarifacategorium
 *
 * @package App\Models
 */
class Alojamientohabitacion extends Model
{
	protected $table = 'alojamientohabitacion';
	protected $primaryKey = 'alojamientohabitacion_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'min_adultos' => 'int',
		'max_child' => 'int',
		'max_adultos' => 'int',
		'min_adultos_child' => 'int',
		'capacidad' => 'int',
		'max_adultos_child' => 'int',
		'orden' => 'int',
		'habilitar' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'fk_tarifacategoria_id',
		'textolibre',
		'alojamientohabitacion_nombre',
		'min_adultos',
		'max_child',
		'max_adultos',
		'min_adultos_child',
		'capacidad',
		'max_adultos_child',
		'orden',
		'habilitar'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function tarifacategorium()
	{
		return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
	}
}
