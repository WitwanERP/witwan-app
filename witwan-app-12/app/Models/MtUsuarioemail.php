<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class MtUsuarioemail
 * 
 * @property int $mt_usuarioemail_id
 * @property int $fk_usuario_id
 * @property int $fk_secundario_id
 *
 * @package App\Models
 */
class MtUsuarioemail extends Model
{
	protected $table = 'mt_usuarioemail';
	protected $primaryKey = 'mt_usuarioemail_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_secundario_id' => 'int'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_secundario_id'
	];
}
