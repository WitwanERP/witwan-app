<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Servicio
 *
 * @property int $servicio_id
 * @property string $servicio_nombre
 * @property int $fk_reserva_id
 * @property string $fk_tipoproducto_id
 * @property int $fk_producto_id
 * @property int $fk_proveedor_id
 * @property int $fk_prestador_id
 * @property int $fk_ciudad_id
 * @property Carbon $vigencia_ini
 * @property Carbon $vigencia_fin
 * @property int $adultos
 * @property int $menores
 * @property int $juniors
 * @property int $infante
 * @property int $jubilado
 * @property int $fk_tarifacategoria_id
 * @property int $fk_regimen_id
 * @property int $fk_base_id
 * @property string $status
 * @property string $moneda_costo
 * @property float $iva
 * @property string $fk_moneda_id
 * @property float $impuestos
 * @property float $comision
 * @property float $costo
 * @property float $iva_costo
 * @property float $total
 * @property float $totalservicio
 * @property float $rg_terrestre
 * @property float $rg_aereo
 * @property float $extra1
 * @property float $extra2
 * @property float $extra3
 * @property float $extra4
 * @property string $paxes
 * @property string $info
 * @property Carbon $vencimiento_proveedor
 * @property string $nro_confirmacion
 * @property string $mail_proveedor
 * @property string $comentarios
 * @property string $retira_voucher
 * @property string $autoriza_evoucher
 * @property string $texto_voucher
 * @property int $prev_file_id
 * @property Carbon $regdate
 * @property float $cotcosto
 * @property float $cotventa
 * @property float $renta
 * @property int $rentaguardada
 * @property Carbon $vencimiento2
 * @property Carbon $vencimiento3
 * @property float $montovencimiento1
 * @property float $montovencimiento2
 * @property float $montovencimiento3
 * @property string $origen
 * @property string $externalid
 * @property float $markupinterno
 * @property float $comisionproveedor_porcentaje
 * @property float $comisionproveedor_valor
 * @property float $costo_exento
 * @property float $costo_10
 * @property float $costo_afecto
 * @property float $costo_nocomputable
 * @property float $markup_valor
 * @property float $markup_porcentaje
 * @property int $serviciorelacionado
 * @property float $aboletear
 * @property int $devuelto
 * @property int $facturado
 * @property int $id_devolucion
 * @property string $nombre_regimen
 * @property string $nombre_habitacion
 * @property string $json_extra
 * @property int $item
 * @property string $contable
 * @property int $cupo_reservado
 * @property Carbon|null $cancelled_at
 * @property int $servicio_mayorista_id
 *
 * @property Ciudad $ciudad
 * @property Moneda $moneda
 * @property Producto $producto
 * @property Proveedor $proveedor
 * @property Regiman $regiman
 * @property Reserva $reserva
 * @property Submodulo $submodulo
 * @property Tarifacategorium $tarifacategorium
 * @property Collection|Facturaaerolinea[] $facturaaerolineas
 * @property Collection|Historialfile[] $historialfiles
 * @property Collection|Pnraereo[] $pnraereos
 * @property Collection|RelFacturaproveedorocupacion[] $rel_facturaproveedorocupacions
 * @property RelOcupacionprecompra|null $rel_ocupacionprecompra
 * @property RelOcupacionvigencium|null $rel_ocupacionvigencium
 * @property RelOrdenadminocupacion|null $rel_ordenadminocupacion
 * @property Collection|RelServiciofactura[] $rel_serviciofacturas
 * @property Collection|Reportebsptkt[] $reportebsptkts
 * @property Collection|ServicioExtra[] $servicio_extras
 * @property Collection|ServicioNomina[] $servicio_nominas
 * @property Collection|Serviciofactura[] $serviciofacturas
 * @property Collection|Usuariocomision[] $usuariocomisions
 *
 * @package App\Models
 */
class Servicio extends Model
{
    protected $table = 'servicio';
    protected $primaryKey = 'servicio_id';
    public $timestamps = false;

