<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Servicioctz
 * 
 * @property int $servicio_id
 * @property string $servicio_nombre
 * @property int $fk_reserva_id
 * @property string $fk_tipoproducto_id
 * @property int $fk_producto_id
 * @property int $fk_proveedor_id
 * @property int $fk_prestador_id
 * @property int $fk_ciudad_id
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $adultos
 * @property int $menores
 * @property int $juniors
 * @property int $infante
 * @property int $jubilado
 * @property int $fk_tarifacategoria_id
 * @property int $fk_regimen_id
 * @property int $fk_base_id
 * @property string $status
 * @property string $moneda_costo
 * @property float $iva
 * @property string $fk_moneda_id
 * @property float $impuestos
 * @property float $comision
 * @property float $costo
 * @property float $iva_costo
 * @property float $total
 * @property float $totalservicio
 * @property float $rg_terrestre
 * @property float $rg_aereo
 * @property float $extra1
 * @property float $extra2
 * @property float $extra3
 * @property float $extra4
 * @property string $paxes
 * @property string $info
 * @property Carbon $vencimiento_proveedor
 * @property string $nro_confirmacion
 * @property string $mail_proveedor
 * @property string $comentarios
 * @property string $retira_voucher
 * @property string $autoriza_evoucher
 * @property string $texto_voucher
 * @property int $prev_file_id
 * @property Carbon $regdate
 * @property float $cotcosto
 * @property float $cotventa
 * @property string $json_extra
 *
 * @package App\Models
 */
class Servicioctz extends Model
{
	protected $table = 'servicioctz';
	protected $primaryKey = 'servicio_id';
	public $timestamps = false;

	protected $casts = [
		'fk_reserva_id' => 'int',
		'fk_producto_id' => 'int',
		'fk_proveedor_id' => 'int',
		'fk_prestador_id' => 'int',
		'fk_ciudad_id' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime',
		'adultos' => 'int',
		'menores' => 'int',
		'juniors' => 'int',
		'infante' => 'int',
		'jubilado' => 'int',
		'fk_tarifacategoria_id' => 'int',
		'fk_regimen_id' => 'int',
		'fk_base_id' => 'int',
		'iva' => 'float',
		'impuestos' => 'float',
		'comision' => 'float',
		'costo' => 'float',
		'iva_costo' => 'float',
		'total' => 'float',
		'totalservicio' => 'float',
		'rg_terrestre' => 'float',
		'rg_aereo' => 'float',
		'extra1' => 'float',
		'extra2' => 'float',
		'extra3' => 'float',
		'extra4' => 'float',
		'vencimiento_proveedor' => 'datetime',
		'prev_file_id' => 'int',
		'regdate' => 'datetime',
		'cotcosto' => 'float',
		'cotventa' => 'float'
	];

	protected $fillable = [
		'servicio_nombre',
		'fk_reserva_id',
		'fk_tipoproducto_id',
		'fk_producto_id',
		'fk_proveedor_id',
		'fk_prestador_id',
		'fk_ciudad_id',
		'vigencia_ini',
		'vigencia_fin',
		'adultos',
		'menores',
		'juniors',
		'infante',
		'jubilado',
		'fk_tarifacategoria_id',
		'fk_regimen_id',
		'fk_base_id',
		'status',
		'moneda_costo',
		'iva',
		'fk_moneda_id',
		'impuestos',
		'comision',
		'costo',
		'iva_costo',
		'total',
		'totalservicio',
		'rg_terrestre',
		'rg_aereo',
		'extra1',
		'extra2',
		'extra3',
		'extra4',
		'paxes',
		'info',
		'vencimiento_proveedor',
		'nro_confirmacion',
		'mail_proveedor',
		'comentarios',
		'retira_voucher',
		'autoriza_evoucher',
		'texto_voucher',
		'prev_file_id',
		'regdate',
		'cotcosto',
		'cotventa',
		'json_extra'
	];
}
