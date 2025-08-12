<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Reportebsp
 * 
 * @property int $reportebsp_id
 * @property string $reportebsp_nombre
 * @property Carbon $reportebsp_fecha
 * @property string $reportebsp_archivo
 * @property int $procesado
 * @property int $totaldocumentos
 * @property int $validos
 * @property int $erroneos
 * @property int $enfase
 * @property int $cnj
 * @property Carbon $periodo_in
 * @property Carbon $periodo_out
 * @property float $apagar
 * @property float $apagarusd
 * @property float $costo
 * @property float $costousd
 * @property float $ivas
 * @property Carbon $fechaproceso
 * @property string $archivocsv
 * 
 * @property Collection|Reportebsptkt[] $reportebsptkts
 *
 * @package App\Models
 */
class Reportebsp extends Model
{
	protected $table = 'reportebsp';
	protected $primaryKey = 'reportebsp_id';
	public $timestamps = false;

	protected $casts = [
		'reportebsp_fecha' => 'datetime',
		'procesado' => 'int',
		'totaldocumentos' => 'int',
		'validos' => 'int',
		'erroneos' => 'int',
		'enfase' => 'int',
		'cnj' => 'int',
		'periodo_in' => 'datetime',
		'periodo_out' => 'datetime',
		'apagar' => 'float',
		'apagarusd' => 'float',
		'costo' => 'float',
		'costousd' => 'float',
		'ivas' => 'float',
		'fechaproceso' => 'datetime'
	];

	protected $fillable = [
		'reportebsp_nombre',
		'reportebsp_fecha',
		'reportebsp_archivo',
		'procesado',
		'totaldocumentos',
		'validos',
		'erroneos',
		'enfase',
		'cnj',
		'periodo_in',
		'periodo_out',
		'apagar',
		'apagarusd',
		'costo',
		'costousd',
		'ivas',
		'fechaproceso',
		'archivocsv'
	];

	public function reportebsptkts()
	{
		return $this->hasMany(Reportebsptkt::class, 'fk_reportebsp_id');
	}
}
