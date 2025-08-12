<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Agenteemisor
 * 
 * @property string $agenteemisor_id
 * @property string $agenteemisor_nombre
 * @property string $fk_gds_id
 *
 * @package App\Models
 */
class Agenteemisor extends Model
{
	protected $table = 'agenteemisor';
	protected $primaryKey = 'agenteemisor_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'agenteemisor_nombre',
		'fk_gds_id'
	];
}
