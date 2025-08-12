<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pkd
 * 
 * @property int $pkd_id
 * @property int $fk_producto_id
 * @property string $pkd_descripcion
 * @property string $pkd_descripcion_en
 * @property string $pkd_descripcion_pg
 * @property string $pkd_imagen
 * @property int $fk_sistema_id
 * @property int $habilitar
 * @property string $pkd_nombre
 * @property string $pkd_nombre_en
 * @property string $pkd_nombre_pg
 * @property string $mascara_submodulo
 * @property int $fk_tarifario_id
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $dias
 * @property int $noches
 * @property int $nochesminimas
 * @property Carbon $venta_ini
 * @property Carbon $venta_fin
 * @property string $estructuraciudad
 * @property string $cotizador
 * @property string $monedaventa
 * @property float $cambioventa
 * @property string $salidas
 * @property string $fechassalida
 * @property string $bases
 * @property int $menores
 * @property int $infoa
 * @property float $markupgeneral
 * @property int $pkdinamico_testigo
 * @property string $combinaciones
 * @property int $precioseteado
 * @property int $tieneaereo
 * @property int $tarifario_exclusivo
 * 
 * @property Producto $producto
 * @property Sistema $sistema
 * @property Tarifario $tarifario
 * @property Collection|Pkdgalerium[] $pkdgaleria
 * @property Collection|Pkditem[] $pkditems
 *
 * @package App\Models
 */
class Pkd extends Model
{
	protected $table = 'pkd';
	protected $primaryKey = 'pkd_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'fk_sistema_id' => 'int',
		'habilitar' => 'int',
		'fk_tarifario_id' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'dias' => 'int',
		'noches' => 'int',
		'nochesminimas' => 'int',
		'venta_ini' => 'datetime',
		'venta_fin' => 'datetime',
		'cambioventa' => 'float',
		'menores' => 'int',
		'infoa' => 'int',
		'markupgeneral' => 'float',
		'pkdinamico_testigo' => 'int',
		'precioseteado' => 'int',
		'tieneaereo' => 'int',
		'tarifario_exclusivo' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'pkd_descripcion',
		'pkd_descripcion_en',
		'pkd_descripcion_pg',
		'pkd_imagen',
		'fk_sistema_id',
		'habilitar',
		'pkd_nombre',
		'pkd_nombre_en',
		'pkd_nombre_pg',
		'mascara_submodulo',
		'fk_tarifario_id',
		'vigencia_ini',
		'vigencia_fin',
		'dias',
		'noches',
		'nochesminimas',
		'venta_ini',
		'venta_fin',
		'estructuraciudad',
		'cotizador',
		'monedaventa',
		'cambioventa',
		'salidas',
		'fechassalida',
		'bases',
		'menores',
		'infoa',
		'markupgeneral',
		'pkdinamico_testigo',
		'combinaciones',
		'precioseteado',
		'tieneaereo',
		'tarifario_exclusivo'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}

	public function pkdgaleria()
	{
		return $this->hasMany(Pkdgalerium::class, 'fk_pkd_id');
	}

	public function pkditems()
	{
		return $this->hasMany(Pkditem::class, 'fk_pkd_id');
	}
}
