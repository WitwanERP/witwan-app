<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RelFilerecibo
 * 
 * @property int $fk_file_id
 * @property int $fk_recibo_id
 * @property Carbon $fecha
 * @property string $fk_moneda_id
 * @property float $monto
 * 
 * @property Moneda $moneda
 * @property Recibo $recibo
 * @property Reserva $reserva
 *
 * @package App\Models
 */
class RelFilerecibo extends Model
{
	protected $table = 'rel_filerecibo';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_file_id' => 'int',
		'fk_recibo_id' => 'int',
		'fecha' => 'datetime',
		'monto' => 'float'
	];

	protected $fillable = [
		'fk_file_id',
		'fk_recibo_id',
		'fecha',
		'fk_moneda_id',
		'monto'
	];

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function recibo()
	{
		return $this->belongsTo(Recibo::class, 'fk_recibo_id');
	}

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}
}
