<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Reserva
 *
 * @property int $reserva_id
 * @property int $fk_cliente_id
 * @property int $cliente_usuario
 * @property int $facturar_a
 * @property int $fk_agrupado_id
 * @property int $fk_filepadre_id
 * @property int $fk_sistema_id
 * @property int $fk_sistemaaplicacion_id
 * @property int $fk_identidadfiscal_id
 * @property int $fk_usuario_id
 * @property int $agente
 * @property string $fk_filestatus_id
 * @property int $fk_guia_id
 * @property Carbon $fecha_alta
 * @property Carbon $fecha_vencimiento
 * @property Carbon $regdate
 * @property Carbon $um
 * @property int $umu
 * @property int $codigo
 * @property string $tipocodigo
 * @property string $titular_nombre
 * @property string $titular_apellido
 * @property string $titular_email
 * @property string $titular_celular
 * @property int $cerrada
 * @property int $autorizado
 * @property string $observaciones
 * @property string $observaciones_publicas
 * @property string $fk_moneda_id
 * @property float $total
 * @property float $comision
 * @property float $impuestos
 * @property float $totalservicios
 * @property float $iva
 * @property float $gastos
 * @property float $rg_terrestre
 * @property float $rg_trasnporte
 * @property float $cobrado
 * @property float $renta
 * @property float $costo
 * @property float $ivacosto
 * @property float $ajuste
 * @property float $extra1
 * @property float $extra2
 * @property float $extra3
 * @property float $extra4
 * @property string $moneda_factura
 * @property int $fk_negocio_id
 * @property int $promotor
 * @property int $promotoraereo
 * @property Carbon $vencimiento_senia
 * @property string $areaanalitica
 * @property int $escotizacion
 * @property string $codigo_externo
 * @property int $mostrarreprogramados
 * @property int $operativo
 * @property int $vendedor_mayorista
 * @property float $markup_mayorista
 * @property int $auditado
 * @property int $reserva_mayorista
 * @property int $reservaid_mayorista
 * @property string $info_extra
 * @property string $status_factura
 *
 * @property Cliente $cliente
 * @property Identidadfiscal $identidadfiscal
 * @property Moneda $moneda
 * @property Negocio $negocio
 * @property Reserva $reserva
 * @property Sistema $sistema
 * @property Usuario $usuario
 * @property Collection|Factura[] $facturas
 * @property Collection|Filearchivo[] $filearchivos
 * @property Collection|Filecomentario[] $filecomentarios
 * @property Filemail|null $filemail
 * @property Collection|Filenotificacion[] $filenotificacions
 * @property Collection|Historialfile[] $historialfiles
 * @property Collection|Movimiento[] $movimientos
 * @property Collection|Notacredito[] $notacreditos
 * @property Collection|RelFilefactura[] $rel_filefacturas
 * @property RelFilerecibo|null $rel_filerecibo
 * @property Collection|Reserva[] $reservas
 * @property ReservaExtra|null $reserva_extra
 * @property Collection|Servicio[] $servicios
 * @property Collection|Usuariocomision[] $usuariocomisions
 *
 * @package App\Models
 */
class Reserva extends Model
{
    protected $table = 'reserva';
    protected $primaryKey = 'reserva_id';
    public $timestamps = false;

    protected $casts = [
        'fk_cliente_id' => 'int',
        'cliente_usuario' => 'int',
        'facturar_a' => 'int',
        'fk_agrupado_id' => 'int',
        'fk_filepadre_id' => 'int',
        'fk_sistema_id' => 'int',
        'fk_sistemaaplicacion_id' => 'int',
        'fk_identidadfiscal_id' => 'int',
        'fk_usuario_id' => 'int',
        'agente' => 'int',
        'fk_guia_id' => 'int',
        'fecha_alta' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'inicio' => 'datetime',
        'regdate' => 'datetime',
        'um' => 'datetime',
        'umu' => 'int',
        'codigo' => 'int',
        'cerrada' => 'int',
        'autorizado' => 'int',
        'total' => 'float',
        'comision' => 'float',
        'impuestos' => 'float',
        'totalservicios' => 'float',
        'iva' => 'float',
        'gastos' => 'float',
        'rg_terrestre' => 'float',
        'rg_trasnporte' => 'float',
        'cobrado' => 'float',
        'renta' => 'float',
        'costo' => 'float',
        'ivacosto' => 'float',
        'ajuste' => 'float',
        'extra1' => 'float',
        'extra2' => 'float',
        'extra3' => 'float',
        'extra4' => 'float',
        'fk_negocio_id' => 'int',
        'promotor' => 'int',
        'promotoraereo' => 'int',
        'vencimiento_senia' => 'datetime',
        'escotizacion' => 'int',
        'mostrarreprogramados' => 'int',
        'operativo' => 'int',
        'vendedor_mayorista' => 'int',
        'markup_mayorista' => 'float',
        'auditado' => 'int',
        'reserva_mayorista' => 'int',
        'reservaid_mayorista' => 'int'
    ];

