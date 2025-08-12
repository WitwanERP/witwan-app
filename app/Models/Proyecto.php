<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Proyecto
 * 
 * @property int $proyecto_id
 * @property string $proyecto_nombre
 * 
 * @property Collection|Facturaproveedor[] $facturaproveedors
 * @property Collection|Ordenadmin[] $ordenadmins
 *
 * @package App\Models
 */
class Proyecto extends Model
{
	protected $table = 'proyecto';
	protected $primaryKey = 'proyecto_id';
	public $timestamps = false;

	protected $fillable = [
		'proyecto_nombre'
	];

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_proyecto_id');
	}

	public function ordenadmins()
	{
		return $this->hasMany(Ordenadmin::class, 'fk_proyecto_id');
	}
}
