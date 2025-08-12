<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelGuiaidioma
 * 
 * @property int $fk_guia_id
 * @property int $fk_idioma_id
 *
 * @package App\Models
 */
class RelGuiaidioma extends Model
{
	protected $table = 'rel_guiaidioma';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_guia_id' => 'int',
		'fk_idioma_id' => 'int'
	];
}
