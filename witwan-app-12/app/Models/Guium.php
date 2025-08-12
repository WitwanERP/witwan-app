<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Guium
 * 
 * @property int $guia_id
 * @property string $guia_apellido
 * @property string $guia_nombre
 * @property int $fk_ciudad_id
 * 
 * @property Ciudad $ciudad
 *
 * @package App\Models
 */
class Guium extends Model
{
	protected $table = 'guia';
	protected $primaryKey = 'guia_id';
	public $timestamps = false;

	protected $casts = [
		'fk_ciudad_id' => 'int'
	];

	protected $fillable = [
		'guia_apellido',
		'guia_nombre',
		'fk_ciudad_id'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}
}
