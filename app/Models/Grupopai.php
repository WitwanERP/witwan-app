<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Grupopai
 * 
 * @property int $grupopais_id
 * @property string $grupopais_nombre
 *
 * @package App\Models
 */
class Grupopai extends Model
{
	protected $table = 'grupopais';
	protected $primaryKey = 'grupopais_id';
	public $timestamps = false;

	protected $fillable = [
		'grupopais_nombre'
	];
}
