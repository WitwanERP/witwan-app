<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Banner
 * 
 * @property int $banner_id
 * @property string $banner_nombre
 * @property string $banner_archivo
 *
 * @package App\Models
 */
class Banner extends Model
{
	protected $table = 'banner';
	protected $primaryKey = 'banner_id';
	public $timestamps = false;

	protected $fillable = [
		'banner_nombre',
		'banner_archivo'
	];
}
