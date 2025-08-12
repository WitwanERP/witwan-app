<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Modelocomision
 * 
 * @property int $modelocomision_id
 * @property string $fk_moneda_id
 * @property string $modelocomision_nombre
 * @property string $modelocomision_tipo
 * @property string $modelocomision_esquema
 * @property string $modelocomision_basecomision
 * @property string $modelocomision_basecalculo
 * @property string $modelocomision_asignacion
 * @property int $modelocomision_prioridad
 * @property int $fk_usuario_id
 * @property int $fk_cliente_id
 * @property string $fk_submodulo_id
 * @property string $modelocomision_prefijo
 * @property int $in1
 * @property int $out1
 * @property float $porcentaje1
 * @property int $in2
 * @property int $out2
 * @property float $porcentaje2
 * @property int $in3
 * @property int $out3
 * @property float $porcentaje3
 * @property int $in4
 * @property int $out4
 * @property float $porcentaje4
 * @property int $in5
 * @property int $out5
 * @property float $porcentaje5
 * @property int $meta_anual
 * @property Carbon $vigencia_in
 * @property Carbon $vigencia_out
 * 
 * @property Cliente $cliente
 * @property Moneda $moneda
 * @property Submodulo $submodulo
 * @property Usuario $usuario
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Modelocomision extends Model
{
	protected $table = 'modelocomision';
	protected $primaryKey = 'modelocomision_id';
	public $timestamps = false;

	protected $casts = [
		'modelocomision_prioridad' => 'int',
		'fk_usuario_id' => 'int',
		'fk_cliente_id' => 'int',
		'in1' => 'int',
		'out1' => 'int',
		'porcentaje1' => 'float',
		'in2' => 'int',
		'out2' => 'int',
		'porcentaje2' => 'float',
		'in3' => 'int',
		'out3' => 'int',
		'porcentaje3' => 'float',
		'in4' => 'int',
		'out4' => 'int',
		'porcentaje4' => 'float',
		'in5' => 'int',
		'out5' => 'int',
		'porcentaje5' => 'float',
		'meta_anual' => 'int',
		'vigencia_in' => 'datetime',
		'vigencia_out' => 'datetime'
	];

	protected $fillable = [
		'fk_moneda_id',
		'modelocomision_nombre',
		'modelocomision_tipo',
		'modelocomision_esquema',
		'modelocomision_basecomision',
		'modelocomision_basecalculo',
		'modelocomision_asignacion',
		'modelocomision_prioridad',
		'fk_usuario_id',
		'fk_cliente_id',
		'fk_submodulo_id',
		'modelocomision_prefijo',
		'in1',
		'out1',
		'porcentaje1',
		'in2',
		'out2',
		'porcentaje2',
		'in3',
		'out3',
		'porcentaje3',
		'in4',
		'out4',
		'porcentaje4',
		'in5',
		'out5',
		'porcentaje5',
		'meta_anual',
		'vigencia_in',
		'vigencia_out'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_submodulo_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function usuarios()
	{
		return $this->belongsToMany(Usuario::class, 'rel_usuariomodelocomision', 'fk_modelocomision_id', 'fk_usuario_id')
					->withPivot('rel_usuariomodelocomision_id');
	}
}
