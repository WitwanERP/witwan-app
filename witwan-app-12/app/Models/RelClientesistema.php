<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelClientesistema
 * 
 * @property int $fk_cliente_id
 * @property int $fk_sistema_id
 * @property int $fk_tarifario_id
 * 
 * @property Cliente $cliente
 * @property Sistema $sistema
 * @property Tarifario $tarifario
 *
 * @package App\Models
 */
class RelClientesistema extends Model
{
	protected $table = 'rel_clientesistema';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'fk_sistema_id' => 'int',
		'fk_tarifario_id' => 'int'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function sistema()
	{
		return $this->belongsTo(Sistema::class, 'fk_sistema_id');
	}

	public function tarifario()
	{
		return $this->belongsTo(Tarifario::class, 'fk_tarifario_id');
	}
}
