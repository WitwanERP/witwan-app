<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Gd
 * 
 * @property string $gds_id
 * @property string $gds_nombre
 * 
 * @property Collection|Pnraereo[] $pnraereos
 *
 * @package App\Models
 */
class Gd extends Model
{
	protected $table = 'gds';
	protected $primaryKey = 'gds_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'gds_nombre'
	];

	public function pnraereos()
	{
		return $this->hasMany(Pnraereo::class, 'fk_gds_id');
	}
}
