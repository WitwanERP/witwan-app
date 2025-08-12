<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cierrecaja
 * 
 * @property int $cierrecaja_id
 * @property Carbon $cierrecaja_fecha
 * @property int $fk_usuario_id
 * @property Carbon $regdate
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Cierrecaja extends Model
{
	protected $table = 'cierrecaja';
	protected $primaryKey = 'cierrecaja_id';
	public $timestamps = false;

	protected $casts = [
		'cierrecaja_fecha' => 'datetime',
		'fk_usuario_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'cierrecaja_fecha',
		'fk_usuario_id',
		'regdate'
	];

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
