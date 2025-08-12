<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Destacado
 * 
 * @property int $destacado_id
 * @property string $destacado_nombre
 * @property int $fk_producto_id
 * @property int $fk_vigencia_id
 * @property int $fk_tarifa_id
 * @property string $fk_moneda_id
 * @property string $costo_pkd
 * @property string $imagen
 *
 * @package App\Models
 */
class Destacado extends Model
{
	protected $table = 'destacado';
	protected $primaryKey = 'destacado_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_vigencia_id' => 'int',
		'fk_tarifa_id' => 'int'
	];

	protected $fillable = [
		'destacado_nombre',
		'fk_producto_id',
		'fk_vigencia_id',
		'fk_tarifa_id',
		'fk_moneda_id',
		'costo_pkd',
		'imagen'
	];
}
