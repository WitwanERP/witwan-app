<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cadenacliente
 * 
 * @property int $cadenacliente_id
 * @property string $cadenacliente_nombre
 * 
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Cadenacliente extends Model
{
	protected $table = 'cadenacliente';
	protected $primaryKey = 'cadenacliente_id';
	public $timestamps = false;

	protected $fillable = [
		'cadenacliente_nombre'
	];

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'fk_cadenacliente_id');
	}
}
