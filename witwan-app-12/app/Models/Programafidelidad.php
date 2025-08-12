<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Programafidelidad
 * 
 * @property int $programafidelidad_id
 * @property string $programafidelidad_nombre
 * @property string $programafidelidad_tipo
 *
 * @package App\Models
 */
class Programafidelidad extends Model
{
	protected $table = 'programafidelidad';
	protected $primaryKey = 'programafidelidad_id';
	public $timestamps = false;

	protected $fillable = [
		'programafidelidad_nombre',
		'programafidelidad_tipo'
	];
}
