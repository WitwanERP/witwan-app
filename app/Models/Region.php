<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Region
 * 
 * @property int $region_id
 * @property string $region_nombre
 * @property string $nombre_en
 * @property string $nombre_pg
 * 
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Pai[] $pais
 *
 * @package App\Models
 */
class Region extends Model
{
	protected $table = 'region';
	protected $primaryKey = 'region_id';
	public $timestamps = false;

	protected $fillable = [
		'region_nombre',
		'nombre_en',
		'nombre_pg'
	];

	public function modelofees()
	{
		return $this->hasMany(Modelofee::class, 'fk_region_id');
	}

	public function pais()
	{
		return $this->hasMany(Pai::class, 'fk_region_id');
	}
}
