<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Tipoclavefiscal
 * 
 * @property int $tipoclavefiscal_id
 * @property string $tipoclavefiscal_nombre
 *
 * @package App\Models
 */
class Tipoclavefiscal extends Model
{
	protected $table = 'tipoclavefiscal';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'tipoclavefiscal_id' => 'int'
	];

	protected $fillable = [
		'tipoclavefiscal_id',
		'tipoclavefiscal_nombre'
	];
}
