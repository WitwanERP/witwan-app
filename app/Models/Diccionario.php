<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Diccionario
 * 
 * @property int $diccionario_id
 * @property string $diccionario_key
 * @property string $fk_idioma_id
 * @property string $diccionario_val
 *
 * @package App\Models
 */
class Diccionario extends Model
{
	protected $table = 'diccionario';
	protected $primaryKey = 'diccionario_id';
	public $timestamps = false;

	protected $fillable = [
		'diccionario_key',
		'fk_idioma_id',
		'diccionario_val'
	];
}
