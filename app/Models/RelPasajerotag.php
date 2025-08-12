<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelPasajerotag
 * 
 * @property int $fk_pasajero_id
 * @property int $fk_tag_id
 * 
 * @property Pasajero $pasajero
 * @property Tag $tag
 *
 * @package App\Models
 */
class RelPasajerotag extends Model
{
	protected $table = 'rel_pasajerotag';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_pasajero_id' => 'int',
		'fk_tag_id' => 'int'
	];

	public function pasajero()
	{
		return $this->belongsTo(Pasajero::class, 'fk_pasajero_id');
	}

	public function tag()
	{
		return $this->belongsTo(Tag::class, 'fk_tag_id');
	}
}
