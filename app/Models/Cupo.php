<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cupo
 * 
 * @property int $cupo_id
 * @property int $release
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $cantidad
 * @property int $fk_producto_id
 * @property int $fk_tarifacategoria_id
 * @property int $subcategoria
 * @property int $freesale
 * @property int $fk_usuario_id
 * @property Carbon $fecha_alta
 * @property int $fk_cliente_id
 * @property string $bases
 * @property int $fk_tarifario_id
 * @property string $base
 * 
 * @property Cliente $cliente
 * @property Producto $producto
 * @property Tarifacategorium $tarifacategorium
 * @property Tarifario $tarifario
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Cupo extends Model
{
	protected $table = 'cupo';
	protected $primaryKey = 'cupo_id';
	public $timestamps = false;

	protected $casts = [
		'release' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'cantidad' => 'int',
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'subcategoria' => 'int',
		'freesale' => 'int',
		'fk_usuario_id' => 'int',
		'fecha_alta' => 'datetime',
		'fk_cliente_id' => 'int',
		'fk_tarifario_id' => 'int'
	];

	protected $fillable = [
		'release',
		'vigencia_ini',
		'vigencia_fin',
		'cantidad',
		'fk_producto_id',
		'fk_tarifacategoria_id',
		'subcategoria',
		'freesale',
		'fk_usuario_id',
		'fecha_alta',
		'fk_cliente_id',
		'bases',
		'fk_tarifario_id',
		'base'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function tarifacategorium()
	{
		return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
