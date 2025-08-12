<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Ivatipo
 * 
 * @property int $ivatipo_id
 * @property string $ivatipo_nombre
 *
 * @package App\Models
 */
class Ivatipo extends Model
{
	protected $table = 'ivatipo';
	protected $primaryKey = 'ivatipo_id';
	public $timestamps = false;

	protected $fillable = [
		'ivatipo_nombre'
	];
}
