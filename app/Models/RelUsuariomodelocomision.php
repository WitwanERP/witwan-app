<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelUsuariomodelocomision
 * 
 * @property int $rel_usuariomodelocomision_id
 * @property int $fk_usuario_id
 * @property int $fk_modelocomision_id
 * 
 * @property Modelocomision $modelocomision
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class RelUsuariomodelocomision extends Model
{
	protected $table = 'rel_usuariomodelocomision';
	protected $primaryKey = 'rel_usuariomodelocomision_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_modelocomision_id' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_modelocomision_id'
	];

	public function modelocomision()
	{
		return $this->belongsTo(Modelocomision::class, 'fk_modelocomision_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
