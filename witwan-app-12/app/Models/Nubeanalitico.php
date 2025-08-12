<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Nubeanalitico
 * 
 * @property int $nubeanalitico_id
 * @property int $fk_plancuenta_id
 * @property int $voucher
 * @property string $tipovoucher
 * @property string $nrodoc
 * @property string $fecha
 * @property Carbon $nubeanalitico_fecha
 * @property int $fk_proveedor_id
 * @property int $fk_cliente_id
 * @property int $fk_vendedor_id
 * @property float $debe
 * @property float $haber
 * @property string $glosa1
 * @property string $glosa2
 *
 * @package App\Models
 */
class Nubeanalitico extends Model
{
	protected $table = 'nubeanalitico';
	protected $primaryKey = 'nubeanalitico_id';
	public $timestamps = false;

	protected $casts = [
		'fk_plancuenta_id' => 'int',
		'voucher' => 'int',
		'nubeanalitico_fecha' => 'datetime',
		'fk_proveedor_id' => 'int',
		'fk_cliente_id' => 'int',
		'fk_vendedor_id' => 'int',
		'debe' => 'float',
		'haber' => 'float'
	];

	protected $fillable = [
		'fk_plancuenta_id',
		'voucher',
		'tipovoucher',
		'nrodoc',
		'fecha',
		'nubeanalitico_fecha',
		'fk_proveedor_id',
		'fk_cliente_id',
		'fk_vendedor_id',
		'debe',
		'haber',
		'glosa1',
		'glosa2'
	];
}
