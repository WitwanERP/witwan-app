<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Facturaboletum
 * 
 * @property int $factura_id
 * @property int $ok
 *
 * @package App\Models
 */
class Facturaboletum extends Model
{
	protected $table = 'facturaboleta';
	protected $primaryKey = 'factura_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'factura_id' => 'int',
		'ok' => 'int'
	];

	protected $fillable = [
		'ok'
	];
}
