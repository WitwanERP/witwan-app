<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Traslado
 * 
 * @property int $fk_producto_id
 * @property int $grupo
 * @property int $subgrupo
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property string $tipo_vuelo
 * @property int $punto_desde
 * @property string $punto_desde_tipo
 * @property int $punto_hacia
 * @property string $punto_hacia_tipo
 * @property string $politica_edades
 * @property string $disponibilidad
 * @property string $vehiculo
 * @property int $venc_post_reserva
 * @property int $venc_pre_checkin
 * @property int $maxsiniva
 * 
 * @property Producto $producto
 *
 * @package App\Models
 */
class Traslado extends Model
{
	protected $table = 'traslado';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_producto_id' => 'int',
		'grupo' => 'int',
		'subgrupo' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'punto_desde' => 'int',
		'punto_hacia' => 'int',
		'venc_post_reserva' => 'int',
		'venc_pre_checkin' => 'int',
		'maxsiniva' => 'int'
	];

	protected $fillable = [
		'fk_producto_id',
		'grupo',
		'subgrupo',
		'vigencia_ini',
		'vigencia_fin',
		'tipo_vuelo',
		'punto_desde',
		'punto_desde_tipo',
		'punto_hacia',
		'punto_hacia_tipo',
		'politica_edades',
		'disponibilidad',
		'vehiculo',
		'venc_post_reserva',
		'venc_pre_checkin',
		'maxsiniva'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'fk_producto_id');
	}
}
