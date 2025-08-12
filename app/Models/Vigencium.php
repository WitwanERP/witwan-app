<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Vigencium
 * 
 * @property int $vigencia_id
 * @property int $fk_producto_id
 * @property string $residente
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $vigencia_prioridad
 * @property bool $vigencia_promocional
 * @property Carbon $vigencia_ventaini
 * @property Carbon $vigencia_ventafin
 * @property string $vigencia_descripcion
 * @property int $fk_regimen_id
 * @property int $fk_tarifario_id
 * @property int $vencimiento_checkin
 * @property int $vencimiento_reserva
 * @property Carbon $vencimiento_promocion
 * @property string $nota_promocion
 * @property string $clase
 * @property string $weekdate
 * @property int $noches_minimas
 * @property string $modo_nochesminimas
 * @property int $cargamanual
 * @property string $infoadicional
 * @property int $promocional
 * @property int $promo_noches
 * @property int $promo_pornoches
 * @property bool $acumulable
 * @property string $comentarios
 * @property string $comentarios_en
 * @property string $comentarios_pt
 * @property boolean $weekdays
 * @property bool $web
 * @property int $dias
 * @property int $noches
 * @property float $cotizacionespecial
 * 
 * @property Producto $producto
 * @property Regiman $regiman
 * @property Tarifario $tarifario
 * @property RelOcupacionvigencium|null $rel_ocupacionvigencium
 * @property Collection|Tarifa[] $tarifas
 *
 * @package App\Models
 */
class Vigencium extends Model
{
	protected $table = 'vigencia';
	protected $primaryKey = 'vigencia_id';
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'vigencia_prioridad' => 'int',
		'vigencia_promocional' => 'bool',
		'vigencia_ventaini' => 'datetime',
		'vigencia_ventafin' => 'datetime',
		'fk_regimen_id' => 'int',
		'fk_tarifario_id' => 'int',
		'vencimiento_checkin' => 'int',
		'vencimiento_reserva' => 'int',
		'vencimiento_promocion' => 'datetime',
		'noches_minimas' => 'int',
		'cargamanual' => 'int',
		'promocional' => 'int',
		'promo_noches' => 'int',
		'promo_pornoches' => 'int',
		'acumulable' => 'bool',
		'weekdays' => 'boolean',
		'web' => 'bool',
		'dias' => 'int',
		'noches' => 'int',
		'cotizacionespecial' => 'float'
	];

	protected $fillable = [
		'fk_producto_id',
		'residente',
		'vigencia_ini',
		'vigencia_fin',
		'vigencia_prioridad',
		'vigencia_promocional',
		'vigencia_ventaini',
		'vigencia_ventafin',
		'vigencia_descripcion',
		'fk_regimen_id',
		'fk_tarifario_id',
		'vencimiento_checkin',
		'vencimiento_reserva',
		'vencimiento_promocion',
		'nota_promocion',
		'clase',
		'weekdate',
		'noches_minimas',
		'modo_nochesminimas',
		'cargamanual',
		'infoadicional',
		'promocional',
		'promo_noches',
		'promo_pornoches',
		'acumulable',
		'comentarios',
		'comentarios_en',
		'comentarios_pt',
		'weekdays',
		'web',
		'dias',
		'noches',
		'cotizacionespecial'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}

	public function regiman()
	{
		return $this->belongsTo(Regiman::class, 'fk_regimen_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}

	public function rel_ocupacionvigencium()
	{
		return $this->hasOne(RelOcupacionvigencium::class, 'fk_vigencia_id');
	}

	public function tarifas()
	{
		return $this->hasMany(Tarifa::class, 'fk_vigencia_id');
	}
}
