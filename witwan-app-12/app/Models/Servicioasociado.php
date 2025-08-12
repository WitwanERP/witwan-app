<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Servicioasociado
 * 
 * @property int $servicioasociado_id
 * @property string $servicioasociado_nombre
 * @property string $servicioasociado_tipo
 * @property float $servicioasociado_porcentaje
 * @property float $servicioasociado_fijo
 * @property int $fk_proveedor_id
 *
 * @package App\Models
 */
class Servicioasociado extends Model
{
	protected $table = 'servicioasociado';
	protected $primaryKey = 'servicioasociado_id';
	public $timestamps = false;

	protected $casts = [
		'servicioasociado_porcentaje' => 'float',
		'servicioasociado_fijo' => 'float',
		'fk_proveedor_id' => 'int'
	];

	protected $fillable = [
		'servicioasociado_nombre',
		'servicioasociado_tipo',
		'servicioasociado_porcentaje',
		'servicioasociado_fijo',
		'fk_proveedor_id'
	];
}
