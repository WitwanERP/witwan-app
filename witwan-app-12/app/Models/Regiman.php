<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Regiman
 * 
 * @property int $regimen_id
 * @property string $regimen_nombre
 * @property string $regimen_nombre_en
 * @property string $regimen_nombre_pg
 * 
 * @property Collection|Servicio[] $servicios
 * @property Collection|Vigencium[] $vigencia
 *
 * @package App\Models
 */
class Regiman extends Model
{
	protected $table = 'regimen';
	protected $primaryKey = 'regimen_id';
	public $timestamps = false;

	protected $fillable = [
		'regimen_nombre',
		'regimen_nombre_en',
		'regimen_nombre_pg'
	];

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_regimen_id');
	}

	public function vigencia()
	{
		return $this->hasMany(Vigencium::class, 'fk_regimen_id');
	}
}
