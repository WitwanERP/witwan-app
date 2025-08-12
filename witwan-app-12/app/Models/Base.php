<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Base
 * 
 * @property string $base_id
 * @property string $base_nombre
 * @property string $base_codigo
 * @property int $base_cantidad
 * 
 * @property Collection|Tarifa[] $tarifas
 *
 * @package App\Models
 */
class Base extends Model
{
	protected $table = 'base';
	protected $primaryKey = 'base_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'base_cantidad' => 'int'
	];

	protected $fillable = [
		'base_nombre',
		'base_codigo',
		'base_cantidad'
	];

	public function tarifas()
	{
		return $this->hasMany(Tarifa::class, 'fk_base_id');
	}
}