    protected $fillable = [
        'fk_cliente_id',
        'cliente_usuario',
        'facturar_a',
        'fk_agrupado_id',
        'fk_filepadre_id',
        'fk_sistema_id',
        'fk_sistemaaplicacion_id',
        'fk_identidadfiscal_id',
        'fk_usuario_id',
        'agente',
        'fk_filestatus_id',
        'fk_guia_id',
        'fecha_alta',
        'fecha_vencimiento',
        'regdate',
        'um',
        'umu',
        'codigo',
        'tipocodigo',
        'titular_nombre',
        'titular_apellido',
        'titular_email',
        'titular_celular',
        'cerrada',
        'autorizado',
        'observaciones',
        'observaciones_publicas',
        'fk_moneda_id',
        'total',
        'comision',
        'impuestos',
        'totalservicios',
        'iva',
        'gastos',
        'rg_terrestre',
        'rg_trasnporte',
        'cobrado',
        'renta',
        'costo',
        'ivacosto',
        'ajuste',
        'extra1',
        'extra2',
        'extra3',
        'extra4',
        'moneda_factura',
        'fk_negocio_id',
        'promotor',
        'promotoraereo',
        'vencimiento_senia',
        'areaanalitica',
        'escotizacion',
        'codigo_externo',
        'mostrarreprogramados',
        'operativo',
        'vendedor_mayorista',
        'markup_mayorista',
        'auditado',
        'reserva_mayorista',
        'reservaid_mayorista',
        'info_extra',
        'status_factura',
        'inicio'
    ];

    protected $attributes = [
        'vendedor_mayorista' => 0,
        'markup_mayorista' => 0,
        'auditado' => 0,
        'reserva_mayorista' => 0,
        'reservaid_mayorista' => 0,
        'cerrada' => 0,
        'autorizado' => 0,
        'escotizacion' => 0,
        'mostrarreprogramados' => 0,
        'operativo' => 0,
        'fk_negocio_id' => 0,
        'promotor' => 0,
        'promotoraereo' => 0,
        'fk_agrupado_id' => 0,
        'fk_filepadre_id' => 0,
        'fk_identidadfiscal_id' => 0,
        'fk_guia_id' => 0,
        'comision' => 0,
        'impuestos' => 0,
        'iva' => 0,
        'gastos' => 0,
        'rg_terrestre' => 0,
        'rg_trasnporte' => 0,
        'cobrado' => 0,
        'renta' => 0,
        'costo' => 0,
        'ivacosto' => 0,
        'ajuste' => 0,
        'extra1' => 0,
        'extra2' => 0,
        'extra3' => 0,
        'extra4' => 0,
        'observaciones' => '',
        'observaciones_publicas' => '',
        'info_extra' => '',
        'status_factura' => 'PE',
        'areaanalitica' => '',
        'codigo_externo' => '',
        'tipocodigo' => ''
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'facturar_a');
    }

    public function identidadfiscal()
    {
        return $this->belongsTo(Identidadfiscal::class, 'fk_identidadfiscal_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    public function negocio()
    {
        return $this->belongsTo(Negocio::class, 'fk_negocio_id');
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'fk_filepadre_id');
    }

    public function sistema()
    {
        return $this->belongsTo(Sistema::class, 'fk_sistemaaplicacion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'agente');
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class, 'fk_file_id');
    }

    public function filearchivos()
    {
        return $this->hasMany(Filearchivo::class, 'fk_file_id');
    }

    public function filecomentarios()
    {
        return $this->hasMany(Filecomentario::class, 'fk_file_id');
    }

    public function filemail()
    {
        return $this->hasOne(Filemail::class, 'fk_file_id');
    }

    public function filenotificacions()
    {
        return $this->hasMany(Filenotificacion::class, 'fk_file_id');
    }

    public function historialfiles()
    {
        return $this->hasMany(Historialfile::class, 'fk_reserva_id');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'fk_file_id');
    }

    public function notacreditos()
    {
        return $this->hasMany(Notacredito::class, 'fk_file_id');
    }

    public function rel_filefacturas()
    {
        return $this->hasMany(RelFilefactura::class, 'fk_file_id');
    }

    public function rel_filerecibo()
    {
        return $this->hasOne(RelFilerecibo::class, 'fk_file_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'fk_filepadre_id');
    }

    public function reserva_extra()
    {
        return $this->hasOne(ReservaExtra::class, 'fk_reserva_id');
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'fk_reserva_id');
    }

    public function usuariocomisions()
    {
        return $this->hasMany(Usuariocomision::class, 'fk_file_id');
    }
}
