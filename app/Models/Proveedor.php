<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Proveedor
 * 
 * @property int $proveedor_id
 * @property int $fk_cadenahotelera_id
 * @property string $oldid
 * @property string $crol
 * @property string $proveedor_nombre
 * @property string $razonsocial
 * @property int $fk_condicioniva_id
 * @property string $proveedor_legajo
 * @property string $proveedor_telefono
 * @property string $proveedor_telefonoemergencia
 * @property string $proveedor_fax
 * @property string $proveedor_direccion
 * @property string $proveedor_email
 * @property string $proveedor_emailreservas
 * @property string $proveedor_ciudad
 * @property string $proveedor_provincia
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property int $identidad
 * @property string $cuit
 * @property string $iata
 * @property string $edita_tarifa
 * @property string $comentario
 * @property string $habilita
 * @property int $fk_usuario_id
 * @property Carbon $fechacarga
 * @property Carbon $um
 * @property string $proveedor_codigopostal
 * @property string $proveedor_infobanco
 * @property string $eliminar
 * @property string $proveedorminorista
 * @property string $moneda_extra
 * @property string $tipo_extra
 * @property float $costo_extra
 * @property int $prestador
 * @property int $gastoacliente
 * @property int $voucherpropio
 * @property float $porcentaje_extra
 * @property int $enviadocumentos
 * @property int $cartaemision
 * @property int $vencimiento_dias
 * @property string $vencimiento_tipo
 * @property int $fk_cliente_id
 * @property string $proveedor_logo
 * @property string $proveedor_imagen
 * @property int $proveedor_oc
 * @property string $codigo_travelc
 * @property string $codigo_roombeast
 * 
 * @property Cadenahotelera $cadenahotelera
 * @property Ciudad $ciudad
 * @property Cliente $cliente
 * @property Condicioniva $condicioniva
 * @property Pai $pai
 * @property Usuario $usuario
 * @property Collection|Aerolinea[] $aerolineas
 * @property Collection|Canje[] $canjes
 * @property Collection|Convenio[] $convenios
 * @property Collection|Facturaproveedor[] $facturaproveedors
 * @property Collection|Interfase[] $interfases
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Ordenadmin[] $ordenadmins
 * @property Collection|Pnraereo[] $pnraereos
 * @property Collection|Producto[] $productos
 * @property Collection|Servicio[] $servicios
 * @property Collection|Submodulo[] $submodulos
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Proveedor extends Model
{
	protected $table = 'proveedor';
	protected $primaryKey = 'proveedor_id';
	public $timestamps = false;

	protected $casts = [
		'fk_cadenahotelera_id' => 'int',
		'fk_condicioniva_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'identidad' => 'int',
		'fk_usuario_id' => 'int',
		'fechacarga' => 'datetime',
		'um' => 'datetime',
		'costo_extra' => 'float',
		'prestador' => 'int',
		'gastoacliente' => 'int',
		'voucherpropio' => 'int',
		'porcentaje_extra' => 'float',
		'enviadocumentos' => 'int',
		'cartaemision' => 'int',
		'vencimiento_dias' => 'int',
		'fk_cliente_id' => 'int',
		'proveedor_oc' => 'int'
	];

	protected $fillable = [
		'fk_cadenahotelera_id',
		'oldid',
		'crol',
		'proveedor_nombre',
		'razonsocial',
		'fk_condicioniva_id',
		'proveedor_legajo',
		'proveedor_telefono',
		'proveedor_telefonoemergencia',
		'proveedor_fax',
		'proveedor_direccion',
		'proveedor_email',
		'proveedor_emailreservas',
		'proveedor_ciudad',
		'proveedor_provincia',
		'fk_pais_id',
		'fk_ciudad_id',
		'identidad',
		'cuit',
		'iata',
		'edita_tarifa',
		'comentario',
		'habilita',
		'fk_usuario_id',
		'fechacarga',
		'um',
		'proveedor_codigopostal',
		'proveedor_infobanco',
		'eliminar',
		'proveedorminorista',
		'moneda_extra',
		'tipo_extra',
		'costo_extra',
		'prestador',
		'gastoacliente',
		'voucherpropio',
		'porcentaje_extra',
		'enviadocumentos',
		'cartaemision',
		'vencimiento_dias',
		'vencimiento_tipo',
		'fk_cliente_id',
		'proveedor_logo',
		'proveedor_imagen',
		'proveedor_oc',
		'codigo_travelc',
		'codigo_roombeast'
	];

	public function cadenahotelera()
	{
		return $this->belongsTo(Cadenahotelera::class, 'fk_cadenahotelera_id');
	}

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function condicioniva()
	{
		return $this->belongsTo(Condicioniva::class, 'fk_condicioniva_id');
	}

	public function pai()
	{
		return $this->belongsTo(Pai::class, 'fk_pais_id');
	}

	public function usuario()
	{
		return $this->belongsTo(Usuario::class, 'fk_usuario_id');
	}

	public function aerolineas()
	{
		return $this->hasMany(Aerolinea::class, 'fk_proveedor_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_proveedor_id');
	}

	public function convenios()
	{
		return $this->hasMany(Convenio::class, 'fk_proveedor_id');
	}

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_proveedor_id');
	}

	public function interfases()
	{
		return $this->hasMany(Interfase::class, 'fk_proveedor_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_proveedor_id');
	}

	public function ordenadmins()
	{
		return $this->hasMany(Ordenadmin::class, 'fk_proveedor_id');
	}

	public function pnraereos()
	{
		return $this->hasMany(Pnraereo::class, 'fk_proveedor_id');
	}

	public function productos()
	{
		return $this->hasMany(Producto::class, 'fk_prestador_id');
	}

	public function servicios()
	{
		return $this->hasMany(Servicio::class, 'fk_prestador_id');
	}

	public function submodulos()
	{
		return $this->hasMany(Submodulo::class, 'fk_proveedor_id');
	}

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'fk_proveedor_id');
	}
}
