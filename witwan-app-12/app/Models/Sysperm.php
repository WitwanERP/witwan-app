<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysperm
 * 
 * @property int $sysperm_id
 * @property string $sysperm_nombre
 * @property string $sysperm_action
 *
 * @package App\Models
 */
class Sysperm extends Model
{
	protected $table = 'sysperm';
	protected $primaryKey = 'sysperm_id';
	public $timestamps = false;

	protected $fillable = [
		'sysperm_nombre',
		'sysperm_action'
	];
}
