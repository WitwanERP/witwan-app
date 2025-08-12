<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelServicio
 * 
 * @property int $servicio_madre
 * @property int $servicio_hijo
 *
 * @package App\Models
 */
class RelServicio extends Model
{
	protected $table = 'rel_servicio';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'servicio_madre' => 'int',
		'servicio_hijo' => 'int'
	];
}
