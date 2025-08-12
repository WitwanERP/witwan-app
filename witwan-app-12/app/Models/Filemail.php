<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Filemail
 * 
 * @property int $fk_file_id
 * @property string $mail_to
 * @property string $mail_to_cc
 * @property string $status
 * @property string $descripcion
 * @property Carbon $fecha
 * @property string $motivo
 * @property string $tipo
 * @property string $mensaje_error
 * @property int $cancelar
 * 
 * @property Reserva $reserva
 *
 * @package App\Models
 */
class Filemail extends Model
{
	protected $table = 'filemail';
	protected $primaryKey = 'fk_file_id';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime',
		'cancelar' => 'int'
	];

	protected $fillable = [
		'mail_to',
		'mail_to_cc',
		'status',
		'descripcion',
		'fecha',
		'motivo',
		'tipo',
		'mensaje_error',
		'cancelar'
	];

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}
}
