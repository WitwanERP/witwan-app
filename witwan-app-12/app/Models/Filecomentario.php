<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Filecomentario
 * 
 * @property int $filecomentario_id
 * @property int $fk_file_id
 * @property int $fk_usuario_id
 * @property Carbon $fecha
 * @property string $comentario
 * 
 * @property Reserva $reserva
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Filecomentario extends Model
{
	protected $table = 'filecomentario';
	protected $primaryKey = 'filecomentario_id';
	public $timestamps = false;

	protected $casts = [
		'fk_file_id' => 'int',
		'fk_usuario_id' => 'int',
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'fk_file_id',
		'fk_usuario_id',
		'fecha',
		'comentario'
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
