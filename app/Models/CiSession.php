<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CiSession
 * 
 * @property string $session_id
 * @property string $ip_address
 * @property string $user_agent
 * @property int $last_activity
 * @property string $user_data
 *
 * @package App\Models
 */
class CiSession extends Model
{
	protected $table = 'ci_sessions';
	protected $primaryKey = 'session_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'last_activity' => 'int'
	];

	protected $fillable = [
		'ip_address',
		'user_agent',
		'last_activity',
		'user_data'
	];
}
