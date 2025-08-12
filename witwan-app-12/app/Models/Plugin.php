<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Plugin
 * 
 * @property int $plugin_id
 * @property string $plugin_system_name
 * @property string $plugin_name
 * @property string|null $plugin_uri
 * @property string $plugin_version
 * @property string|null $plugin_description
 * @property string|null $plugin_author
 * @property string|null $plugin_author_uri
 * @property string|null $plugin_data
 *
 * @package App\Models
 */
class Plugin extends Model
{
	protected $table = 'plugins';
	protected $primaryKey = 'plugin_id';
	public $timestamps = false;

	protected $fillable = [
		'plugin_system_name',
		'plugin_name',
		'plugin_uri',
		'plugin_version',
		'plugin_description',
		'plugin_author',
		'plugin_author_uri',
		'plugin_data'
	];
}
