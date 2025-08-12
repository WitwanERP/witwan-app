<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Condicioniva
 * 
 * @property int $condicioniva_id
 * @property int $fk_tipofactura_id
 * @property string $condicioniva_nombre
 * @property float $porcentaje
 * @property string $incluido
 * 
 * @property Tipofactura $tipofactura
 * @property Collection|Cliente[] $clientes
 * @property Collection|Identidadfiscal[] $identidadfiscals
 * @property Collection|Proveedor[] $proveedors
 *
 * @package App\Models
 */
class Condicioniva extends Model
{
	protected $table = 'condicioniva';
	protected $primaryKey = 'condicioniva_id';
	public $timestamps = false;

	protected $casts = [
		'fk_tipofactura_id' => 'int',
		'porcentaje' => 'float'
	];

	protected $fillable = [
		'fk_tipofactura_id',
		'condicioniva_nombre',
		'porcentaje',
		'incluido'
	];

	public function tipofactura()
	{
		return $this->belongsTo(Tipofactura::class, 'fk_tipofactura_id');
	}

	public function clientes()
	{
		return $this->hasMany(Cliente::class, 'fk_condicioniva_id');
	}

	public function identidadfiscals()
	{
		return $this->hasMany(Identidadfiscal::class, 'fk_condicioniva_id');
	}

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'fk_condicioniva_id');
	}
}
