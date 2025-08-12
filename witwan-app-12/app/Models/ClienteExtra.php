<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ClienteExtra
 * 
 * @property int $fk_cliente_id
 * @property Carbon $regdate
 * @property string $extra_nombre
 * @property string $extra_valor
 *
 * @package App\Models
 */
class ClienteExtra extends Model
{
	protected $table = 'cliente_extra';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'fk_cliente_id',
		'regdate',
		'extra_nombre',
		'extra_valor'
	];
}
