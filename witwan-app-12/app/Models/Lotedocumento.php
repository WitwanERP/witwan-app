<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Lotedocumento
 * 
 * @property int $lotedocumento_id
 * @property Carbon $regdate
 * @property string $lotedocumento_respuesta
 * @property string $statuslote
 *
 * @package App\Models
 */
class Lotedocumento extends Model
{
	protected $table = 'lotedocumento';
	protected $primaryKey = 'lotedocumento_id';
	public $timestamps = false;

	protected $casts = [
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'regdate',
		'lotedocumento_respuesta',
		'statuslote'
	];
}
