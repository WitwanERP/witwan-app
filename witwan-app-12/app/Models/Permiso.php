<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Permiso
 * 
 * @property int $permiso_id
 * @property int $fk_usuario_id
 * @property int $fk_seccion_id
 * @property string $permiso_nombre
 * @property int $permiso_valor
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Permiso extends Model
{
	protected $table = 'permiso';
	protected $primaryKey = 'permiso_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_seccion_id' => 'int',
		'permiso_valor' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_seccion_id',
		'permiso_nombre',
		'permiso_valor'
	];

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
