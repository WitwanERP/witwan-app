<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Historialfile
 * 
 * @property int $historial_id
 * @property Carbon $historial_date
 * @property string $historial_campo
 * @property string $historial_valor
 * @property string $historial_actual
 * @property int $fk_reserva_id
 * @property int $fk_servicio_id
 * @property int|null $fk_usuario_id
 * @property string|null $historial_ip
 * 
 * @property Reserva $reserva
 * @property Servicio $servicio
 * @property Usuario|null $usuario
 *
 * @package App\Models
 */
class Historialfile extends Model
{
	protected $table = 'historialfile';
	protected $primaryKey = 'historial_id';
	public $timestamps = false;

	protected $casts = [
		'historial_date' => 'datetime',
		'fk_reserva_id' => 'int',
		'fk_servicio_id' => 'int',
		'fk_usuario_id' => 'int'
	];

	protected $fillable = [
		'historial_date',
		'historial_campo',
		'historial_valor',
		'historial_actual',
		'fk_reserva_id',
		'fk_servicio_id',
		'fk_usuario_id',
		'historial_ip'
	];

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_reserva_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
