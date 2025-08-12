<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Alojamientotipo
 * 
 * @property int $alojamientotipo_id
 * @property string $alojamientotipo_nombre
 * 
 * @property Alojamiento|null $alojamiento
 *
 * @package App\Models
 */
class Alojamientotipo extends Model
{
	protected $table = 'alojamientotipo';
	protected $primaryKey = 'alojamientotipo_id';
	public $timestamps = false;

	protected $fillable = [
		'alojamientotipo_nombre'
	];

	public function alojamiento()
	{
		return $this->hasOne(Alojamiento::class, 'fk_alojamientotipo_id');
	}
}
