<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Usuario
 * 
 * @property int $usuario_id
 * @property string $fk_tipousuario_id
 * @property string $usuario_nombre
 * @property string $usuario_apellido
 * @property string $usuario_mail
 * @property int $fk_proveedor_id
 * @property int $fk_cliente_id
 * @property int $fk_cadenacliente_id
 * @property string $usuario_login
 * @property string $usuario_clave
 * @property string $usuario_password
 * @property string $usuario_key
 * @property string $fk_idioma_id
 * @property string $usuario_telefono
 * @property string $usuario_celular
 * @property string $usuario_fax
 * @property string $usuario_domicilio
 * @property string $usuario_sexo
 * @property Carbon $nacimiento
 * @property string $ciudad
 * @property string $notas
 * @property string $habilitar
 * @property string $solocotiza
 * @property int $agente
 * @property string $eliminar
 * @property string $usuario_interno
 * @property int $fk_operador_id
 * @property int $usuario_promo
 * @property int $usuario_responsable
 * @property string $firma_amadeus
 * @property string $firma_sabre
 * @property int $fk_modelocomision_id
 * @property string $usuario_apikey
 * @property string $password
 * 
 * @property Cadenacliente $cadenacliente
 * @property Cliente $cliente
 * @property Idioma $idioma
 * @property Proveedor $proveedor
 * @property Tipousuario $tipousuario
 * @property Collection|Canje[] $canjes
 * @property Collection|Cierrearqueo[] $cierrearqueos
 * @property Collection|Cierrecaja[] $cierrecajas
 * @property Collection|Cliente[] $clientes
 * @property Collection|Creditoextra[] $creditoextras
 * @property Collection|Cupo[] $cupos
 * @property Collection|Factura[] $facturas
 * @property Collection|FacturaEnvio[] $factura_envios
 * @property Collection|Facturaproveedor[] $facturaproveedors
 * @property Collection|Filearchivo[] $filearchivos
 * @property Collection|Filecomentario[] $filecomentarios
 * @property Collection|Historialfile[] $historialfiles
 * @property Collection|Modelocomision[] $modelocomisions
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notacredito[] $notacreditos
 * @property Collection|Notadebito[] $notadebitos
 * @property Collection|Ordenadmin[] $ordenadmins
 * @property Collection|Permiso[] $permisos
 * @property Collection|Producto[] $productos
 * @property Collection|Proveedor[] $proveedors
 * @property Collection|Recibo[] $recibos
 * @property RelOrdenadminocupacion|null $rel_ordenadminocupacion
 * @property Collection|Usuario[] $usuarios
 * @property Collection|Reserva[] $reservas
 * @property Collection|Soldout[] $soldouts
 * @property Collection|Usuariocomision[] $usuariocomisions
 *
 * @package App\Models
 */
class Usuario extends Model
{
	protected $table = 'usuario';
	protected $primaryKey = 'usuario_id';
	public $timestamps = false;

	protected $casts = [
		'fk_proveedor_id' => 'int',
		'fk_cliente_id' => 'int',
		'fk_cadenacliente_id' => 'int',
		'nacimiento' => 'datetime',
		'agente' => 'int',
		'fk_operador_id' => 'int',
		'usuario_promo' => 'int',
		'usuario_responsable' => 'int',
		'fk_modelocomision_id' => 'int'
	];

	protected $hidden = [
		'usuario_password',
		'password'
	];

	protected $fillable = [
		'fk_tipousuario_id',
		'usuario_nombre',
		'usuario_apellido',
		'usuario_mail',
		'fk_proveedor_id',
		'fk_cliente_id',
		'fk_cadenacliente_id',
		'usuario_login',
		'usuario_clave',
		'usuario_password',
		'usuario_key',
		'fk_idioma_id',
		'usuario_telefono',
		'usuario_celular',
		'usuario_fax',
		'usuario_domicilio',
		'usuario_sexo',
		'nacimiento',
		'ciudad',
		'notas',
		'habilitar',
		'solocotiza',
		'agente',
		'eliminar',
		'usuario_interno',
		'fk_operador_id',
		'usuario_promo',
		'usuario_responsable',
		'firma_amadeus',
		'firma_sabre',
		'fk_modelocomision_id',
		'usuario_apikey',
		'password'
	];

