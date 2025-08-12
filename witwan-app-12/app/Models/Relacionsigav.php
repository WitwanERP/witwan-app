<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Relacionsigav
 * 
 * @property int|null $sigav
 * @property string|null $area
 * @property string|null $prefijo
 * @property int|null $witwan
 * @property string|null $promotor
 *
 * @package App\Models
 */
class Relacionsigav extends Model
{
	protected $table = 'relacionsigav';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'sigav' => 'int',
		'witwan' => 'int'
	];

	protected $fillable = [
		'sigav',
		'area',
		'prefijo',
		'witwan',
		'promotor'
	];
}
