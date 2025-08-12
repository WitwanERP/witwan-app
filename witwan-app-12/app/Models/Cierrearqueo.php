<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cierrearqueo
 * 
 * @property int $cierrearqueo_id
 * @property Carbon $cierrearqueo_fecha
 * @property int $fk_usuario_id
 * @property Carbon $regdate
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Cierrearqueo extends Model
{
	protected $table = 'cierrearqueo';
	protected $primaryKey = 'cierrearqueo_id';
	public $timestamps = false;

	protected $casts = [
		'cierrearqueo_fecha' => 'datetime',
		'fk_usuario_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'cierrearqueo_fecha',
		'fk_usuario_id',
		'regdate'
	];

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
