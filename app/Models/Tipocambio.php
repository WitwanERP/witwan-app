<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tipocambio
 * 
 * @property int $tipocambio_id
 * @property Carbon $tipocambio_fecha
 * @property string $fk_moneda_id
 * @property float $tipocambio_valor
 *
 * @package App\Models
 */
class Tipocambio extends Model
{
	protected $table = 'tipocambio';
	protected $primaryKey = 'tipocambio_id';
	public $timestamps = false;

	protected $casts = [
		'tipocambio_fecha' => 'datetime',
		'tipocambio_valor' => 'float'
	];

	protected $fillable = [
		'tipocambio_fecha',
		'fk_moneda_id',
		'tipocambio_valor'
	];
}
