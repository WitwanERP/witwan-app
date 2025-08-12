<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ctz
 * 
 * @property int $ctz_id
 * @property int $fk_cliente_id
 * @property int $cliente_usuario
 * @property int $facturar_a
 * @property int $fk_agrupado_id
 * @property int $fk_filepadre_id
 * @property int $fk_sistema_id
 * @property int $fk_sistemaaplicacion_id
 * @property int $fk_identidadfiscal_id
 * @property int $fk_usuario_id
 * @property int $agente
 * @property string $fk_filestatus_id
 * @property int $fk_guia_id
 * @property Carbon $fecha_alta
 * @property Carbon $fecha_vencimiento
 * @property Carbon $regdate
 * @property Carbon $um
 * @property int $umu
 * @property int $codigo
 * @property string $tipocodigo
 * @property string $titular_nombre
 * @property string $titular_apellido
 * @property string $titular_email
 * @property int $cerrada
 * @property int $autorizado
 * @property string $observaciones
 * @property string $observaciones_publicas
 * @property string $fk_moneda_id
 * @property float $total
 * @property float $comision
 * @property float $impuestos
 * @property float $totalservicios
 * @property float $iva
 * @property float $gastos
 * @property float $rg_terrestre
 * @property float $rg_trasnporte
 * @property float $cobrado
 * @property float $renta
 * @property float $costo
 * @property float $ivacosto
 * @property float $ajuste
 * @property float $extra1
 * @property float $extra2
 * @property float $extra3
 * @property float $extra4
 * @property string $moneda_factura
 * @property string $codigo_externo
 * @property int $mostrarreprogramados
 *
 * @package App\Models
 */
class Ctz extends Model
{
	protected $table = 'ctz';
	protected $primaryKey = 'ctz_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'cliente_usuario' => 'int',
		'facturar_a' => 'int',
		'fk_agrupado_id' => 'int',
		'fk_filepadre_id' => 'int',
		'fk_sistema_id' => 'int',
		'fk_sistemaaplicacion_id' => 'int',
		'fk_identidadfiscal_id' => 'int',
		'fk_usuario_id' => 'int',
		'agente' => 'int',
		'fk_guia_id' => 'int',
		'fecha_alta' => 'datetime',
		'fecha_vencimiento' => 'datetime',
		'regdate' => 'datetime',
		'um' => 'datetime',
		'umu' => 'int',
		'codigo' => 'int',
		'cerrada' => 'int',
		'autorizado' => 'int',
		'total' => 'float',
		'comision' => 'float',
		'impuestos' => 'float',
		'totalservicios' => 'float',
		'iva' => 'float',
		'gastos' => 'float',
		'rg_terrestre' => 'float',
		'rg_trasnporte' => 'float',
		'cobrado' => 'float',
		'renta' => 'float',
		'costo' => 'float',
		'ivacosto' => 'float',
		'ajuste' => 'float',
		'extra1' => 'float',
		'extra2' => 'float',
		'extra3' => 'float',
		'extra4' => 'float',
		'mostrarreprogramados' => 'int'
	];

	protected $fillable = [
		'fk_cliente_id',
		'cliente_usuario',
		'facturar_a',
		'fk_agrupado_id',
		'fk_filepadre_id',
		'fk_sistema_id',
		'fk_sistemaaplicacion_id',
		'fk_identidadfiscal_id',
		'fk_usuario_id',
		'agente',
		'fk_filestatus_id',
		'fk_guia_id',
		'fecha_alta',
		'fecha_vencimiento',
		'regdate',
		'um',
		'umu',
		'codigo',
		'tipocodigo',
		'titular_nombre',
		'titular_apellido',
		'titular_email',
		'cerrada',
		'autorizado',
		'observaciones',
		'observaciones_publicas',
		'fk_moneda_id',
		'total',
		'comision',
		'impuestos',
		'totalservicios',
		'iva',
		'gastos',
		'rg_terrestre',
		'rg_trasnporte',
		'cobrado',
		'renta',
		'costo',
		'ivacosto',
		'ajuste',
		'extra1',
		'extra2',
		'extra3',
		'extra4',
		'moneda_factura',
		'codigo_externo',
		'mostrarreprogramados'
	];
}
