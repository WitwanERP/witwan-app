<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Formapago
 * 
 * @property int $formapago_id
 * @property string $formapago_nombre
 * @property int $fk_plancuenta_id
 * 
 * @property Plancuentum $plancuentum
 *
 * @package App\Models
 */
class Formapago extends Model
{
	protected $table = 'formapago';
	protected $primaryKey = 'formapago_id';
	public $timestamps = false;

	protected $casts = [
		'fk_plancuenta_id' => 'int'
	];

	protected $fillable = [
		'formapago_nombre',
		'fk_plancuenta_id'
	];

	public function plancuentum()
	{
		return $this->belongsTo(Plancuentum::class, 'fk_plancuenta_id');
	}
}
