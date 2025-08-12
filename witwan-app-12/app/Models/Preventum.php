<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Preventum
 * 
 * @property int $preventa_id
 * @property int $fk_cliente_id
 * @property int $fk_producto_id
 * @property int $fk_tarifacategoria_id
 * @property int $subcategoria
 * @property string|null $fk_base_id
 * @property int $cantidad
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 *
 * @package App\Models
 */
class Preventum extends Model
{
	protected $table = 'preventa';
	protected $primaryKey = 'preventa_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'subcategoria' => 'int',
		'cantidad' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime'
	];

	protected $fillable = [
		'fk_cliente_id',
		'fk_producto_id',
		'fk_tarifacategoria_id',
		'subcategoria',
		'fk_base_id',
		'cantidad',
		'vigencia_ini',
		'vigencia_fin'
	];
}
