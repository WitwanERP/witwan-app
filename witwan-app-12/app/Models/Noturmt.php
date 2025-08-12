<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Noturmt
 * 
 * @property int $CodPro
 *
 * @package App\Models
 */
class Noturmt extends Model
{
	protected $table = 'noturmt';
	protected $primaryKey = 'CodPro';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'CodPro' => 'int'
	];
}
