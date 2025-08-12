<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Aerolinea
 * 
 * @property int $aerolinea_id
 * @property int $fk_proveedor_id
 * @property int $fk_cliente_id
 * @property string $aerolinea_nombre
 * @property float $comisionbsp
 * @property string $aerolinea_codigo
 * @property string $aerolinea_bspcode
 * @property string $aerolinea_icao
 * @property string $callsign
 * @property string $test
 * 
 * @property Cliente $cliente
 * @property Proveedor $proveedor
 * @property Collection|Aereo[] $aereos
 * @property Collection|Cupoaereo[] $cupoaereos
 * @property Collection|Pnraereo[] $pnraereos
 *
 * @package App\Models
 */
class Aerolinea extends Model
{
	protected $table = 'aerolinea';
	protected $primaryKey = 'aerolinea_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'fk_cliente_id' => 'int',
		'comisionbsp' => 'float'
	];

	protected $fillable = [
		'fk_proveedor_id',
		'fk_cliente_id',
		'aerolinea_nombre',
		'comisionbsp',
		'aerolinea_codigo',
		'aerolinea_bspcode',
		'aerolinea_icao',
		'callsign',
		'test'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function aereos()
	{
		return $this->hasMany(Aereo::class, 'fk_aerolinea_id');
	}

	public function cupoaereos()
	{
		return $this->hasMany(Cupoaereo::class, 'fk_aerolinea_id');
	}

	public function pnraereos()
	{
		return $this->hasMany(Pnraereo::class, 'fk_aerolinea_id');
	}
}
