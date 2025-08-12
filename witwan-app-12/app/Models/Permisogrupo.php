<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Permisogrupo
 * 
 * @property int $permisogrupo_id
 * @property string $fk_tipousuario_id
 * @property int $fk_seccion_id
 * @property string $permisogrupo_nombre
 * @property int $permisogrupo_valor
 * 
 * @property Tipousuario $tipousuario
 *
 * @package App\Models
 */
class Permisogrupo extends Model
{
	protected $table = 'permisogrupo';
	protected $primaryKey = 'permisogrupo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_seccion_id' => 'int',
		'permisogrupo_valor' => 'int'
	];

	protected $fillable = [
		'fk_tipousuario_id',
		'fk_seccion_id',
		'permisogrupo_nombre',
		'permisogrupo_valor'
	];

	public function tipousuario()
	{
		return $this->belongsTo(Tipousuario::class, 'fk_tipousuario_id');
	}
}
