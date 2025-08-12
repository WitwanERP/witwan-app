<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Aeropuerto
 * 
 * @property string $aeropuertos_nombre
 * @property string $aeropuertos_iata
 *
 * @package App\Models
 */
class Aeropuerto extends Model
{
	protected $table = 'aeropuertos';
	protected $primaryKey = 'aeropuertos_iata';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'aeropuertos_nombre'
	];
}
