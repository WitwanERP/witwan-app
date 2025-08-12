<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Pkdgalerium
 * 
 * @property int $pkdgaleria_id
 * @property int $fk_pkd_id
 * @property string $pkdgaleria_archivo
 * @property int $orden
 * 
 * @property Pkd $pkd
 *
 * @package App\Models
 */
class Pkdgalerium extends Model
{
	protected $table = 'pkdgaleria';
	protected $primaryKey = 'pkdgaleria_id';
	public $timestamps = false;

	protected $casts = [
		'fk_pkd_id' => 'int',
		'orden' => 'int'
	];

	protected $fillable = [
		'fk_pkd_id',
		'pkdgaleria_archivo',
		'orden'
	];

	public function pkd()
	{
		return $this->belongsTo(Pkd::class, 'fk_pkd_id');
	}
}
