<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Ciudadauxiliar
 * 
 * @property int $ciudadauxiliar_id
 * @property int $fk_ciudad_id
 * @property string $ciudad_externa
 * @property string $tipo
 * @property string $nombre
 * 
 * @property Ciudad $ciudad
 *
 * @package App\Models
 */
class Ciudadauxiliar extends Model
{
	protected $table = 'ciudadauxiliar';
	protected $primaryKey = 'ciudadauxiliar_id';
	public $timestamps = false;

	protected $casts = [
		'fk_ciudad_id' => 'int'
	];

	protected $fillable = [
		'fk_ciudad_id',
		'ciudad_externa',
		'tipo',
		'nombre'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}
}
