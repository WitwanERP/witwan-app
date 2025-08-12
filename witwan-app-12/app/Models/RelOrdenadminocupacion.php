<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RelOrdenadminocupacion
 * 
 * @property int $fk_ordenadmin_id
 * @property int $fk_ocupacion_id
 * @property Carbon $fecha
 * @property string $fk_moneda_id
 * @property float $monto
 * @property int $fk_usuario_id
 * @property string $status
 * 
 * @property Moneda $moneda
 * @property Ordenadmin $ordenadmin
 * @property Servicio $servicio
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class RelOrdenadminocupacion extends Model
{
	protected $table = 'rel_ordenadminocupacion';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_ordenadmin_id' => 'int',
		'fk_ocupacion_id' => 'int',
		'fecha' => 'datetime',
		'monto' => 'float',
		'fk_usuario_id' => 'int'
	];

	protected $fillable = [
		'fk_ordenadmin_id',
		'fk_ocupacion_id',
		'fecha',
		'fk_moneda_id',
		'monto',
		'fk_usuario_id',
		'status'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function ordenadmin()
	{
		return $this->belongsTo(Ordenadmin::class, 'fk_ordenadmin_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
