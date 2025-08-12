<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pkdinamico
 * 
 * @property int $fk_producto_id
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
 *
 * @package App\Models
 */
class Pkdinamico extends Model
{
	protected $table = 'pkdinamico';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
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
		'precioseteado' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
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
		'precioseteado'
	];
}
