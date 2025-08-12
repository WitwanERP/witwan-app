<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cadenahotelera
 * 
 * @property int $cadenahotelera_id
 * @property string $cadenahotelera_nombre
 * 
 * @property Collection|Proveedor[] $proveedors
 *
 * @package App\Models
 */
class Cadenahotelera extends Model
{
	protected $table = 'cadenahotelera';
	protected $primaryKey = 'cadenahotelera_id';
	public $timestamps = false;

	protected $fillable = [
		'cadenahotelera_nombre'
	];

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'fk_cadenahotelera_id');
	}
}
