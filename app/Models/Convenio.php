<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Convenio
 * 
 * @property int $convenio_id
 * @property string $convenio_nombre
 * @property int $fk_proveedor_id
 * @property string $convenio_archivo
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * 
 * @property Proveedor $proveedor
 *
 * @package App\Models
 */
class Convenio extends Model
{
	protected $table = 'convenio';
	protected $primaryKey = 'convenio_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'vigencia_ini' => 'datetime',
		'vigencia_fin' => 'datetime'
	];

	protected $fillable = [
		'convenio_nombre',
		'fk_proveedor_id',
		'convenio_archivo',
		'vigencia_ini',
		'vigencia_fin'
	];

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}
}
