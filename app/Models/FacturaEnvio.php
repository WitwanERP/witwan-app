<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FacturaEnvio
 * 
 * @property int $factura_envio_id
 * @property int $fk_usuario_id
 * @property int $fk_factura_id
 * @property Carbon $fecha
 * 
 * @property Factura $factura
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class FacturaEnvio extends Model
{
	protected $table = 'factura_envio';
	protected $primaryKey = 'factura_envio_id';
	public $timestamps = false;

	protected $casts = [
		'fk_usuario_id' => 'int',
		'fk_factura_id' => 'int',
		'fecha' => 'datetime'
	];

	protected $fillable = [
		'fk_usuario_id',
		'fk_factura_id',
		'fecha'
	];

	public function factura()
	{
		return $this->belongsTo(Factura::class, 'fk_factura_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}
}
