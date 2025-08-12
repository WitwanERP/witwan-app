<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Interfase
 * 
 * @property int $interfases_id
 * @property string $interfases_nombre
 * @property string $interfases_codigo
 * @property string $interfases_libreria
 * @property int $fk_proveedor_id
 * @property float $interfases_mup
 * @property int $interfases_activo
 * @property int $interfases_prioridad
 * @property int $interfases_receptivo
 * @property int $interfases_mayorista
 * @property int $interfases_release
 * @property int $interfases_penalidad
 * @property string $interfases_data
 * 
 * @property Proveedor $proveedor
 * @property Collection|Interfasedatum[] $interfasedata
 *
 * @package App\Models
 */
class Interfase extends Model
{
	protected $table = 'interfases';
	protected $primaryKey = 'interfases_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'interfases_mup' => 'float',
		'interfases_activo' => 'int',
		'interfases_prioridad' => 'int',
		'interfases_receptivo' => 'int',
		'interfases_mayorista' => 'int',
		'interfases_release' => 'int',
		'interfases_penalidad' => 'int'
	];

	protected $fillable = [
		'interfases_nombre',
		'interfases_codigo',
		'interfases_libreria',
		'fk_proveedor_id',
		'interfases_mup',
		'interfases_activo',
		'interfases_prioridad',
		'interfases_receptivo',
		'interfases_mayorista',
		'interfases_release',
		'interfases_penalidad',
		'interfases_data'
	];

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function interfasedata()
	{
		return $this->hasMany(Interfasedatum::class, 'fk_interfases_id');
	}
}
