<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Loginterfase
 * 
 * @property int $loginterfase_id
 * @property Carbon $loginterfase_fecha
 * @property string $loginterfase_tipo
 * @property string $loginterfase_texto
 *
 * @package App\Models
 */
class Loginterfase extends Model
{
	protected $table = 'loginterfase';
	protected $primaryKey = 'loginterfase_id';
	public $timestamps = false;

	protected $casts = [
		'loginterfase_fecha' => 'datetime'
	];

	protected $fillable = [
		'loginterfase_fecha',
		'loginterfase_tipo',
		'loginterfase_texto'
	];
}
