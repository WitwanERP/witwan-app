<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Productos\TipoProducto;
use App\Models\Productos\Producto;
use App\Models\Proveedores\Proveedor;
use App\Models\Prestadores\Prestador;
use App\Models\Ciudades\Ciudad;
use App\Models\Tarifas\TarifaCategoria;
use App\Models\Regimenes\Regimen;
use App\Models\Bases\Base;
use App\Models\Monedas\Moneda;
use App\Models\Devoluciones\Devolucion;
use App\Models\Servicios\ServicioMayorista;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicio';
    protected $primaryKey = 'servicio_id';

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
        'mail_proveedor_bkp',
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
        'servicio_mayorista_id',
    ];

    protected $casts = [
        'vigencia_ini' => 'date',
        'vigencia_fin' => 'date',
        'vencimiento_proveedor' => 'date',
        'regdate' => 'timestamp',
        'vencimiento2' => 'date',
        'vencimiento3' => 'date',
        'cancelled_at' => 'datetime',
    ];

    // Define relationships
    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'fk_reserva_id', 'reserva_id');
    }

    public function tipoproducto()
    {
        return $this->belongsTo(TipoProducto::class, 'fk_tipoproducto_id', 'tipoproducto_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'fk_producto_id', 'producto_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'fk_proveedor_id', 'proveedor_id');
    }

    public function prestador()
    {
        return $this->belongsTo(Prestador::class, 'fk_prestador_id', 'prestador_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'fk_ciudad_id', 'ciudad_id');
    }

    public function tarifacategoria()
    {
        return $this->belongsTo(TarifaCategoria::class, 'fk_tarifacategoria_id', 'tarifacategoria_id');
    }

    public function regimen()
    {
        return $this->belongsTo(Regimen::class, 'fk_regimen_id', 'regimen_id');
    }

    public function base()
    {
        return $this->belongsTo(Base::class, 'fk_base_id', 'base_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    public function devolucion()
    {
        return $this->belongsTo(Devolucion::class, 'id_devolucion', 'devolucion_id');
    }

    public function servicioMayorista()
    {
        return $this->belongsTo(ServicioMayorista::class, 'servicio_mayorista_id', 'servicio_mayorista_id');
    }
}
