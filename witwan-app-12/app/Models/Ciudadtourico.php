<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Ciudadtourico
 * 
 * @property int $ciudadtourico_id
 * @property string $ciudadtourico_nombre
 * @property string $ciudadtourico_codigo
 * @property string $ciudadtourico_pais
 *
 * @package App\Models
 */
class Ciudadtourico extends Model
{
	protected $table = 'ciudadtourico';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'ciudadtourico_id' => 'int'
	];

	protected $fillable = [
		'ciudadtourico_nombre',
		'ciudadtourico_codigo'
	];
}
