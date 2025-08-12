<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Negocio
 * 
 * @property int $negocio_id
 * @property string $negocio_nombre
 * @property Carbon $negocio_vencimiento
 * @property int $fk_ciudad_id
 * @property string $negocio_descripcion
 * @property int $fk_sistema_id
 * 
 * @property Ciudad $ciudad
 * @property Sistema $sistema
 * @property Collection|Reserva[] $reservas
 *
 * @package App\Models
 */
class Negocio extends Model
{
	protected $table = 'negocio';
	protected $primaryKey = 'negocio_id';
	public $timestamps = false;

	protected $casts = [
		'negocio_vencimiento' => 'datetime',
		'fk_ciudad_id' => 'int',
		'fk_sistema_id' => 'int'
	];

	protected $fillable = [
		'negocio_nombre',
		'negocio_vencimiento',
		'fk_ciudad_id',
		'negocio_descripcion',
		'fk_sistema_id'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'fk_negocio_id');
	}
}
