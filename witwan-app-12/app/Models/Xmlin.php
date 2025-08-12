<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Xmlin
 * 
 * @property int $xmlin_id
 * @property string $xmlin_nombre
 * @property int $xmlin_status
 * @property float $xmlin_markup
 * @property int $xmlin_prioridad
 *
 * @package App\Models
 */
class Xmlin extends Model
{
	protected $table = 'xmlin';
	protected $primaryKey = 'xmlin_id';
	public $timestamps = false;

	protected $casts = [
		'xmlin_status' => 'int',
		'xmlin_markup' => 'float',
		'xmlin_prioridad' => 'int'
	];

	protected $fillable = [
		'xmlin_nombre',
		'xmlin_status',
		'xmlin_markup',
		'xmlin_prioridad'
	];
}
