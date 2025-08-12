<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Facturaaerolinea
 * 
 * @property int $facturaaerolinea_id
 * @property int $fk_servicio_id
 * @property string $numeroticket
 * @property int $fk_factura_id
 * @property int $fk_notacredito_id
 * 
 * @property Factura $factura
 * @property Notacredito $notacredito
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class Facturaaerolinea extends Model
{
	protected $table = 'facturaaerolinea';
	protected $primaryKey = 'facturaaerolinea_id';
	public $timestamps = false;

	protected $casts = [
		'fk_servicio_id' => 'int',
		'fk_factura_id' => 'int',
		'fk_notacredito_id' => 'int'
	];

	protected $fillable = [
		'fk_servicio_id',
		'numeroticket',
		'fk_factura_id',
		'fk_notacredito_id'
	];

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'facturaaerolinea_id');
	}

	public function notacredito()
	{
		return $this->belongsTo(Notacredito::class, 'fk_notacredito_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_servicio_id');
	}
}
