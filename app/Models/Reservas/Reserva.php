<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Clientes\Cliente;
use App\Models\Usuarios\Usuario;
use App\Models\Sistemas\Sistema;
use App\Models\Sistemas\SistemaAplicacion;
use App\Models\Monedas\Moneda;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reserva';
    protected $primaryKey = 'reserva_id';

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
        'inicio',
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
        'status_factura'
    ];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_vencimiento' => 'date',
        'regdate' => 'datetime',
        'um' => 'timestamp',
        'inicio' => 'date',
        'vencimiento_senia' => 'date',
        'cerrada' => 'boolean',
        'autorizado' => 'boolean',
        'escotizacion' => 'boolean',
        'mostrarreprogramados' => 'boolean',
        'auditado' => 'boolean',
        'reserva_mayorista' => 'boolean',
    ];

    // Relationships
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente_id', 'cliente_id');
    }

    public function facturarA()
    {
        return $this->belongsTo(Cliente::class, 'facturar_a', 'cliente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario_id', 'usuario_id');
    }

    public function sistema()
    {
        return $this->belongsTo(Sistema::class, 'fk_sistema_id', 'sistema_id');
    }

    public function sistemaAplicacion()
    {
        return $this->belongsTo(Sistema::class, 'fk_sistemaaplicacion_id', 'sistema_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'fk_reserva_id', 'reserva_id');
    }

    // Add other relationships as needed
}
