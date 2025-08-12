<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Tipoboleto
 * 
 * @property string $tipoboleto_id
 * @property string $tipoboleto_nombre
 *
 * @package App\Models
 */
class Tipoboleto extends Model
{
	protected $table = 'tipoboleto';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'tipoboleto_id',
		'tipoboleto_nombre'
	];
}
