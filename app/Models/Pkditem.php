<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pkditem
 * 
 * @property int $pkditem_id
 * @property int $item
 * @property int $fk_pkd_id
 * @property string $fk_tipoproducto_id
 * @property int $fk_ciudad_id
 * @property int $itinerario_ini
 * @property int $itinerario_fin
 * @property float $markup
 * @property Carbon $fecha
 * 
 * @property Ciudad $ciudad
 * @property Pkd $pkd
 * @property Submodulo $submodulo
 * @property Collection|Pkdproducto[] $pkdproductos
 *
 * @package App\Models
 */
class Pkditem extends Model
{
	protected $table = 'pkditem';
	protected $primaryKey = 'pkditem_id';
	public $timestamps = false;

	protected $casts = [
		'item' => 'int',
		'fk_pkd_id' => 'int',
		'fk_ciudad_id' => 'int',
		'itinerario_ini' => 'int',
		'itinerario_fin' => 'int',
		'markup' => 'float',
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'item',
		'fk_pkd_id',
		'fk_tipoproducto_id',
		'fk_ciudad_id',
		'itinerario_ini',
		'itinerario_fin',
		'markup',
		'fecha'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function pkd()
	{
		return $this->belongsTo(Pkd::class, 'fk_pkd_id');
	}

	public function submodulo()
	{
		return $this->belongsTo(Submodulo::class, 'fk_tipoproducto_id');
	}

	public function pkdproductos()
	{
		return $this->hasMany(Pkdproducto::class, 'fk_pkditem_id');
	}
}
