<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifarioarchivo
 * 
 * @property int $tarifarioarchivo_id
 * @property int $fk_tarifario_id
 * @property string $tarifarioarchivo_archivo
 * @property string $tarifarioarchivo_descripcion
 * 
 * @property Tarifario $tarifario
 *
 * @package App\Models
 */
class Tarifarioarchivo extends Model
{
	protected $table = 'tarifarioarchivo';
	protected $primaryKey = 'tarifarioarchivo_id';
	public $timestamps = false;

	protected $casts = [
		'fk_tarifario_id' => 'int'
	];

	protected $fillable = [
		'fk_tarifario_id',
		'tarifarioarchivo_archivo',
		'tarifarioarchivo_descripcion'
	];

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}
}
