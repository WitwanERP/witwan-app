<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysnotification
 * 
 * @property int $sysnotification_id
 * @property int $fk_usuario_id
 * @property Carbon $sysnotification_date
 * @property Carbon $sysnotification_due
 * @property string $sysnotification_nombre
 * @property string $sysnotification_url
 * @property string $sysnotification_type
 * @property string $sysnotification_icon
 *
 * @package App\Models
 */
class Sysnotification extends Model
{
	protected $table = 'sysnotification';
	protected $primaryKey = 'sysnotification_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'sysnotification_date' => 'datetime',
		'sysnotification_due' => 'datetime'
	];

	protected $fillable = [
		'fk_usuario_id',
		'sysnotification_date',
		'sysnotification_due',
		'sysnotification_nombre',
		'sysnotification_url',
		'sysnotification_type',
		'sysnotification_icon'
	];
}
