<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Itemgasto
 * 
 * @property int $itemgasto_id
 * @property string $itemgasto_nombre
 * @property int $itemgasto_activo
 * 
 * @property Collection|Facturaproveedor[] $facturaproveedors
 *
 * @package App\Models
 */
class Itemgasto extends Model
{
	protected $table = 'itemgasto';
	protected $primaryKey = 'itemgasto_id';
	public $timestamps = false;

	protected $casts = [
		'itemgasto_activo' => 'int'
	];

	protected $fillable = [
		'itemgasto_nombre',
		'itemgasto_activo'
	];

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_itemgasto_id');
	}
}
