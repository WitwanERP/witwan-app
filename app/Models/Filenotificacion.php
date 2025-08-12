<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Filenotificacion
 * 
 * @property int $filenotificacion_id
 * @property int $fk_file_id
 * @property int $version
 * @property string $destinatario
 * @property int $fk_mails_id
 * 
 * @property Reserva $reserva
 *
 * @package App\Models
 */
class Filenotificacion extends Model
{
	protected $table = 'filenotificacion';
	protected $primaryKey = 'filenotificacion_id';
	public $timestamps = false;

	protected $casts = [
		'fk_file_id' => 'int',
		'version' => 'int',
		'fk_mails_id' => 'int'
	];

	protected $fillable = [
		'fk_file_id',
		'version',
		'destinatario',
		'fk_mails_id'
	];

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}
}
