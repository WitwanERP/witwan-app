<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Creditoextra
 * 
 * @property int $creditoextra_id
 * @property Carbon $creditoextra_fecha
 * @property int $fk_cliente_id
 * @property int $fk_usuario_id
 * @property float $creditoextra_monto
 * @property Carbon $regdate
 * 
 * @property Cliente $cliente
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Creditoextra extends Model
{
	protected $table = 'creditoextra';
	protected $primaryKey = 'creditoextra_id';
	public $timestamps = false;

	protected $casts = [
		'creditoextra_fecha' => 'datetime',
		'fk_cliente_id' => 'int',
		'fk_usuario_id' => 'int',
		'creditoextra_monto' => 'float',
		'regdate' => 'datetime'
	];

	protected $fillable = [
		'creditoextra_fecha',
		'fk_cliente_id',
		'fk_usuario_id',
		'creditoextra_monto',
		'regdate'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
