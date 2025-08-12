<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ServicioNomina
 * 
 * @property int $servicio_nomina_id
 * @property int $fk_servicio_id
 * @property string $nombre
 * @property string $apellido
 * @property string $email
 * @property string $documento
 * @property string $nacionalidad
 * @property string $telefono
 * @property string $cuit
 * @property string $tipopax
 * @property string $edad
 * @property string $nacimiento
 * 
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class ServicioNomina extends Model
{
	protected $table = 'servicio_nomina';
	protected $primaryKey = 'servicio_nomina_id';
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int'
	];

	protected $fillable = [
		'fk_servicio_id',
		'nombre',
		'apellido',
		'email',
		'documento',
		'nacionalidad',
		'telefono',
		'cuit',
		'tipopax',
		'edad',
		'nacimiento'
	];

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}
}
