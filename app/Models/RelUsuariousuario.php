<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelUsuariousuario
 * 
 * @property int $usuariousuario_id
 * @property int $fk_usuario_id
 * @property int $fk_secundario_id
 * @property int $tiporelacion
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class RelUsuariousuario extends Model
{
	protected $table = 'rel_usuariousuario';
	protected $primaryKey = 'usuariousuario_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_secundario_id' => 'int',
		'tiporelacion' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_secundario_id',
		'tiporelacion'
	];

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_secundario_id');
	}
}