    protected $casts = [
        'fk_reserva_id' => 'int',
        'fk_producto_id' => 'int',
        'fk_proveedor_id' => 'int',
        'fk_prestador_id' => 'int',
        'fk_ciudad_id' => 'int',
        'vigencia_ini' => 'datetime',
        'vigencia_fin' => 'datetime',
        'adultos' => 'int',
        'menores' => 'int',
        'juniors' => 'int',
        'infante' => 'int',
        'jubilado' => 'int',
        'fk_tarifacategoria_id' => 'int',
        'fk_regimen_id' => 'int',
        'fk_base_id' => 'int',
        'iva' => 'float',
        'impuestos' => 'float',
        'comision' => 'float',
        'costo' => 'float',
        'iva_costo' => 'float',
        'total' => 'float',
        'totalservicio' => 'float',
        'rg_terrestre' => 'float',
        'rg_aereo' => 'float',
        'extra1' => 'float',
        'extra2' => 'float',
        'extra3' => 'float',
        'extra4' => 'float',
        'vencimiento_proveedor' => 'datetime',
        'prev_file_id' => 'int',
        'regdate' => 'datetime',
        'cotcosto' => 'float',
        'cotventa' => 'float',
        'renta' => 'float',
        'rentaguardada' => 'int',
        'vencimiento2' => 'datetime',
        'vencimiento3' => 'datetime',
        'montovencimiento1' => 'float',
        'montovencimiento2' => 'float',
        'montovencimiento3' => 'float',
        'markupinterno' => 'float',
        'comisionproveedor_porcentaje' => 'float',
        'comisionproveedor_valor' => 'float',
        'costo_exento' => 'float',
        'costo_10' => 'float',
        'costo_afecto' => 'float',
        'costo_nocomputable' => 'float',
        'markup_valor' => 'float',
        'markup_porcentaje' => 'float',
        'serviciorelacionado' => 'int',
        'aboletear' => 'float',
        'devuelto' => 'int',
        'facturado' => 'int',
        'id_devolucion' => 'int',
        'item' => 'int',
        'cupo_reservado' => 'int',
        'cancelled_at' => 'datetime',
        'servicio_mayorista_id' => 'int'
    ];

    protected $fillable = [
        'servicio_nombre',
        'fk_reserva_id',
        'fk_tipoproducto_id',
        'fk_producto_id',
        'fk_proveedor_id',
        'fk_prestador_id',
        'fk_ciudad_id',
        'vigencia_ini',
        'vigencia_fin',
        'adultos',
        'menores',
        'juniors',
        'infante',
        'jubilado',
        'fk_tarifacategoria_id',
        'fk_regimen_id',
        'fk_base_id',
        'status',
        'moneda_costo',
        'iva',
        'fk_moneda_id',
        'impuestos',
        'comision',
        'costo',
        'iva_costo',
        'total',
        'totalservicio',
        'rg_terrestre',
        'rg_aereo',
        'extra1',
        'extra2',
        'extra3',
        'extra4',
        'paxes',
        'info',
        'vencimiento_proveedor',
        'nro_confirmacion',
        'mail_proveedor',
        'comentarios',
        'retira_voucher',
        'autoriza_evoucher',
        'texto_voucher',
        'prev_file_id',
        'regdate',
        'cotcosto',
        'cotventa',
        'renta',
        'rentaguardada',
        'vencimiento2',
        'vencimiento3',
        'montovencimiento1',
        'montovencimiento2',
        'montovencimiento3',
        'origen',
        'externalid',
        'markupinterno',
        'comisionproveedor_porcentaje',
        'comisionproveedor_valor',
        'costo_exento',
        'costo_10',
        'costo_afecto',
        'costo_nocomputable',
        'markup_valor',
        'markup_porcentaje',
        'serviciorelacionado',
        'aboletear',
        'devuelto',
        'facturado',
        'id_devolucion',
        'nombre_regimen',
        'nombre_habitacion',
        'json_extra',
        'item',
        'contable',
        'cupo_reservado',
        'cancelled_at',
        'servicio_mayorista_id'
    ];

