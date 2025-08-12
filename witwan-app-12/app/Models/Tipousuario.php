<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tipousuario
 * 
 * @property string $tipousuario_id
 * @property string $tipousuario_nombre
 * @property string $inicio
 * 
 * @property Collection|Permisogrupo[] $permisogrupos
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Tipousuario extends Model
{
	protected $table = 'tipousuario';
	protected $primaryKey = 'tipousuario_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'tipousuario_nombre',
		'inicio'
	];

	public function permisogrupos()
	{
		return $this->hasMany(Permisogrupo::class, 'fk_tipousuario_id');
	}

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'fk_tipousuario_id');
	}
}
