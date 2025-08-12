<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pasajero
 * 
 * @property int $pasajero_id
 * @property string $pasajero_nombre
 * @property string $pasajero_apellido
 * @property string $pasajero_apodo
 * @property string $pasajero_nacionalidad
 * @property string $pasajero_nacimiento
 * @property string $pasajero_sexo
 * @property string $pasajero_email
 * @property string $pasajero_clave
 * @property string $pasajero_password
 * @property int $fk_cliente_id
 * @property int $mostrar_ficha
 * @property string $cargo
 * @property string $cliente_asociado
 * @property int $fk_usuario_vendedor
 * @property string $fk_usuario_promotor1
 * @property string $freelance
 * @property string $habilita
 * @property string $pasajero_foto
 * @property string $tipodoc
 * @property string $nrodoc
 * @property int $emisordoc
 * @property string $emisorfecha
 * @property string $vencimientodoc
 * @property string $pasajero_direccionfiscal
 * @property string $pasajero_codigopostal
 * @property int $fk_pais_id
 * @property string $pasajero_ciudad
 * @property int $fk_ciudad_id
 * @property int $fk_tipoclavefiscal_id
 * @property string $nro_clavefiscal
 * @property int $fk_condicioniva_id
 * @property int $fk_tarifario1_id
 * @property int $fk_tarifario2_id
 * @property float $gastos_iva
 * @property string $fk_moneda_id
 * @property float $gastos_fijo_1
 * @property float $gastos_porcentaje_1
 * @property string $fotodoc
 * @property Carbon $ultimo_mail
 * @property string $observaciones
 * 
 * @property Cliente $cliente
 * @property Collection|Tag[] $tags
 *
 * @package App\Models
 */
class Pasajero extends Model
{
	protected $table = 'pasajero';
	protected $primaryKey = 'pasajero_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'mostrar_ficha' => 'int',
		'fk_usuario_vendedor' => 'int',
		'emisordoc' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fk_tipoclavefiscal_id' => 'int',
		'fk_condicioniva_id' => 'int',
		'fk_tarifario1_id' => 'int',
		'fk_tarifario2_id' => 'int',
		'gastos_iva' => 'float',
		'gastos_fijo_1' => 'float',
		'gastos_porcentaje_1' => 'float',
		'ultimo_mail' => 'datetime'
	];

	protected $hidden = [
		'pasajero_password'
	];

	protected $fillable = [
		'pasajero_nombre',
		'pasajero_apellido',
		'pasajero_apodo',
		'pasajero_nacionalidad',
		'pasajero_nacimiento',
		'pasajero_sexo',
		'pasajero_email',
		'pasajero_clave',
		'pasajero_password',
		'fk_cliente_id',
		'mostrar_ficha',
		'cargo',
		'cliente_asociado',
		'fk_usuario_vendedor',
		'fk_usuario_promotor1',
		'freelance',
		'habilita',
		'pasajero_foto',
		'tipodoc',
		'nrodoc',
		'emisordoc',
		'emisorfecha',
		'vencimientodoc',
		'pasajero_direccionfiscal',
		'pasajero_codigopostal',
		'fk_pais_id',
		'pasajero_ciudad',
		'fk_ciudad_id',
		'fk_tipoclavefiscal_id',
		'nro_clavefiscal',
		'fk_condicioniva_id',
		'fk_tarifario1_id',
		'fk_tarifario2_id',
		'gastos_iva',
		'fk_moneda_id',
		'gastos_fijo_1',
		'gastos_porcentaje_1',
		'fotodoc',
		'ultimo_mail',
		'observaciones'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function tags()
	{
		return $this->belongsToMany(Tag::class, 'rel_pasajerotag', 'fk_pasajero_id', 'fk_tag_id');
	}
}
