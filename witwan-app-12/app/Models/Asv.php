<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Asv
 * 
 * @property string $asv
 *
 * @package App\Models
 */
class Asv extends Model
{
	protected $table = 'asv';
	protected $primaryKey = 'asv';
	public $incrementing = false;
	public $timestamps = false;
}
