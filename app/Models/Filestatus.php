<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Filestatus
 * 
 * @property string $filestatus_id
 * @property string $filestatus_nombre
 * @property string $filestatus_nombre_en
 *
 * @package App\Models
 */
class Filestatus extends Model
{
	protected $table = 'filestatus';
	protected $primaryKey = 'filestatus_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'filestatus_nombre',
		'filestatus_nombre_en'
	];
}
