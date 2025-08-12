<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Facturacliente
 * 
 * @property int|null $ID_Negocio
 * @property int|null $ID_Interno
 * @property int|null $ID_Servicio
 * @property string|null $Nombre_Servicio
 * @property int|null $ID_Cliente
 * @property string|null $Nombre_Cliente
 * @property int|null $ID_Vendedor
 * @property string|null $Nombre_Vendedor
 * @property string|null $ID_Moneda
 * @property int|null $Tipo_Cambio
 * @property int|null $FacturaCliente
 * @property string|null $FechaFacturaCliente
 * @property int|null $FacturaOperador
 * @property int|null $N_Linea
 * @property string|null $FechaEmisionTKT
 * @property string|null $Linea_Pendiente
 * @property int|null $MontoExento
 * @property int|null $MontoNeto
 * @property int|null $MontoIva
 * @property int|null $IvaCalculado
 * @property int|null $MontoTotal
 *
 * @package App\Models
 */
class Facturacliente extends Model
{
	protected $table = 'facturacliente';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'ID_Negocio' => 'int',
		'ID_Interno' => 'int',
		'ID_Servicio' => 'int',
		'ID_Cliente' => 'int',
		'ID_Vendedor' => 'int',
		'Tipo_Cambio' => 'int',
		'FacturaCliente' => 'int',
		'FacturaOperador' => 'int',
		'N_Linea' => 'int',
		'MontoExento' => 'int',
		'MontoNeto' => 'int',
		'MontoIva' => 'int',
		'IvaCalculado' => 'int',
		'MontoTotal' => 'int'
	];

	protected $fillable = [
		'ID_Negocio',
		'ID_Interno',
		'ID_Servicio',
		'Nombre_Servicio',
		'ID_Cliente',
		'Nombre_Cliente',
		'ID_Vendedor',
		'Nombre_Vendedor',
		'ID_Moneda',
		'Tipo_Cambio',
		'FacturaCliente',
		'FechaFacturaCliente',
		'FacturaOperador',
		'N_Linea',
		'FechaEmisionTKT',
		'Linea_Pendiente',
		'MontoExento',
		'MontoNeto',
		'MontoIva',
		'IvaCalculado',
		'MontoTotal'
	];
}
