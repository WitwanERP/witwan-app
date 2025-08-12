<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Imputacion
 * 
 * @property int $imputacion_id
 * @property Carbon $imputacion_fecha
 * @property int $imputacion_orden
 * @property string $imputacion_movimiento1
 * @property string $imputacion_glosa
 *
 * @package App\Models
 */
class Imputacion extends Model
{
	protected $table = 'imputacion';
	protected $primaryKey = 'imputacion_id';
	public $timestamps = false;

	protected $casts = [
		'imputacion_fecha' => 'datetime',
		'imputacion_orden' => 'int'
	];

	protected $fillable = [
		'imputacion_fecha',
		'imputacion_orden',
		'imputacion_movimiento1',
		'imputacion_glosa'
	];
}
