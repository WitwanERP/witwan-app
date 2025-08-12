<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tipofactura
 * 
 * @property int $tipofactura_id
 * @property string $tipofactura_nombre
 * 
 * @property Collection|Condicioniva[] $condicionivas
 *
 * @package App\Models
 */
class Tipofactura extends Model
{
	protected $table = 'tipofactura';
	protected $primaryKey = 'tipofactura_id';
	public $timestamps = false;

	protected $fillable = [
		'tipofactura_nombre'
	];

	public function condicionivas()
	{
		return $this->hasMany(Condicioniva::class, 'fk_tipofactura_id');
	}
}
