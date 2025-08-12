<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Precompra
 * 
 * @property int $precompra_id
 * @property string $precompra_tipo
 * @property int $fk_sistema_id
 * @property int $fk_producto_id
 * @property string $fk_moneda_id
 * @property int $fk_file_id
 * @property Carbon $precompra_inicio
 * @property int $fk_proveedor_id
 * @property float $precompra_dinero
 * @property float $precompra_utilizado
 * @property Carbon $precompra_fin
 * @property int $fk_usuario_id
 * @property Carbon $regdate
 * @property Carbon $um
 * @property string $observaciones
 * 
 * @property RelOcupacionprecompra|null $rel_ocupacionprecompra
 *
 * @package App\Models
 */
class Precompra extends Model
{
	protected $table = 'precompra';
	protected $primaryKey = 'precompra_id';
	public $timestamps = false;

	protected $casts = [
		'fk_sistema_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_file_id' => 'int',
		'precompra_inicio' => 'datetime',
		'fk_proveedor_id' => 'int',
		'precompra_dinero' => 'float',
		'precompra_utilizado' => 'float',
		'precompra_fin' => 'datetime',
		'fk_usuario_id' => 'int',
		'regdate' => 'datetime',
		'um' => 'datetime'
	];

	protected $fillable = [
		'precompra_tipo',
		'fk_sistema_id',
		'fk_producto_id',
		'fk_moneda_id',
		'fk_file_id',
		'precompra_inicio',
		'fk_proveedor_id',
		'precompra_dinero',
		'precompra_utilizado',
		'precompra_fin',
		'fk_usuario_id',
		'regdate',
		'um',
		'observaciones'
	];

	public function rel_ocupacionprecompra()
	{
		return $this->hasOne(RelOcupacionprecompra::class, 'fk_precompra_id');
	}
}
