<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Payroll
 * 
 * @property int $payroll_id
 * @property Carbon $payroll_fecha
 * @property float $payroll_monto
 * @property int $fk_plancuenta_id
 * @property string $payroll_auxiliar
 * @property string $payroll_cc
 *
 * @package App\Models
 */
class Payroll extends Model
{
	protected $table = 'payroll';
	protected $primaryKey = 'payroll_id';
	public $timestamps = false;

	protected $casts = [
		'payroll_fecha' => 'datetime',
		'payroll_monto' => 'float',
		'fk_plancuenta_id' => 'int'
	];

	protected $fillable = [
		'payroll_fecha',
		'payroll_monto',
		'fk_plancuenta_id',
		'payroll_auxiliar',
		'payroll_cc'
	];
}
