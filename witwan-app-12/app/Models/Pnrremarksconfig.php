<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Pnrremarksconfig
 * 
 * @property int $pnrremarksconfig_id
 * @property string $pnrremarksconfig_texto
 * @property string $pnrremarksconfig_campo
 * @property string $pnrremarksconfig_tipo
 *
 * @package App\Models
 */
class Pnrremarksconfig extends Model
{
	protected $table = 'pnrremarksconfig';
	protected $primaryKey = 'pnrremarksconfig_id';
	public $timestamps = false;

	protected $fillable = [
		'pnrremarksconfig_texto',
		'pnrremarksconfig_campo',
		'pnrremarksconfig_tipo'
	];
}
