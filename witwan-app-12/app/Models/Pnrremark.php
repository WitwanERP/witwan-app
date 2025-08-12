<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Pnrremark
 * 
 * @property int $pnrremark_id
 * @property int $fk_pnraereo_id
 * @property string $pnrremark_tipo
 * @property string $pnrremark_valor
 * 
 * @property Pnraereo $pnraereo
 *
 * @package App\Models
 */
class Pnrremark extends Model
{
	protected $table = 'pnrremark';
	protected $primaryKey = 'pnrremark_id';
	public $timestamps = false;

	protected $casts = [
		'fk_pnraereo_id' => 'int'
	];

	protected $fillable = [
		'fk_pnraereo_id',
		'pnrremark_tipo',
		'pnrremark_valor'
	];

	public function pnraereo()
	{
		return $this->belongsTo(Pnraereo::class, 'fk_pnraereo_id');
	}
}
