<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarjetacredito
 * 
 * @property int $tarjetacredito_id
 * @property string $tarjetacredito_nombre
 *
 * @package App\Models
 */
class Tarjetacredito extends Model
{
	protected $table = 'tarjetacredito';
	protected $primaryKey = 'tarjetacredito_id';
	public $timestamps = false;

	protected $fillable = [
		'tarjetacredito_nombre'
	];
}
