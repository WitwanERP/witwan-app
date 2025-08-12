<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Syslogin
 * 
 * @property int $syslogin_id
 * @property Carbon $syslogin_created
 * @property string $syslogin_ip
 * @property int $fk_usuario_id
 *
 * @package App\Models
 */
class Syslogin extends Model
{
	protected $table = 'syslogin';
	protected $primaryKey = 'syslogin_id';
	public $timestamps = false;

	protected $casts = [
		'syslogin_created' => 'datetime',
		'fk_usuario_id' => 'int'
	];

	protected $fillable = [
		'syslogin_created',
		'syslogin_ip',
		'fk_usuario_id'
	];
}
