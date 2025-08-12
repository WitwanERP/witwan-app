<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Aeropuertociudad
 * 
 * @property string $aeropuertociudad_id
 * @property string $aeropuertociudad_nombre
 *
 * @package App\Models
 */
class Aeropuertociudad extends Model
{
	protected $table = 'aeropuertociudad';
	protected $primaryKey = 'aeropuertociudad_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'aeropuertociudad_nombre'
	];
}
