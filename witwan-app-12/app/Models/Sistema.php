<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Sistema
 * 
 * @property int $sistema_id
 * @property string $clave_texto
 * @property string $sistema_nombre
 * @property string $sistema_nombre_en
 * @property string $sistema_nombre_pg
 * @property string $sistema_codigo
 * @property string $habilitado
 * @property string $css_class
 * @property string $sistema_link
 * @property int $item_order
 * @property string $ivaincluido
 * @property float $extra1
 * @property float $extra2
 * @property float $extra3
 * @property float $extra4
 * @property string $texto_extra1
 * @property string $texto_extra2
 * @property string $texto_extra3
 * @property string $texto_extra4
 * @property string $liquidacion
 * @property int $admitetarifario
 * @property string $config
 * 
 * @property Collection|Canje[] $canjes
 * @property Collection|Identidadfiscal[] $identidadfiscals
 * @property Collection|Iva[] $ivas
 * @property Collection|Negocio[] $negocios
 * @property Collection|Pkd[] $pkds
 * @property Collection|Producto[] $productos
 * @property Collection|Cliente[] $clientes
 * @property Collection|Reserva[] $reservas
 * @property Collection|Tarifario[] $tarifarios
 *
 * @package App\Models
 */
class Sistema extends Model
{
	protected $table = 'sistema';
	protected $primaryKey = 'sistema_id';
	public $timestamps = false;

	protected $casts = [
		'item_order' => 'int',
		'extra1' => 'float',
		'extra2' => 'float',
		'extra3' => 'float',
		'extra4' => 'float',
		'admitetarifario' => 'int'
	];

	protected $fillable = [
		'clave_texto',
		'sistema_nombre',
		'sistema_nombre_en',
		'sistema_nombre_pg',
		'sistema_codigo',
		'habilitado',
		'css_class',
		'sistema_link',
		'item_order',
		'ivaincluido',
		'extra1',
		'extra2',
		'extra3',
		'extra4',
		'texto_extra1',
		'texto_extra2',
		'texto_extra3',
		'texto_extra4',
		'liquidacion',
		'admitetarifario',
		'config'
	];

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_sistema_id');
	}

	public function identidadfiscals()
	{
		return $this->hasMany(Identidadfiscal::class, 'fk_sistema_id');
	}

	public function ivas()
	{
		return $this->hasMany(Iva::class, 'fk_sistema_id');
	}

	public function negocios()
	{
		return $this->hasMany(Negocio::class, 'fk_sistema_id');
	}

	public function pkds()
	{
		return $this->hasMany(Pkd::class, 'fk_sistema_id');
	}

	public function productos()
	{
		return $this->hasMany(Producto::class, 'fk_sistema_id');
	}

	public function clientes()
	{
		return $this->belongsToMany(Cliente::class, 'rel_clientesistema', 'fk_sistema_id', 'fk_cliente_id')
					->withPivot('fk_tarifario_id');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'fk_sistemaaplicacion_id');
	}

	public function tarifarios()
	{
		return $this->hasMany(Tarifario::class, 'fk_sistema_id');
	}
}
