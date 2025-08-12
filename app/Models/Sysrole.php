<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysrole
 * 
 * @property int $sysrole_id
 * @property string $sysrole_nombre
 * @property bool $sysrole_admin
 *
 * @package App\Models
 */
class Sysrole extends Model
{
	protected $table = 'sysrole';
	protected $primaryKey = 'sysrole_id';
	public $timestamps = false;

	protected $casts = [
		'sysrole_admin' => 'bool'
	];

	protected $fillable = [
		'sysrole_nombre',
		'sysrole_admin'
	];
}
