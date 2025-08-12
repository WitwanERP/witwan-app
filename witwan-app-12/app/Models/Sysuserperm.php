<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysuserperm
 * 
 * @property int $fk_sysuser_id
 * @property int $fk_sysperm_id
 *
 * @package App\Models
 */
class Sysuserperm extends Model
{
	protected $table = 'sysuserperm';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_sysuser_id' => 'int',
		'fk_sysperm_id' => 'int'
	];

	protected $fillable = [
		'fk_sysuser_id',
		'fk_sysperm_id'
	];
}
