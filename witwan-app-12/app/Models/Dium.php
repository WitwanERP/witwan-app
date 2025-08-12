<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Dium
 * 
 * @property int $dia_id
 * @property string $dia_nombre
 *
 * @package App\Models
 */
class Dium extends Model
{
	protected $table = 'dia';
	protected $primaryKey = 'dia_id';
	public $timestamps = false;

	protected $fillable = [
		'dia_nombre'
	];
}
