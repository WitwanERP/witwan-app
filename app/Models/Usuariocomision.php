<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Usuariocomision
 * 
 * @property int $usuariocomision_id
 * @property int $fk_usuario_id
 * @property int $fk_file_id
 * @property int $fk_ocupacion_id
 * @property string $fk_statuscomision_id
 * @property Carbon $usuariocomision_fecha
 * @property Carbon $fecha_cobro
 * @property string $fk_moneda_id
 * @property float $monto
 * @property string $descripcion
 * @property int $fk_plancuenta_id
 * @property int $fk_factura_id
 * 
 * @property Moneda $moneda
 * @property Plancuentum $plancuentum
 * @property Reserva $reserva
 * @property Servicio $servicio
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Usuariocomision extends Model
{
	protected $table = 'usuariocomision';
	protected $primaryKey = 'usuariocomision_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_file_id' => 'int',
		'fk_ocupacion_id' => 'int',
		'usuariocomision_fecha' => 'datetime',
		'fecha_cobro' => 'datetime',
		'monto' => 'float',
		'fk_plancuenta_id' => 'int',
		'fk_factura_id' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_file_id',
		'fk_ocupacion_id',
		'fk_statuscomision_id',
		'usuariocomision_fecha',
		'fecha_cobro',
		'fk_moneda_id',
		'monto',
		'descripcion',
		'fk_plancuenta_id',
		'fk_factura_id'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function plancuentum()
	{
		return $this->belongsTo(Plancuentum::class, 'fk_plancuenta_id');
	}

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
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
