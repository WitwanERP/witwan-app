<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Sysuser
 * 
 * @property int $sysuser_id
 * @property int $fk_cliente_id
 * @property string $fk_tipousuario_id
 * @property string $sysuser_name
 * @property string $sysuser_lastname
 * @property string $sysuser_nickname
 * @property string $sysuser_email
 * @property string $sysuser_password
 * @property string $sysuser_key
 * @property int $fk_sysrole_id
 * @property string $sysuser_lang
 *
 * @package App\Models
 */
class Sysuser extends Model
{
	protected $table = 'sysuser';
	protected $primaryKey = 'sysuser_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cliente_id' => 'int',
		'fk_sysrole_id' => 'int'
	];

	protected $hidden = [
		'sysuser_password'
	];

	protected $fillable = [
		'fk_cliente_id',
		'fk_tipousuario_id',
		'sysuser_name',
		'sysuser_lastname',
		'sysuser_nickname',
		'sysuser_email',
		'sysuser_password',
		'sysuser_key',
		'fk_sysrole_id',
		'sysuser_lang'
	];
}
