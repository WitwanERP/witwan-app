<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Autorizacionpnr
 * 
 * @property int $autorizacionpnr_id
 * @property string $autorizacionpnr_locator
 * @property Carbon $autorizacionpnr_fecha
 * @property string $autorizacionpnr_firma
 * @property int $aprobador
 * @property float $autorizacionpnr_ticket
 * @property float $autorizacionpnr_limite
 * @property float $autorizacionpnr_utilizado
 * @property string $autorizacionpnr_codigo
 * @property int $autorizacionpnr_status
 * @property int $autorizacionpnr_tst
 *
 * @package App\Models
 */
class Autorizacionpnr extends Model
{
	protected $table = 'autorizacionpnr';
	protected $primaryKey = 'autorizacionpnr_id';
	public $timestamps = false;

	protected $casts = [
		'autorizacionpnr_fecha' => 'datetime',
		'aprobador' => 'int',
		'autorizacionpnr_ticket' => 'float',
		'autorizacionpnr_limite' => 'float',
		'autorizacionpnr_utilizado' => 'float',
		'autorizacionpnr_status' => 'int',
		'autorizacionpnr_tst' => 'int'
	];

	protected $fillable = [
		'autorizacionpnr_locator',
		'autorizacionpnr_fecha',
		'autorizacionpnr_firma',
		'aprobador',
		'autorizacionpnr_ticket',
		'autorizacionpnr_limite',
		'autorizacionpnr_utilizado',
		'autorizacionpnr_codigo',
		'autorizacionpnr_status',
		'autorizacionpnr_tst'
	];
}
