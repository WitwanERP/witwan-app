<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Soldout
 * 
 * @property int $soldout_id
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $fk_producto_id
 * @property string|null $base
 * @property int $fk_tarifacategoria_id
 * @property int $fk_subcategoria_id
 * @property int $fk_usuario_id
 * @property Carbon $fecha_alta
 * 
 * @property Producto $producto
 * @property Tarifacategorium $tarifacategorium
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Soldout extends Model
{
	protected $table = 'soldout';
	protected $primaryKey = 'soldout_id';
	public $timestamps = false;

	protected $casts = [
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'fk_subcategoria_id' => 'int',
		'fk_usuario_id' => 'int',
		'fecha_alta' => 'datetime'
	];

	protected $fillable = [
		'vigencia_ini',
		'vigencia_fin',
		'fk_producto_id',
		'base',
		'fk_tarifacategoria_id',
		'fk_subcategoria_id',
		'fk_usuario_id',
		'fecha_alta'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function tarifacategorium()
	{
		return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
