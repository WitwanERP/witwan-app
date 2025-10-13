<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Moneda
 *
 * @property string $moneda_id
 * @property string $moneda_nombre
 * @property string $descripcion
 * @property int $orden
 * @property string $moneda_basica
 * @property string $iso_code
 * @property int $publicaweb
 *
 * @property Alojamiento|null $alojamiento
 * @property Collection|Canje[] $canjes
 * @property Collection|Cliente[] $clientes
 * @property Collection|Cotizacion[] $cotizacions
 * @property Collection|Cupoaereo[] $cupoaereos
 * @property Collection|Factura[] $facturas
 * @property Collection|Facturaproveedor[] $facturaproveedors
 * @property Collection|Modelocomision[] $modelocomisions
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notadebito[] $notadebitos
 * @property Collection|Ordenadmin[] $ordenadmins
 * @property Collection|Plancuenta[] $plancuenta
 * @property Collection|Pnraereo[] $pnraereos
 * @property Collection|Recibo[] $recibos
 * @property RelFilerecibo|null $rel_filerecibo
 * @property RelOrdenadminocupacion|null $rel_ordenadminocupacion
 * @property Collection|Reportebsptkt[] $reportebsptkts
 * @property Collection|Reserva[] $reservas
 * @property Collection|Servicio[] $servicios
 * @property Collection|Tarifario[] $tarifarios
 * @property Collection|Usuariocomision[] $usuariocomisions
 *
 * @package App\Models
 */
class Moneda extends Model
{
	protected $table = 'moneda';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'orden' => 'int',
		'publicaweb' => 'int'
	];

	protected $fillable = [
		'moneda_id',
		'moneda_nombre',
		'descripcion',
		'orden',
		'moneda_basica',
		'iso_code',
		'publicaweb'
	];

	public function alojamiento()
	{
		return $this->hasOne(Alojamiento::class, 'fk_moneda_id', 'moneda_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_moneda_id', 'moneda_id');
	}

	public function clientes()
	{
		return $this->hasMany(Cliente::class, 'fk_moneda_id', 'moneda_id');
	}

	public function cotizacions()
	{
		return $this->hasMany(Cotizacion::class, 'cotizacion_moneda', 'moneda_id');
	}

	public function cupoaereos()
	{
		return $this->hasMany(Cupoaereo::class, 'fk_moneda_id', 'moneda_id');
	}

	public function facturas()
	{
		return $this->hasMany(Factura::class, 'fk_moneda_id', 'moneda_id');
	}

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_moneda_id', 'moneda_id');
	}

	public function modelocomisions()
	{
		return $this->hasMany(Modelocomision::class, 'fk_moneda_id', 'moneda_id');
	}

	public function modelofees()
	{
		return $this->hasMany(Modelofee::class, 'fk_moneda_id', 'moneda_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_moneda_id', 'moneda_id');
	}

	public function notadebitos()
	{
		return $this->hasMany(Notadebito::class, 'fk_moneda_id', 'moneda_id');
	}

	public function ordenadmins()
	{
		return $this->hasMany(Ordenadmin::class, 'fk_moneda_id', 'moneda_id');
	}

	public function plancuenta()
	{
		return $this->hasMany(Plancuenta::class, 'fk_moneda_id', 'moneda_id');
	}

	public function pnraereos()
	{
		return $this->hasMany(Pnraereo::class, 'fk_moneda_id', 'moneda_id');
	}

	public function recibos()
	{
		return $this->hasMany(Recibo::class, 'fk_moneda_id', 'moneda_id');
	}

	public function rel_filerecibo()
	{
		return $this->hasOne(RelFilerecibo::class, 'fk_moneda_id', 'moneda_id');
	}

	public function rel_ordenadminocupacion()
	{
		return $this->hasOne(RelOrdenadminocupacion::class, 'fk_moneda_id', 'moneda_id');
	}

	public function reportebsptkts()
	{
		return $this->hasMany(Reportebsptkt::class, 'fk_moneda_id', 'moneda_id');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'fk_moneda_id', 'moneda_id');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_moneda_id', 'moneda_id');
	}

	public function tarifarios()
	{
		return $this->hasMany(Tarifario::class, 'fk_moneda_id', 'moneda_id');
	}

	public function usuariocomisions()
	{
		return $this->hasMany(Usuariocomision::class, 'fk_moneda_id', 'moneda_id');
	}
}
