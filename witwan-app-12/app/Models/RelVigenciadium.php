<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelVigenciadium
 * 
 * @property int $fk_vigencia_id
 * @property int $fk_dia_id
 *
 * @package App\Models
 */
class RelVigenciadium extends Model
{
	protected $table = 'rel_vigenciadia';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_vigencia_id' => 'int',
		'fk_dia_id' => 'int'
	];

	protected $fillable = [
		'fk_vigencia_id',
		'fk_dia_id'
	];
}
