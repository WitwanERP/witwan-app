<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Identidadfiscal
 * 
 * @property int $identidadfiscal_id
 * @property string $identidadfiscal_razonsocial
 * @property string $identidadfiscal_cuit
 * @property string $identidadfiscal_direccion
 * @property string $identidadfiscal_fantasia
 * @property string $identidadfiscal_telefono
 * @property string $identidadfiscal_fax
 * @property string $identidadfiscal_logo
 * @property int $fk_condicioniva_id
 * @property int $fk_sistema_id
 * 
 * @property Condicioniva $condicioniva
 * @property Sistema $sistema
 * @property Collection|Reserva[] $reservas
 *
 * @package App\Models
 */
class Identidadfiscal extends Model
{
	protected $table = 'identidadfiscal';
	protected $primaryKey = 'identidadfiscal_id';
	public $timestamps = false;

	protected $casts = [
		'fk_condicioniva_id' => 'int',
		'fk_sistema_id' => 'int'
	];

	protected $fillable = [
		'identidadfiscal_razonsocial',
		'identidadfiscal_cuit',
		'identidadfiscal_direccion',
		'identidadfiscal_fantasia',
		'identidadfiscal_telefono',
		'identidadfiscal_fax',
		'identidadfiscal_logo',
		'fk_condicioniva_id',
		'fk_sistema_id'
	];

	public function condicioniva()
	{
		return $this->belongsTo(Condicioniva::class, 'fk_condicioniva_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'fk_identidadfiscal_id');
	}
}
