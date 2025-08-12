<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Dataoff
 * 
 * @property int $dataoff_id
 * @property string $dataoff_nombre
 * @property string $dataoff_ide
 * @property string $dataoff_ws
 * @property string $dataoff_valor
 *
 * @package App\Models
 */
class Dataoff extends Model
{
	protected $table = 'dataoff';
	protected $primaryKey = 'dataoff_id';
	public $timestamps = false;

	protected $fillable = [
		'dataoff_nombre',
		'dataoff_ide',
		'dataoff_ws',
		'dataoff_valor'
	];
}
