<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Filearchivo
 * 
 * @property int $filearchivo_id
 * @property int $fk_file_id
 * @property string $filearchivo_archivo
 * @property int $filearchivo_publico
 * @property int $fk_usuario_id
 * @property Carbon $regdate
 * 
 * @property Reserva $reserva
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Filearchivo extends Model
{
	protected $table = 'filearchivo';
	protected $primaryKey = 'filearchivo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_file_id' => 'int',
		'filearchivo_publico' => 'int',
		'fk_usuario_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_file_id',
		'filearchivo_archivo',
		'filearchivo_publico',
		'fk_usuario_id',
		'regdate'
	];

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
