<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysmenu
 * 
 * @property int $sysmenu_id
 * @property int $fk_syscategory_id
 * @property string $sysmenu_name
 * @property string $sysmenu_controller
 * @property string $sysmenu_icon
 * @property int $sysmenu_order
 *
 * @package App\Models
 */
class Sysmenu extends Model
{
	protected $table = 'sysmenu';
	protected $primaryKey = 'sysmenu_id';
	public $timestamps = false;

	protected $casts = [
		'fk_syscategory_id' => 'int',
		'sysmenu_order' => 'int'
	];

	protected $fillable = [
		'fk_syscategory_id',
		'sysmenu_name',
		'sysmenu_controller',
		'sysmenu_icon',
		'sysmenu_order'
	];
}
