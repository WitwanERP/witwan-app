<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Pkdproducto
 * 
 * @property int $pkdproducto_id
 * @property int $fk_pkditem_id
 * @property int $fk_producto_id
 * @property int $fk_tarifacategoria_id
 * @property float $markup
 * @property string $pkdproducto_data
 * 
 * @property Pkditem $pkditem
 * @property Producto $producto
 * @property Tarifacategorium $tarifacategorium
 *
 * @package App\Models
 */
class Pkdproducto extends Model
{
	protected $table = 'pkdproducto';
	protected $primaryKey = 'pkdproducto_id';
	public $timestamps = false;

	protected $casts = [
		'fk_pkditem_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'markup' => 'float'
	];

	protected $fillable = [
		'fk_pkditem_id',
		'fk_producto_id',
		'fk_tarifacategoria_id',
		'markup',
		'pkdproducto_data'
	];

	public function pkditem()
	{
		return $this->belongsTo(Pkditem::class, 'fk_pkditem_id');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function tarifacategorium()
	{
		return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
	}
}
