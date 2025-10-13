<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Conciliabanco
 *
 * @property int $conciliabanco_id
 * @property int $fk_plancuenta_id
 * @property string $conciliabanco_nombre
 * @property Carbon $conciliabanco_in
 * @property Carbon $conciliabanco_out
 * @property int $conciliabanco_cerrado
 * @property float $conciliabanco_saldo
 * @property string $archivo
 *
 * @property Plancuenta $plancuenta
 *
 * @package App\Models
 */
class Conciliabanco extends Model
{
	protected $table = 'conciliabanco';
	protected $primaryKey = 'conciliabanco_id';
	public $timestamps = false;

	protected $casts = [
		'fk_plancuenta_id' => 'int',
		'conciliabanco_in' => 'datetime',
		'conciliabanco_out' => 'datetime',
		'conciliabanco_cerrado' => 'int',
		'conciliabanco_saldo' => 'float'
	];

	protected $fillable = [
		'fk_plancuenta_id',
		'conciliabanco_nombre',
		'conciliabanco_in',
		'conciliabanco_out',
		'conciliabanco_cerrado',
		'conciliabanco_saldo',
		'archivo'
	];

	public function plancuenta()
	{
		return $this->belongsTo(Plancuenta::class, 'fk_plancuenta_id');
	}
}
