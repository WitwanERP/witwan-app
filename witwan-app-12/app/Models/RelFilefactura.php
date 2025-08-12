<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelFilefactura
 * 
 * @property int $fk_file_id
 * @property int $fk_factura_id
 * 
 * @property Factura $factura
 * @property Reserva $reserva
 *
 * @package App\Models
 */
class RelFilefactura extends Model
{
	protected $table = 'rel_filefactura';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_file_id' => 'int',
		'fk_factura_id' => 'int'
	];

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'fk_factura_id');
	}

	public function reserva()
	{
		return $this->belongsTo(Reserva::class, 'fk_file_id');
	}
}
