<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Solicitud
 * 
 * @property int $solicitud_id
 * @property string $solicitud_nombre
 * @property string $solicitud_apellido
 * @property string $solicitud_email
 * @property string $solicitud_telefono
 * @property string $fk_idioma_id
 * @property string $solicitud_ciudad
 * @property string $solicitud_direccion
 * @property int $fk_pais_id
 * @property string $solicitud_celular
 * @property string $solicitud_fax
 * @property string $solicitud_clave
 * @property string $solicitud_sexo
 * @property string $solicitud_empresa
 * @property string $solicitud_rz
 * @property string $solicitud_legajo
 * @property int $fk_condicioniva_id
 * @property string $solicitud_direccionfiscal
 * @property string $solicitud_telefonoempresa
 * @property string $solicitud_faxempresa
 * @property string $solicitud_emailempresa
 * @property string $solicitud_ciudadempresa
 * @property int $fk_tipoclavefiscal_id
 * @property string $solicitud_clavefiscal
 * @property Carbon $solicitud_fecha
 * @property string $solicitud_status
 * @property string $solicitud_data
 *
 * @package App\Models
 */
class Solicitud extends Model
{
	protected $table = 'solicitud';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_condicioniva_id' => 'int',
		'fk_tipoclavefiscal_id' => 'int',
		'solicitud_fecha' => 'datetime'
	];

	protected $fillable = [
		'solicitud_id',
		'solicitud_nombre',
		'solicitud_apellido',
		'solicitud_email',
		'solicitud_telefono',
		'fk_idioma_id',
		'solicitud_ciudad',
		'solicitud_direccion',
		'fk_pais_id',
		'solicitud_celular',
		'solicitud_fax',
		'solicitud_clave',
		'solicitud_sexo',
		'solicitud_empresa',
		'solicitud_rz',
		'solicitud_legajo',
		'fk_condicioniva_id',
		'solicitud_direccionfiscal',
		'solicitud_telefonoempresa',
		'solicitud_faxempresa',
		'solicitud_emailempresa',
		'solicitud_ciudadempresa',
		'fk_tipoclavefiscal_id',
		'solicitud_clavefiscal',
		'solicitud_fecha',
		'solicitud_status',
		'solicitud_data'
	];
}
