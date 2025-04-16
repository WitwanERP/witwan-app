<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Productos\Aerolinea;
use App\Models\Empresas\Proveedor;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Ciudad;
use App\Models\Configuracion\TipoClaveFiscal;
use App\Models\Configuracion\CondicionIva;
use App\Models\Administracion\Moneda;
use App\Models\Tarifario;
use App\Models\User as Usuario;

class Pasajero extends Model
{
    use HasFactory;

    protected $table = 'pasajero';
    protected $primaryKey = 'pasajero_id';
    public $timestamps = false;

    protected $casts = [
        'pasajero_nacimiento' => 'date',
        'emisorfecha' => 'date',
        'vencimientodoc' => 'date',
        'ultimo_mail' => 'date',
        'freelance' => 'boolean',
        'habilita' => 'boolean',
        'mostrar_ficha' => 'integer',
        'gastos_iva' => 'decimal:2',
        'gastos_fijo_1' => 'decimal:2',
        'gastos_porcentaje_1' => 'decimal:2',
    ];

    protected $fillable = [
        'pasajero_nombre',
        'pasajero_apellido',
        'pasajero_apodo',
        'pasajero_nacionalidad',
        'pasajero_nacimiento',
        'pasajero_sexo',
        'pasajero_email',
        'pasajero_clave',
        'pasajero_password',
        'fk_cliente_id',
        'mostrar_ficha',
        'cargo',
        'cliente_asociado',
        'fk_usuario_vendedor',
        'fk_usuario_promotor1',
        'freelance',
        'habilita',
        'pasajero_foto',
        'tipodoc',
        'nrodoc',
        'emisordoc',
        'emisorfecha',
        'vencimientodoc',
        'pasajero_direccionfiscal',
        'pasajero_codigopostal',
        'fk_pais_id',
        'pasajero_ciudad',
        'fk_ciudad_id',
        'fk_tipoclavefiscal_id',
        'nro_clavefiscal',
        'fk_condicioniva_id',
        'fk_tarifario1_id',
        'fk_tarifario2_id',
        'gastos_iva',
        'fk_moneda_id',
        'gastos_fijo_1',
        'gastos_porcentaje_1',
        'fotodoc',
        'observaciones',
        'ultimo_mail',
    ];

    /**
     * Relación con el país
     */
    public function pais()
    {
        return $this->belongsTo(Pais::class, 'fk_pais_id', 'pais_id');
    }

    /**
     * Relación con la ciudad
     */
    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'fk_ciudad_id', 'ciudad_id');
    }

    /**
     * Relación con el cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente_id', 'cliente_id');
    }

    /**
     * Relación con el tipo de clave fiscal
     */
    public function tipoClaveFiscal()
    {
        return $this->belongsTo(TipoClaveFiscal::class, 'fk_tipoclavefiscal_id');
    }

    /**
     * Relación con la condición de IVA
     */
    public function condicionIva()
    {
        return $this->belongsTo(CondicionIva::class, 'fk_condicioniva_id');
    }


    /**
     * Relación con la moneda
     */
    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'fk_moneda_id', 'moneda_id');
    }

    /**
     * Relación con el vendedor
     */
    public function vendedor()
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario_vendedor', 'usuario_id');
    }

    /**
     * Método para obtener el nombre completo del pasajero
     */
    public function getNombreCompletoAttribute()
    {
        return "{$this->pasajero_apellido}, {$this->pasajero_nombre}";
    }
}
/*Columna	Tipo	Comentario
pasajero_id	int(10) Incremento automático
pasajero_nombre	varchar(100)
pasajero_apellido	varchar(100)
pasajero_apodo	varchar(100)
pasajero_nacionalidad	varchar(100)
pasajero_nacimiento	varchar(100)
pasajero_sexo	varchar(1) [M]
pasajero_email	varchar(100)
pasajero_clave	varchar(100)
pasajero_password	varchar(100)
fk_cliente_id	int(10)
mostrar_ficha	int(10)
cargo	varchar(100)
cliente_asociado	text
fk_usuario_vendedor	int(10)
fk_usuario_promotor1	text
freelance	varchar(1) [N]
habilita	varchar(1) [Y]
pasajero_foto	varchar(150)
tipodoc	varchar(15)
nrodoc	varchar(50)
emisordoc	int(10)
emisorfecha	varchar(100)
vencimientodoc	varchar(100)
pasajero_direccionfiscal	varchar(100)
pasajero_codigopostal	varchar(100)
fk_pais_id	int(10)
pasajero_ciudad	varchar(100)
fk_ciudad_id	int(10)
fk_tipoclavefiscal_id	int(10)
nro_clavefiscal	varchar(50)
fk_condicioniva_id	int(11)
fk_tarifario1_id	int(11)
fk_tarifario2_id	int(11)
gastos_iva	decimal(15,2)
fk_moneda_id	varchar(3)
gastos_fijo_1	decimal(15,2)
gastos_porcentaje_1	decimal(15,2)
fotodoc	text
observaciones	text
ultimo_mail	date	*/
