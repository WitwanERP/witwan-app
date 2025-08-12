<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Modoivaventum
 * 
 * @property int $modoivaventa_id
 * @property string $modoivaventa_nombre
 * 
 * @property Collection|Iva[] $ivas
 *
 * @package App\Models
 */
class Modoivaventum extends Model
{
	protected $table = 'modoivaventa';
	protected $primaryKey = 'modoivaventa_id';
	public $timestamps = false;

	protected $fillable = [
		'modoivaventa_nombre'
	];

	public function ivas()
	{
		return $this->hasMany(Iva::class, 'fk_modoivaventa_id');
	}
}
