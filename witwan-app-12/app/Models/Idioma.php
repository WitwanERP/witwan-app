<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Idioma
 * 
 * @property string $idioma_id
 * @property string $idioma_nombre
 * @property int $orden
 * 
 * @property Collection|Cliente[] $clientes
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Idioma extends Model
{
	protected $table = 'idioma';
	protected $primaryKey = 'idioma_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'orden' => 'int'
	];

	protected $fillable = [
		'idioma_nombre',
		'orden'
	];

	public function clientes()
	{
		return $this->hasMany(Cliente::class, 'fk_idioma_id');
	}

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'fk_idioma_id');
	}
}
