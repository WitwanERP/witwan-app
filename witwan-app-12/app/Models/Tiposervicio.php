<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Tiposervicio
 * 
 * @property string $tiposervicio_id
 * @property string $tiposervicio_nombre
 * @property int $fk_plancuenta_id
 * @property int $cuenta_renta
 * @property int $tiposervicio_eventual
 * @property int $tiposervicio_activo
 * @property string $tiposervicio_tipo
 * @property int $fk_proveedor_id
 * @property int $tiposervicio_esrelacionado
 * @property int $tiposervicio_referencia
 * @property int $tiposervicio_orden
 *
 * @package App\Models
 */
class Tiposervicio extends Model
{
	protected $table = 'tiposervicio';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_plancuenta_id' => 'int',
		'cuenta_renta' => 'int',
		'tiposervicio_eventual' => 'int',
		'tiposervicio_activo' => 'int',
		'fk_proveedor_id' => 'int',
		'tiposervicio_esrelacionado' => 'int',
		'tiposervicio_referencia' => 'int',
		'tiposervicio_orden' => 'int'
	];

	protected $fillable = [
		'tiposervicio_id',
		'tiposervicio_nombre',
		'fk_plancuenta_id',
		'cuenta_renta',
		'tiposervicio_eventual',
		'tiposervicio_activo',
		'tiposervicio_tipo',
		'fk_proveedor_id',
		'tiposervicio_esrelacionado',
		'tiposervicio_referencia',
		'tiposervicio_orden'
	];
}
