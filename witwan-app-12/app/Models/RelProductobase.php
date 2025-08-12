<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelProductobase
 * 
 * @property int $fk_producto_id
 * @property int $fk_base_id
 *
 * @package App\Models
 */
class RelProductobase extends Model
{
	protected $table = 'rel_productobase';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_base_id' => 'int'
	];
}
