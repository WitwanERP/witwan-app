<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelGrupopaispai
 * 
 * @property int $fk_grupopais_id
 * @property int $fk_pais_id
 *
 * @package App\Models
 */
class RelGrupopaispai extends Model
{
	protected $table = 'rel_grupopaispais';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_grupopais_id' => 'int',
		'fk_pais_id' => 'int'
	];
}
