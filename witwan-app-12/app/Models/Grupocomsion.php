<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Grupocomsion
 * 
 * @property int $grupocomsion_id
 * @property string $grupocomsion_nombre
 *
 * @package App\Models
 */
class Grupocomsion extends Model
{
	protected $table = 'grupocomsion';
	protected $primaryKey = 'grupocomsion_id';
	public $timestamps = false;

	protected $fillable = [
		'grupocomsion_nombre'
	];
}
