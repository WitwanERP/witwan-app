<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Crmbanco
 * 
 * @property int $crmbanco_id
 * @property string $crmbanco_nombre
 *
 * @package App\Models
 */
class Crmbanco extends Model
{
	protected $table = 'crmbanco';
	protected $primaryKey = 'crmbanco_id';
	public $timestamps = false;

	protected $fillable = [
		'crmbanco_nombre'
	];
}
