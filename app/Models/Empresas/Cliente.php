<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Productos\Aerolinea;
use App\Models\Empresas\Proveedor;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Ciudad;

class Cliente extends Model
{
    use HasFactory;
    protected $table = 'cliente';
    protected $primaryKey = 'cliente_id';
    public $timestamps = false;
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
        'factura_automatica',
    ];
    public function setClienteLegajoAttribute($value){
        $this->attributes['cliente_legajo'] = $value ?? '';
    }
    public function pais(){
        return $this->belongsTo(Pais::class, 'fk_pais_id', 'pais_id');
    }
    public function ciudad(){
        return $this->belongsTo(Ciudad::class, 'fk_ciudad_id', 'ciudad_id');
    }
    public function aerolineas(){
        return $this->hasMany(Aerolinea::class, 'fk_cliente_id', 'cliente_id');
    }
    public function proveedores(){
        return $this->hasMany(Proveedor::class, 'fk_cliente_id', 'cliente_id');
    }
  public function tarifario1(){
        return $this->belongsTo(Tarifario::class, 'fk_tarifario1_id', 'tarifario_id');
    }
    public function tarifario2(){
        return $this->belongsTo(Tarifario::class, 'fk_tarifario2_id', 'tarifario_id');
    }
    public function tarifario3(){
        return $this->belongsTo(Tarifario::class, 'fk_tarifario3_id', 'tarifario_id');
    }
    public function idioma(){
        return $this->belongsTo(Idioma::class, 'fk_idioma_id', 'idioma_id');
    }

    public function tipofactura(){
        return $this->belongsTo(TipoFactura::class, 'fk_tipofactura_id', 'tipofactura_id');
    }
    public function condicioniva(){
        return $this->belongsTo(CondicionIva::class, 'fk_condicioniva_id', 'condicioniva_id');
    }
    public function tipoclavefiscal(){
        return $this->belongsTo(TipoClaveFiscal::class, 'fk_tipoclavefiscal_id', 'tipoclavefiscal_id');
    }
    public function usuario(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_id', 'usuario_id');
    }
    public function usuario_promotor1(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_promotor1', 'usuario_id');
    }
    public function usuario_promotor2(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_promotor2', 'usuario_id');
    }
    public function usuario_promotor3(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_promotor3', 'usuario_id');
    }
    public function usuario_promotor4(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_promotor4', 'usuario_id');
    }
    public function usuario_vendedor(){
        return $this->belongsTo(Usuario::class, 'fk_usuario_vendedor', 'usuario_id');
    }
    public function cadenacliente(){
        return $this->belongsTo(CadenaCliente::class, 'fk_cadenacliente_id', 'cadenacliente_id');
    }  /**/
}
/*cliente_id	int(10) Incremento automático
cliente_nombre	varchar(150)
cliente_razonsocial	varchar(200)
cliente_legajo	varchar(50)
fk_idioma_id	varchar(2)
fk_tarifario1_id	int(10)	minorista
fk_tarifario2_id	int(10)	mayorista
fk_tarifario3_id	int(10)	receptivo
limite_credito	decimal(12,2)
credito_habilitado	int(1) [1]
credito_utilizado	decimal(15,2)
consolidador	varchar(1) [N]
clienteminorista	int(1) [0]
facturacion_periodo	int(5)
fk_tipofactura_id	int(10)
fk_condicioniva_id	int(11)
cliente_telefono	varchar(50)
cliente_fax	varchar(50)
cliente_direccionfiscal	varchar(255)
cliente_email	varchar(150)
cliente_email2	varchar(150)
cliente_emailadmin	varchar(150)
cliente_ciudad	varchar(150)
cliente_provincia	varchar(150)
fk_pais_id	int(10)
fk_ciudad_id	int(10)
cliente_codigopostal	varchar(10)
cuit	varchar(20)
fk_tipoclavefiscal_id	int(10)
nro_clavefiscal	varchar(150)
iata	varchar(20)
cliente_logo	varchar(150)
usar_logo	varchar(1) [N]
gastos_porcentaje_1	decimal(4,2)
gastos_porcentaje_2	decimal(4,2)
gastos_porcentaje_3	decimal(4,2)
gastos_fijo_1	decimal(10,2)
gastos_fijo_2	decimal(10,2)
gastos_fijo_3	decimal(10,2)
gastos_fijo_moneda	varchar(3)
fk_moneda_id	varchar(3)
gastos_iva	decimal(10,2)
habilita	varchar(1) [Y]
comentarios	text
fechacarga	timestamp [current_timestamp()]
um	timestamp [0000-00-00 00:00:00]
fk_usuario_id	int(10)
fk_usuario_promotor1	text
fk_usuario_promotor2	int(11)	Levanta de Tabla Usuarios
fk_usuario_promotor3	int(11)	Levanta de Tabla Usuarios
fk_usuario_promotor4	int(10)
fk_usuario_vendedor	int(11)
freelance	varchar(1) [N]
autorizaws	varchar(10) [0]
nombre_representante	varchar(150) NULL
representante_geografico	varchar(1) [N]
cuit_internacional	varchar(50) NULL
cliente_promo	int(1)
cliente_web	int(1)
cliente_pasajerodirecto	tinyint(1)
fk_cadenacliente_id	int(10)
plazo_pago	int(3)
idnemo	int(10)
idtravelc	int(10)
tipofacturacion	int(2)
licencia_id	int(11)	licencia witwan
tipo_fce	varchar(50) [SCA]
factura_automatica	int(1) [0]*/
