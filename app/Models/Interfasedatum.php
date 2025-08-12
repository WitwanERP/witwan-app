<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Interfasedatum
 * 
 * @property int $interfasedata_id
 * @property int $fk_interfases_id
 * @property string $interfasedata_tipo
 * @property string $interfasedata_item
 * @property string $interfasedata_valor
 * 
 * @property Interfase $interfase
 *
 * @package App\Models
 */
class Interfasedatum extends Model
{
	protected $table = 'interfasedata';
	protected $primaryKey = 'interfasedata_id';
	public $timestamps = false;

	protected $casts = [
		'fk_interfases_id' => 'int'
	];

	protected $fillable = [
		'fk_interfases_id',
		'interfasedata_tipo',
		'interfasedata_item',
		'interfasedata_valor'
	];

	public function interfase()
	{
		return $this->belongsTo(Interfase::class, 'fk_interfases_id');
	}
}
