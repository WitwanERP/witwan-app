<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Hotelcategorium
 * 
 * @property int $hotelcategoria_id
 * @property string $hotelcategoria_nombre
 * @property string $hotelcategoria_label
 * @property float $hotelcategoria_stars
 * 
 * @property Alojamiento|null $alojamiento
 *
 * @package App\Models
 */
class Hotelcategorium extends Model
{
	protected $table = 'hotelcategoria';
	protected $primaryKey = 'hotelcategoria_id';
	public $timestamps = false;

	protected $casts = [
		'hotelcategoria_stars' => 'float'
	];

	protected $fillable = [
		'hotelcategoria_nombre',
		'hotelcategoria_label',
		'hotelcategoria_stars'
	];

	public function alojamiento()
	{
		return $this->hasOne(Alojamiento::class, 'fk_hotelcategoria_id');
	}
}
