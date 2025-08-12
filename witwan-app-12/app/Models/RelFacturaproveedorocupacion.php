<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelFacturaproveedorocupacion
 * 
 * @property int $fk_facturaproveedor_id
 * @property int $fk_ocupacion_id
 * @property float $monto
 * 
 * @property Facturaproveedor $facturaproveedor
 * @property Servicio $servicio
 *
 * @package App\Models
 */
class RelFacturaproveedorocupacion extends Model
{
	protected $table = 'rel_facturaproveedorocupacion';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_facturaproveedor_id' => 'int',
		'fk_ocupacion_id' => 'int',
		'monto' => 'float'
	];

	protected $fillable = [
		'monto'
	];

	public function facturaproveedor()
	{
		return $this->belongsTo(Facturaproveedor::class, 'fk_facturaproveedor_id');
	}

	public function servicio()
	{
		return $this->belongsTo(Servicio::class, 'fk_ocupacion_id');
	}
}