	public function cadenacliente()
	{
		return $this->belongsTo(Cadenacliente::class, 'fk_cadenacliente_id');
	}

	public function cliente()
	{
		return $this->belongsTo(Cliente::class, 'fk_cliente_id');
	}

	public function idioma()
	{
		return $this->belongsTo(Idioma::class, 'fk_idioma_id');
	}

	public function proveedor()
	{
		return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
	}

	public function tipousuario()
	{
		return $this->belongsTo(Tipousuario::class, 'fk_tipousuario_id');
	}

	public function canjes()
	{
		return $this->hasMany(Canje::class, 'fk_usuario_id');
	}

	public function cierrearqueos()
	{
		return $this->hasMany(Cierrearqueo::class, 'fk_usuario_id');
	}

	public function cierrecajas()
	{
		return $this->hasMany(Cierrecaja::class, 'fk_usuario_id');
	}

	public function clientes()
	{
		return $this->hasMany(Cliente::class, 'fk_usuario_id');
	}

	public function creditoextras()
	{
		return $this->hasMany(Creditoextra::class, 'fk_usuario_id');
	}

	public function cupos()
	{
		return $this->hasMany(Cupo::class, 'fk_usuario_id');
	}

	public function facturas()
	{
		return $this->hasMany(Factura::class, 'fk_usuario_id');
	}

	public function factura_envios()
	{
		return $this->hasMany(FacturaEnvio::class, 'fk_usuario_id');
	}

	public function facturaproveedors()
	{
		return $this->hasMany(Facturaproveedor::class, 'fk_usuario_id');
	}

	public function filearchivos()
	{
		return $this->hasMany(Filearchivo::class, 'fk_usuario_id');
	}

	public function filecomentarios()
	{
		return $this->hasMany(Filecomentario::class, 'fk_usuario_id');
	}

	public function historialfiles()
	{
		return $this->hasMany(Historialfile::class, 'fk_usuario_id');
	}

	public function modelocomisions()
	{
		return $this->belongsToMany(Modelocomision::class, 'rel_usuariomodelocomision', 'fk_usuario_id', 'fk_modelocomision_id')
					->withPivot('rel_usuariomodelocomision_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_usuario_id');
	}

	public function notacreditos()
	{
		return $this->hasMany(Notacredito::class, 'fk_usuario_id');
	}

	public function notadebitos()
	{
		return $this->hasMany(Notadebito::class, 'fk_usuario_id');
	}

	public function ordenadmins()
	{
		return $this->hasMany(Ordenadmin::class, 'fk_usuario_id');
	}

	public function permisos()
	{
		return $this->hasMany(Permiso::class, 'fk_usuario_id');
	}

	public function productos()
	{
		return $this->hasMany(Producto::class, 'fk_usuario_id');
	}

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'fk_usuario_id');
	}

	public function recibos()
	{
		return $this->hasMany(Recibo::class, 'fk_usuario_id');
	}

	public function rel_ordenadminocupacion()
	{
		return $this->hasOne(RelOrdenadminocupacion::class, 'fk_usuario_id');
	}

	public function usuarios()
	{
		return $this->belongsToMany(Usuario::class, 'rel_usuariousuario', 'fk_secundario_id', 'fk_usuario_id')
					->withPivot('usuariousuario_id', 'tiporelacion');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'agente');
	}

	public function soldouts()
	{
		return $this->hasMany(Soldout::class, 'fk_usuario_id');
	}

	public function usuariocomisions()
	{
		return $this->hasMany(Usuariocomision::class, 'fk_usuario_id');
	}
}
