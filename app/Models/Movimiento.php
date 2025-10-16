<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Movimiento
 *
 * @property int $movimiento_id
 * @property int $fk_asientocontable_id
 * @property string $statusmovimiento
 * @property int $fk_file_id
 * @property int $fk_plancuenta_id
 * @property string $fk_moneda_id
 * @property int $cuenta_debito
 * @property int $cuenta_credito
 * @property float $cotizacion_moneda
 * @property float $monto
 * @property float $montofinal
 * @property string $tipo
 * @property Carbon $fecha
 * @property Carbon $fecha_acreditacion
 * @property Carbon $regdate
 * @property int $fk_usuario_id
 * @property int $fk_cliente_id
 * @property int $fk_proveedor_id
 * @property int $fk_factura_id
 * @property int $fk_notacredito_id
 * @property int $fk_notadebito_id
 * @property int $fk_ordenadmin_id
 * @property int $fk_facturaproveedor_id
 * @property string $descripcion
 * @property string $banco
 * @property string $nrodocumento
 * @property string $operacion
 * @property int $fk_recibo_id
 * @property float $porcentajeadministracion
 * @property float $porcentajereceptivo
 * @property float $porcentajemayorista
 * @property float $porcentajeminorista
 * @property float $porcentajeconsolidador
 * @property int $fk_movimiento_id
 * @property int $utilizado
 * @property int $afecta_cobranza
 * @property int $fk_itemgasto_id
 * @property int $statusdocumento
 * @property int $filtro_cliente
 * @property int $filtro_proveedor
 * @property string $filtro_documento
 * @property int $filtro_file
 * @property int $filtro_servicio
 * @property int $auxiliar
 *
 * @property Asientocontable $asientocontable
 * @property Cliente $cliente
 * @property Factura $factura
 * @property Facturaproveedor $facturaproveedor
 * @property Moneda $moneda
 * @property Movimiento $movimiento
 * @property Notacredito $notacredito
 * @property Notadebito $notadebito
 * @property Ordenadmin $ordenadmin
 * @property Plancuenta $plancuenta
 * @property Proveedor $proveedor
 * @property Recibo $recibo
 * @property Reserva $reserva
 * @property Usuario $usuario
 * @property Collection|Movimiento[] $movimientos
 *
 * @package App\Models
 */
class Movimiento extends Model
{
    protected $table = 'movimiento';
    protected $primaryKey = 'movimiento_id';
    public $timestamps = false;

    protected $casts = [
        'fk_asientocontable_id' => 'int',
        'fk_file_id' => 'int',
        'fk_plancuenta_id' => 'int',
        'cuenta_debito' => 'int',
        'cuenta_credito' => 'int',
        'cotizacion_moneda' => 'float',
        'monto' => 'float',
        'montofinal' => 'float',
        'fecha' => 'datetime',
        'fecha_acreditacion' => 'datetime',
        'regdate' => 'datetime',
        'fk_usuario_id' => 'int',
        'fk_cliente_id' => 'int',
        'fk_proveedor_id' => 'int',
        'fk_factura_id' => 'int',
        'fk_notacredito_id' => 'int',
        'fk_notadebito_id' => 'int',
        'fk_ordenadmin_id' => 'int',
        'fk_facturaproveedor_id' => 'int',
        'fk_recibo_id' => 'int',
        'porcentajeadministracion' => 'float',
        'porcentajereceptivo' => 'float',
        'porcentajemayorista' => 'float',
        'porcentajeminorista' => 'float',
        'porcentajeconsolidador' => 'float',
        'fk_movimiento_id' => 'int',
        'utilizado' => 'int',
        'afecta_cobranza' => 'int',
        'fk_itemgasto_id' => 'int',
        'statusdocumento' => 'int',
        'filtro_cliente' => 'int',
        'filtro_proveedor' => 'int',
        'filtro_file' => 'int',
        'filtro_servicio' => 'int',
        'auxiliar' => 'int'
    ];

    protected $fillable = [
        'fk_asientocontable_id',
        'statusmovimiento',
        'fk_file_id',
        'fk_plancuenta_id',
        'fk_moneda_id',
        'cuenta_debito',
        'cuenta_credito',
        'cotizacion_moneda',
        'monto',
        'montofinal',
        'tipo',
        'fecha',
        'fecha_acreditacion',
        'regdate',
        'fk_usuario_id',
        'fk_cliente_id',
        'fk_proveedor_id',
        'fk_factura_id',
        'fk_notacredito_id',
        'fk_notadebito_id',
        'fk_ordenadmin_id',
        'fk_facturaproveedor_id',
        'descripcion',
        'banco',
        'nrodocumento',
        'operacion',
        'fk_recibo_id',
        'porcentajeadministracion',
        'porcentajereceptivo',
        'porcentajemayorista',
        'porcentajeminorista',
        'porcentajeconsolidador',
        'fk_movimiento_id',
        'utilizado',
        'afecta_cobranza',
        'fk_itemgasto_id',
        'statusdocumento',
        'filtro_cliente',
        'filtro_proveedor',
        'filtro_documento',
        'filtro_file',
        'filtro_servicio',
        'auxiliar'
    ];

    protected $attributes = [
        'utilizado' => 0,
        'afecta_cobranza' => 1,
        'statusdocumento' => 1,
        'auxiliar' => 0,
        'fk_file_id' => 0,
        'fk_plancuenta_id' => 0,
        'cuenta_debito' => 0,
        'cuenta_credito' => 0,
        'cotizacion_moneda' => 1,
        'monto' => 0,
        'montofinal' => 0,
        'tipo' => 'E',
        'fk_usuario_id' => 0,
        'fk_cliente_id' => 0,
        'fk_proveedor_id' => 0,
        'fk_factura_id' => 0,
        'fk_notacredito_id' => 0,
        'fk_notadebito_id' => 0,
        'fk_ordenadmin_id' => 0,
        'fk_facturaproveedor_id' => 0,
        'descripcion' => '',
        'banco' => '',
        'nrodocumento' => '',
        'operacion' => '',
        'fk_recibo_id' => 0,
        'porcentajeadministracion' => 0,
        'porcentajereceptivo' => 0,
        'porcentajemayorista' => 0,
        'porcentajeminorista' => 0,
        'porcentajeconsolidador' => 0,
        'fk_movimiento_id' => 0,
        'fk_itemgasto_id' => 0,
        'filtro_cliente' => 0,
        'filtro_proveedor' => 0,
        'filtro_documento' => '',
        'filtro_file' => 0,
        'filtro_servicio' => 0
    ];

    public function asientocontable()
    {
        return $this->belongsTo(Asientocontable::class, 'fk_asientocontable_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente_id');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'fk_factura_id');
    }

    public function facturaproveedor()
    {
        return $this->belongsTo(Facturaproveedor::class, 'fk_facturaproveedor_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class, 'fk_movimiento_id');
    }

    public function notacredito()
    {
        return $this->belongsTo(Notacredito::class, 'fk_notacredito_id');
    }

    public function notadebito()
    {
        return $this->belongsTo(Notadebito::class, 'fk_notadebito_id');
    }

    public function ordenadmin()
    {
        return $this->belongsTo(Ordenadmin::class, 'fk_ordenadmin_id');
    }

    public function plancuenta()
    {
        return $this->belongsTo(Plancuenta::class, 'fk_plancuenta_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'fk_proveedor_id');
    }

    public function recibo()
    {
        return $this->belongsTo(Recibo::class, 'fk_recibo_id');
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'fk_file_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario_id');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'fk_movimiento_id');
    }
}
