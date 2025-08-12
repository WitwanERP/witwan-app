<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysconfig
 * 
 * @property int $sysconfig_id
 * @property string $sysconfig_key
 * @property string $sysconfig_value
 *
 * @package App\Models
 */
class Sysconfig extends Model
{
	protected $table = 'sysconfig';
	protected $primaryKey = 'sysconfig_id';
	public $timestamps = false;

	protected $fillable = [
		'sysconfig_key',
		'sysconfig_value'
	];
}
