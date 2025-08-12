<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Ciudadxml
 * 
 * @property int $ciudadxml_id
 * @property int $fk_ciudad_id
 * @property string $interfase_codigo
 * @property string $codigo_ciudad
 * 
 * @property Ciudad $ciudad
 *
 * @package App\Models
 */
class Ciudadxml extends Model
{
	protected $table = 'ciudadxml';
	protected $primaryKey = 'ciudadxml_id';
	public $timestamps = false;

	protected $casts = [
		'fk_ciudad_id' => 'int'
	];

	protected $fillable = [
		'fk_ciudad_id',
		'interfase_codigo',
		'codigo_ciudad'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}
}
