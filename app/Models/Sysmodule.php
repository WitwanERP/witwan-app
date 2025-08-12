<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysmodule
 * 
 * @property int $sysmodule_id
 * @property bool $sysmodule_active
 * @property string $sysmodule_name
 * @property string $sysmodule_version
 * @property string $sysmodule_description
 * @property string $sysmodule_file
 * @property int $sysmodule_order
 *
 * @package App\Models
 */
class Sysmodule extends Model
{
	protected $table = 'sysmodule';
	protected $primaryKey = 'sysmodule_id';
	public $timestamps = false;

	protected $casts = [
		'sysmodule_active' => 'bool',
		'sysmodule_order' => 'int'
	];

	protected $fillable = [
		'sysmodule_active',
		'sysmodule_name',
		'sysmodule_version',
		'sysmodule_description',
		'sysmodule_file',
		'sysmodule_order'
	];
}
