<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Centrocosto
 * 
 * @property int $centrocosto_id
 * @property string $centrocosto_nombre
 * @property string $centrocosto_codigo
 * @property int $centrocosto_activo
 *
 * @package App\Models
 */
class Centrocosto extends Model
{
	protected $table = 'centrocosto';
	protected $primaryKey = 'centrocosto_id';
	public $timestamps = false;

	protected $casts = [
		'centrocosto_activo' => 'int'
	];

	protected $fillable = [
		'centrocosto_nombre',
		'centrocosto_codigo',
		'centrocosto_activo'
	];
}
