<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelEerr
 * 
 * @property string|null $numero_cuenta
 * @property string|null $nombre_cuenta
 * @property string|null $familia_cuenta
 * @property string|null $clasificacion_interna
 * @property string|null $subcuenta
 * @property string|null $item
 * @property string|null $familia_cuenta_nop
 *
 * @package App\Models
 */
class RelEerr extends Model
{
	protected $table = 'rel_eerr';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'numero_cuenta',
		'nombre_cuenta',
		'familia_cuenta',
		'clasificacion_interna',
		'subcuenta',
		'item',
		'familia_cuenta_nop'
	];
}
