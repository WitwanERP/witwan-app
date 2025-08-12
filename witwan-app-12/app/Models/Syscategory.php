<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Syscategory
 * 
 * @property int $syscategory_id
 * @property string $syscategory_name
 * @property string $syscategory_icon
 *
 * @package App\Models
 */
class Syscategory extends Model
{
	protected $table = 'syscategory';
	protected $primaryKey = 'syscategory_id';
	public $timestamps = false;

	protected $fillable = [
		'syscategory_name',
		'syscategory_icon'
	];
}
