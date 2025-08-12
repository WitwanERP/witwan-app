<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelProveedorsistema
 * 
 * @property int $fk_proveedor_id
 * @property int $fk_sistema_id
 *
 * @package App\Models
 */
class RelProveedorsistema extends Model
{
	protected $table = 'rel_proveedorsistema';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'fk_sistema_id' => 'int'
	];

	protected $fillable = [
		'fk_proveedor_id',
		'fk_sistema_id'
	];
}
