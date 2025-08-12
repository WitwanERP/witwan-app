<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pnrsegment
 * 
 * @property int $pnrsegment_id
 * @property int $fk_pnraereo_id
 * @property string $pnrsegment_clase
 * @property string $pnrsegment_vuelo
 * @property Carbon $pnrsegment_fechain
 * @property Carbon $pnrsegment_fechaout
 * @property string $pnrsegment_ciudad
 * @property string $pnrsegment_origen
 * @property string $pnrsegment_co2
 * @property string $pnrsegment_millas
 * @property string $pnrsegment_stop
 * @property string $pnrsegment_ttonbr
 * @property string $pnrsegment_segnbr
 * 
 * @property Pnraereo $pnraereo
 *
 * @package App\Models
 */
class Pnrsegment extends Model
{
	protected $table = 'pnrsegment';
	protected $primaryKey = 'pnrsegment_id';
	public $timestamps = false;

	protected $casts = [
		'fk_pnraereo_id' => 'int',
		'pnrsegment_fechain' => 'datetime',
		'pnrsegment_fechaout' => 'datetime'
	];

	protected $fillable = [
		'fk_pnraereo_id',
		'pnrsegment_clase',
		'pnrsegment_vuelo',
		'pnrsegment_fechain',
		'pnrsegment_fechaout',
		'pnrsegment_ciudad',
		'pnrsegment_origen',
		'pnrsegment_co2',
		'pnrsegment_millas',
		'pnrsegment_stop',
		'pnrsegment_ttonbr',
		'pnrsegment_segnbr'
	];

	public function pnraereo()
	{
		return $this->belongsTo(Pnraereo::class, 'fk_pnraereo_id');
	}
}
