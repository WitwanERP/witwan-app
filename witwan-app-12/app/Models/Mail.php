<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Mail
 * 
 * @property int $mails_id
 * @property int $fk_usuario_id
 * @property string $ip
 * @property Carbon|null $fechahora_alta
 * @property Carbon|null $fechahora_envio
 * @property string $carpeta
 * @property string $de
 * @property string $para
 * @property string $cc
 * @property string $cco
 * @property string $asunto
 * @property string $mensaje
 * @property string $error
 *
 * @package App\Models
 */
class Mail extends Model
{
	protected $table = 'mails';
	protected $primaryKey = 'mails_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fechahora_alta' => 'datetime',
		'fechahora_envio' => 'datetime'
	];

	protected $fillable = [
		'fk_usuario_id',
		'ip',
		'fechahora_alta',
		'fechahora_envio',
		'carpeta',
		'de',
		'para',
		'cc',
		'cco',
		'asunto',
		'mensaje',
		'error'
	];
}
