<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Colaevento
 * 
 * @property int $colaevento_id
 * @property Carbon $regdate
 * @property string $tipo_evento
 * @property string $estado
 * @property string $modelo
 * @property int $id_relacionado
 * @property string $datos
 *
 * @package App\Models
 */
class Colaevento extends Model
{
	protected $table = 'colaevento';
	protected $primaryKey = 'colaevento_id';
	public $timestamps = false;

	protected $casts = [
		'regdate' => 'datetime',
		'id_relacionado' => 'int'
	];

	protected $fillable = [
		'regdate',
		'tipo_evento',
		'estado',
		'modelo',
		'id_relacionado',
		'datos'
	];
}
