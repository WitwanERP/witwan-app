<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelUsuariotipousuario
 * 
 * @property int $fk_usuario_id
 * @property string $fk_tipousuario_id
 *
 * @package App\Models
 */
class RelUsuariotipousuario extends Model
{
	protected $table = 'rel_usuariotipousuario';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_tipousuario_id'
	];
}
