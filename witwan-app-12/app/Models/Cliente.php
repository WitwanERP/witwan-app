<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cliente
 * 
 * @property int $cliente_id
 * @property string $cliente_nombre
 * @property string $cliente_razonsocial
 * @property string $cliente_legajo
 * @property string $fk_idioma_id
 * @property int $fk_tarifario1_id
 * @property int $fk_tarifario2_id
 * @property int $fk_tarifario3_id
 * @property float $limite_credito
 * @property int $credito_habilitado
 * @property float $credito_utilizado
 * @property string $consolidador
 * @property int $clienteminorista
 * @property int $facturacion_periodo
 * @property int $fk_tipofactura_id
 * @property int $fk_condicioniva_id
 * @property string $cliente_telefono
 * @property string $cliente_fax
 * @property string $cliente_direccionfiscal
 * @property string $cliente_email
 * @property string $cliente_email2
 * @property string $cliente_emailadmin
 * @property string $cliente_ciudad
 * @property string $cliente_provincia
 * @property int $fk_pais_id
 * @property int $fk_ciudad_id
 * @property string $cliente_codigopostal
 * @property string $cuit
 * @property int $fk_tipoclavefiscal_id
 * @property string $nro_clavefiscal
 * @property string $iata
 * @property string $cliente_logo
 * @property string $usar_logo
 * @property float $gastos_porcentaje_1
 * @property float $gastos_porcentaje_2
 * @property float $gastos_porcentaje_3
 * @property float $gastos_fijo_1
 * @property float $gastos_fijo_2
 * @property float $gastos_fijo_3
 * @property string $gastos_fijo_moneda
 * @property string $fk_moneda_id
 * @property float $gastos_iva
 * @property string $habilita
 * @property string $comentarios
 * @property Carbon $fechacarga
 * @property Carbon $um
 * @property int $fk_usuario_id
 * @property int $fk_usuario_promotor1
 * @property int $fk_usuario_promotor2
 * @property int $fk_usuario_promotor3
 * @property int $fk_usuario_promotor4
 * @property int $fk_usuario_vendedor
 * @property string $freelance
 * @property string $autorizaws
 * @property string|null $nombre_representante
 * @property string $representante_geografico
 * @property string|null $cuit_internacional
 * @property int $cliente_promo
 * @property int $cliente_web
 * @property bool $cliente_pasajerodirecto
 * @property int $fk_cadenacliente_id
 * @property int $plazo_pago
 * @property int $idnemo
 * @property int $idtravelc
 * @property int $tipofacturacion
 * @property int $licencia_id
 * @property string $tipo_fce
 * @property int $factura_automatica
 * 
 * @property Ciudad $ciudad
 * @property Condicioniva $condicioniva
 * @property Idioma $idioma
 * @property Moneda $moneda
 * @property Pai $pai
 * @property Usuario $usuario
 * @property Collection|Aerolinea[] $aerolineas
 * @property Collection|Creditoextra[] $creditoextras
 * @property Collection|Cupo[] $cupos
 * @property Collection|Factura[] $facturas
 * @property Collection|Modelocomision[] $modelocomisions
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notacredito[] $notacreditos
 * @property Collection|Notadebito[] $notadebitos
 * @property Collection|Pasajero[] $pasajeros
 * @property Collection|Proveedor[] $proveedors
 * @property Collection|Recibo[] $recibos
 * @property Collection|Sistema[] $sistemas
 * @property Collection|Tag[] $tags
 * @property Collection|Reserva[] $reservas
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Cliente extends Model
{
	protected $table = 'cliente';
	protected $primaryKey = 'cliente_id';
	public $timestamps = false;

	protected $casts = [
		'fk_tarifario1_id' => 'int',
		'fk_tarifario2_id' => 'int',
		'fk_tarifario3_id' => 'int',
		'limite_credito' => 'float',
		'credito_habilitado' => 'int',
		'credito_utilizado' => 'float',
		'clienteminorista' => 'int',
		'facturacion_periodo' => 'int',
		'fk_tipofactura_id' => 'int',
		'fk_condicioniva_id' => 'int',
		'fk_pais_id' => 'int',
		'fk_ciudad_id' => 'int',
		'fk_tipoclavefiscal_id' => 'int',
		'gastos_porcentaje_1' => 'float',
		'gastos_porcentaje_2' => 'float',
		'gastos_porcentaje_3' => 'float',
		'gastos_fijo_1' => 'float',
		'gastos_fijo_2' => 'float',
		'gastos_fijo_3' => 'float',
		'gastos_iva' => 'float',
		'fechacarga' => 'datetime',
		'um' => 'datetime',
		'fk_usuario_id' => 'int',
		'fk_usuario_promotor1' => 'int',
		'fk_usuario_promotor2' => 'int',
		'fk_usuario_promotor3' => 'int',
		'fk_usuario_promotor4' => 'int',
		'fk_usuario_vendedor' => 'int',
		'cliente_promo' => 'int',
		'cliente_web' => 'int',
		'cliente_pasajerodirecto' => 'bool',
		'fk_cadenacliente_id' => 'int',
		'plazo_pago' => 'int',
		'idnemo' => 'int',
		'idtravelc' => 'int',
		'tipofacturacion' => 'int',
		'licencia_id' => 'int',
		'factura_automatica' => 'int'
	];

	protected $fillable = [
		'cliente_nombre',
		'cliente_razonsocial',
		'cliente_legajo',
		'fk_idioma_id',
		'fk_tarifario1_id',
		'fk_tarifario2_id',
		'fk_tarifario3_id',
		'limite_credito',
		'credito_habilitado',
		'credito_utilizado',
		'consolidador',
		'clienteminorista',
		'facturacion_periodo',
		'fk_tipofactura_id',
		'fk_condicioniva_id',
		'cliente_telefono',
		'cliente_fax',
		'cliente_direccionfiscal',
		'cliente_email',
		'cliente_email2',
		'cliente_emailadmin',
		'cliente_ciudad',
		'cliente_provincia',
		'fk_pais_id',
		'fk_ciudad_id',
		'cliente_codigopostal',
		'cuit',
		'fk_tipoclavefiscal_id',
		'nro_clavefiscal',
		'iata',
		'cliente_logo',
		'usar_logo',
		'gastos_porcentaje_1',
		'gastos_porcentaje_2',
		'gastos_porcentaje_3',
		'gastos_fijo_1',
		'gastos_fijo_2',
		'gastos_fijo_3',
		'gastos_fijo_moneda',
		'fk_moneda_id',
		'gastos_iva',
		'habilita',
		'comentarios',
		'fechacarga',
		'um',
		'fk_usuario_id',
		'fk_usuario_promotor1',
		'fk_usuario_promotor2',
		'fk_usuario_promotor3',
		'fk_usuario_promotor4',
		'fk_usuario_vendedor',
		'freelance',
		'autorizaws',
		'nombre_representante',
		'representante_geografico',
		'cuit_internacional',
		'cliente_promo',
		'cliente_web',
		'cliente_pasajerodirecto',
		'fk_cadenacliente_id',
		'plazo_pago',
		'idnemo',
		'idtravelc',
		'tipofacturacion',
		'licencia_id',
		'tipo_fce',
		'factura_automatica'
	];

	public function ciudad()
	{
		return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
	}

	public function condicioniva()
	{
		return $this->belongsTo(Condicioniva::class, 'fk_condicioniva_id');
	}

	public function idioma()
	{
		return $this->belongsTo(Idioma::class, 'fk_idioma_id');
	}

	public function moneda()
	{
		return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
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
		return $this->hasMany(Aerolinea::class, 'fk_cliente_id');
	}

	public function creditoextras()
	{
		return $this->hasMany(Creditoextra::class, 'fk_cliente_id');
	}

	public function cupos()
	{
		return $this->hasMany(Cupo::class, 'fk_cliente_id');
	}

	public function facturas()
	{
		return $this->hasMany(Factura::class, 'fk_cliente_id');
	}

	public function modelocomisions()
	{
		return $this->hasMany(Modelocomision::class, 'fk_cliente_id');
	}

	public function modelofees()
	{
		return $this->hasMany(Modelofee::class, 'fk_cliente_id');
	}

	public function movimientos()
	{
		return $this->hasMany(Movimiento::class, 'fk_cliente_id');
	}

	public function notacreditos()
	{
		return $this->hasMany(Notacredito::class, 'fk_cliente_id');
	}

	public function notadebitos()
	{
		return $this->hasMany(Notadebito::class, 'fk_cliente_id');
	}

	public function pasajeros()
	{
		return $this->hasMany(Pasajero::class, 'fk_cliente_id');
	}

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'fk_cliente_id');
	}

	public function recibos()
	{
		return $this->hasMany(Recibo::class, 'fk_cliente_id');
	}

	public function sistemas()
	{
		return $this->belongsToMany(Sistema::class, 'rel_clientesistema', 'fk_cliente_id', 'fk_sistema_id')
					->withPivot('fk_tarifario_id');
	}

	public function tags()
	{
		return $this->belongsToMany(Tag::class, 'rel_clientetag', 'fk_cliente_id', 'fk_tag_id');
	}

	public function reservas()
	{
		return $this->hasMany(Reserva::class, 'facturar_a');
	}

	public function usuarios()
	{
		return $this->hasMany(Usuario::class, 'fk_cliente_id');
	}
}
