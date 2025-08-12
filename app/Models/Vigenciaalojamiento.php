<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Vigenciaalojamiento
 * 
 * @property int $vigenciaalojamiento_id
 * @property int $fk_vigencia_id
 * @property int $fk_producto_id
 * @property int $fk_tarifacategoria_id
 * @property int $fk_regimen_id
 * @property int $noches
 * @property string $ncategoria
 *
 * @package App\Models
 */
class Vigenciaalojamiento extends Model
{
	protected $table = 'vigenciaalojamiento';
	protected $primaryKey = 'vigenciaalojamiento_id';
	public $timestamps = false;

	protected $casts = [
		'fk_vigencia_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'fk_regimen_id' => 'int',
		'noches' => 'int'
	];

	protected $fillable = [
		'fk_vigencia_id',
		'fk_producto_id',
		'fk_tarifacategoria_id',
		'fk_regimen_id',
		'noches',
		'ncategoria'
	];
}
