<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tag
 * 
 * @property int $tag_id
 * @property string $tag_nombre
 * @property int $tag_rup
 * @property int $tag_ruc
 * 
 * @property Collection|Cliente[] $clientes
 * @property Collection|Pasajero[] $pasajeros
 *
 * @package App\Models
 */
class Tag extends Model
{
	protected $table = 'tag';
	protected $primaryKey = 'tag_id';
	public $timestamps = false;

	protected $casts = [
		'tag_rup' => 'int',
		'tag_ruc' => 'int'
	];

	protected $fillable = [
		'tag_nombre',
		'tag_rup',
		'tag_ruc'
	];

	public function clientes()
	{
		return $this->belongsToMany(Cliente::class, 'rel_clientetag', 'fk_tag_id', 'fk_cliente_id');
	}

	public function pasajeros()
	{
		return $this->belongsToMany(Pasajero::class, 'rel_pasajerotag', 'fk_tag_id', 'fk_pasajero_id');
	}
}