    protected $attributes = [
        'fk_producto_id' => 0,
        'fk_proveedor_id' => 0,
        'fk_prestador_id' => 0,
        'fk_ciudad_id' => 0,
        'adultos' => 1,
        'menores' => 0,
        'juniors' => 0,
        'infante' => 0,
        'jubilado' => 0,
        'fk_tarifacategoria_id' => 0,
        'fk_regimen_id' => 0,
        'fk_base_id' => 0,
        'status' => 'RQ',
        'retira_voucher' => 'N',
        'autoriza_evoucher' => 'N',
        'contable' => '',
        'cupo_reservado' => 0,
        'devuelto' => 0,
        'facturado' => 0,
        'id_devolucion' => 0,
        'servicio_mayorista_id' => 0,
        'costo_exento' => 0,
        'costo_10' => 0,
        'costo_afecto' => 0,
        'costo_nocomputable' => 0,
        'markup_valor' => 0,
        'markup_porcentaje' => 0,
        'comisionproveedor_porcentaje' => 0,
        'comisionproveedor_valor' => 0,
        'renta' => 0,
        'rentaguardada' => 0,
        'extra1' => 0,
        'extra2' => 0,
        'extra3' => 0,
        'extra4' => 0,
        'impuestos' => 0,
        'comision' => 0,
        'costo' => 0,
        'iva_costo' => 0,
        'total' => 0,
        'totalservicio' => 0,
        'rg_terrestre' => 0,
        'rg_aereo' => 0,
        'prev_file_id' => 0,
        'item' => 1,
        'nro_confirmacion' => '',
        'mail_proveedor' => '',
        'iva' => 0,
        'origen' => 'WIT',
        'externalid' => '',
        'markupinterno' => 0,
        'serviciorelacionado' => 0,
        'aboletear' => 0,
        'nombre_regimen' => '',
        'nombre_habitacion' => '',
        'json_extra' => '',
        'vencimiento2' => '0000-00-00 00:00:00',
        'vencimiento3' => '0000-00-00 00:00:00',
        'montovencimiento1' => 0,
        'montovencimiento2' => 0,
        'montovencimiento3' => 0,
        'cancelled_at' => '0000-00-00 00:00:00',
        'regdate' => now()
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'fk_ciudad_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'fk_producto_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'fk_prestador_id');
    }

    public function regiman()
    {
        return $this->belongsTo(Regiman::class, 'fk_regimen_id');
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'fk_reserva_id');
    }

    public function submodulo()
    {
        return $this->belongsTo(Submodulo::class, 'fk_tipoproducto_id');
    }

    public function tarifacategorium()
    {
        return $this->belongsTo(Tarifacategorium::class, 'fk_tarifacategoria_id');
    }

    public function facturaaerolineas()
    {
        return $this->hasMany(Facturaaerolinea::class, 'fk_servicio_id');
    }

    public function historialfiles()
    {
        return $this->hasMany(Historialfile::class, 'fk_servicio_id');
    }

    public function pnraereos()
    {
        return $this->hasMany(Pnraereo::class, 'fk_ocupacion_id');
    }

    public function rel_facturaproveedorocupacions()
    {
        return $this->hasMany(RelFacturaproveedorocupacion::class, 'fk_ocupacion_id');
    }

    public function rel_ocupacionprecompra()
    {
        return $this->hasOne(RelOcupacionprecompra::class, 'fk_ocupacion_id');
    }

    public function rel_ocupacionvigencium()
    {
        return $this->hasOne(RelOcupacionvigencium::class, 'fk_ocupacion_id');
    }

    public function rel_ordenadminocupacion()
    {
        return $this->hasOne(RelOrdenadminocupacion::class, 'fk_ocupacion_id');
    }

    public function rel_serviciofacturas()
    {
        return $this->hasMany(RelServiciofactura::class, 'fk_servicio_id');
    }

    public function reportebsptkts()
    {
        return $this->hasMany(Reportebsptkt::class, 'fk_ocupacion_id');
    }

    public function servicio_extras()
    {
        return $this->hasMany(ServicioExtra::class, 'fk_servicio_id');
    }

    public function servicio_nominas()
    {
        return $this->hasMany(ServicioNomina::class, 'fk_servicio_id');
    }

    public function serviciofacturas()
    {
        return $this->hasMany(Serviciofactura::class, 'fk_servicio_id');
    }

    public function usuariocomisions()
    {
        return $this->hasMany(Usuariocomision::class, 'fk_ocupacion_id');
    }
}
