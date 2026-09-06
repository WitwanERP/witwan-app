<?php

namespace App\Http\Controllers\Web\Abm;

use App\Rules\ClaveFiscalValida;
use App\Services\Admin\ReplicadorColectora;
use App\Services\CatalogosService;
use App\Services\TablaLegacyService;
use App\Support\Licencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Proveedores (CI: configuracion/proveedor). Los campos
 * condicionales del legacy dependen de flags de sysconfig (nemo, usatravelc,
 * RoombeastProviders, usa_oc) y de la licencia (provincia sólo en morisan).
 * Logo e imagen (subida de archivos) quedan fuera de esta primera versión.
 * Cada alta/edición/baja se replica a las bases hijas de la colectora salvo
 * en mundotour_sdg y licencias CL, como los hooks del CI.
 */
class ProveedorController extends AbmController
{
    protected string $tabla = 'proveedor';

    protected string $pk = 'proveedor_id';

    protected string $ruta = 'config/proveedores';

    protected string $titulo = 'Proveedores';

    protected string $singular = 'Proveedor';

    protected int $porPagina = 100;

    protected array $columnasListado = [
        ['campo' => 'proveedor_id', 'label' => 'ID'],
        ['campo' => 'proveedor_nombre', 'label' => 'Nombre'],
        ['campo' => 'razonsocial', 'label' => 'Razón social'],
        ['campo' => 'cuit', 'label' => 'Registro fiscal'],
        ['campo' => 'pais_nombre', 'label' => 'País'],
        ['campo' => 'ciudad_nombre', 'label' => 'Ciudad'],
        ['campo' => 'habilita', 'label' => 'Habilitado', 'tipo' => 'bool'],
    ];

    protected array $filtrosLike = ['proveedor_nombre', 'razonsocial', 'cuit'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_pais_id', 'label' => 'País', 'opciones' => 'paises'],
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'opciones' => 'ciudades'],
        ['campo' => 'iata', 'label' => 'Cuenta contable', 'opciones' => 'cuentas'],
    ];

    protected string $sortDefault = 'proveedor_nombre';

    /** Where fijo del listado (Prestador lo restringe a prestador=1). */
    protected ?int $soloPrestador = null;

    public function __construct(protected CatalogosService $catalogos, protected ReplicadorColectora $colectora) {}

    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $query = DB::table('proveedor as p')
            ->leftJoin('pais as pa', 'pa.pais_id', '=', 'p.fk_pais_id')
            ->leftJoin('ciudad as c', 'c.ciudad_id', '=', 'p.fk_ciudad_id')
            ->select('p.proveedor_id', 'p.proveedor_nombre', 'p.razonsocial', 'p.cuit', 'p.habilita', 'p.prestador', 'pa.pais_nombre', 'c.ciudad_nombre')
            ->where('p.eliminar', '<>', 'Y');

        if ($this->soloPrestador !== null) {
            $query->where('p.prestador', $this->soloPrestador);
        }
        foreach ($this->filtrosLike as $campo) {
            $valor = trim((string) $request->get($campo, ''));
            if ($valor !== '') {
                $query->where("p.{$campo}", 'LIKE', "%{$valor}%");
            }
        }
        foreach ($this->filtrosSelect as $f) {
            $valor = (string) $request->get($f['campo'], '');
            if ($valor !== '') {
                $query->where("p.{$f['campo']}", $valor);
            }
        }
        $id = trim((string) $request->get('proveedor_id', ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('p.proveedor_id', (int) $id);
        }

        $registros = $query->orderBy('p.proveedor_nombre')->paginate($this->porPagina)->withQueryString();

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $this->presentar($registros),
            'filtros' => $request->only(array_merge($this->filtrosLike, array_column($this->filtrosSelect, 'campo'), ['proveedor_id'])),
        ]);
    }

    protected function campos(): array
    {
        $pais = Licencia::pais();
        $cuit = ['campo' => 'cuit', 'label' => 'Registro fiscal/tributario (CUIT/RUT/NIF/RUC)', 'tipo' => 'text', 'required' => true, 'max' => 20];
        if ($pais === 'AR') {
            $cuit['regla'] = ['required', 'string', 'max:20', new ClaveFiscalValida('AR', 'CUIT')];
        }

        $campos = [
            ['campo' => 'proveedor_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'razonsocial', 'label' => 'Razón social', 'tipo' => 'text', 'max' => 150],
            ['campo' => 'proveedor_direccion', 'label' => 'Dirección fiscal', 'tipo' => 'text', 'required' => true, 'max' => 255],
            $cuit,
            ['campo' => 'fk_condicioniva_id', 'label' => 'Condición IVA', 'tipo' => 'select', 'opciones' => 'condicionesIva'],
        ];

        if ((int) Licencia::sysconfig('nemo', 0) === 1) {
            $campos[] = ['campo' => 'proveedor_legajo', 'label' => 'Código Nemo', 'tipo' => 'text', 'max' => 50];
        }
        if ((int) Licencia::sysconfig('usatravelc', 0) === 1) {
            $campos[] = ['campo' => 'codigo_travelc', 'label' => 'Código Travel Compositor', 'tipo' => 'text', 'max' => 100];
        }

        $campos = array_merge($campos, [
            ['campo' => 'proveedor_telefono', 'label' => 'Teléfono', 'tipo' => 'text', 'max' => 50],
            ['campo' => 'proveedor_telefonoemergencia', 'label' => 'Teléfono de emergencia', 'tipo' => 'text', 'max' => 100],
            ['campo' => 'proveedor_codigopostal', 'label' => 'Código postal', 'tipo' => 'text', 'max' => 15],
            ['campo' => 'proveedor_email', 'label' => 'Email', 'tipo' => 'text', 'max' => 100, 'regla' => 'nullable|email|max:100'],
            ['campo' => 'proveedor_emailreservas', 'label' => 'Email reservas', 'tipo' => 'text', 'max' => 100, 'regla' => 'nullable|email|max:100'],
            ['campo' => 'fk_pais_id', 'label' => 'País', 'tipo' => 'select', 'opciones' => 'paises'],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad', 'tipo' => 'select', 'opciones' => 'ciudades', 'dependeDe' => 'fk_pais_id'],
        ]);

        if (Licencia::es('witwan_morisan')) {
            $provincias = ['CABA', 'Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán', 'Fuera de Argentina'];
            $campos[] = ['campo' => 'proveedor_provincia', 'label' => 'Provincia', 'tipo' => 'select', 'opciones' => array_map(fn ($p) => ['value' => $p, 'label' => $p], $provincias)];
        }

        $campos = array_merge($campos, [
            ['campo' => 'fk_cadenahotelera_id', 'label' => 'Cadena hotelera', 'tipo' => 'select', 'opciones' => 'cadenas'],
            ['campo' => 'iata', 'label' => 'Cuenta contable', 'tipo' => 'select', 'opciones' => 'cuentas', 'ancho' => 'completo'],
            ['campo' => 'voucherpropio', 'label' => '¿Voucher propio?', 'tipo' => 'checkbox'],
            ['campo' => 'prestador', 'label' => '¿Es prestador?', 'tipo' => 'checkbox'],
            ['campo' => 'enviadocumentos', 'label' => '¿Envía documentos?', 'tipo' => 'checkbox'],
            ['campo' => 'cartaemision', 'label' => 'Tiene cuenta corriente', 'tipo' => 'checkbox'],
            // Política de vencimiento
            ['campo' => 'vencimiento_dias', 'label' => 'Vencimiento: días (antes o después)', 'tipo' => 'number'],
            ['campo' => 'vencimiento_tipo', 'label' => 'Vencimiento: base de cálculo', 'tipo' => 'radio', 'opciones' => [['value' => 'CHI', 'label' => 'Check in'], ['value' => 'FEF', 'label' => 'Fecha factura'], ['value' => 'FEC', 'label' => 'Fecha de confirmación']]],
            // Cliente relacionado
            ['campo' => 'fk_cliente_id', 'label' => 'Cliente relacionado', 'tipo' => 'select', 'opciones' => 'clientes', 'ancho' => 'completo'],
            ['campo' => 'proveedor_infobanco', 'label' => 'Información bancaria', 'tipo' => 'textarea'],
            // Gastos por reserva
            ['campo' => 'tipo_extra', 'label' => 'Gastos por reserva: base de cálculo', 'tipo' => 'radio', 'opciones' => [['value' => 'PP', 'label' => 'Por pax'], ['value' => 'PF', 'label' => 'Por file']]],
            ['campo' => 'moneda_extra', 'label' => 'Gastos por reserva: moneda', 'tipo' => 'select', 'opciones' => 'monedas'],
            ['campo' => 'costo_extra', 'label' => 'Gastos por reserva: monto fijo', 'tipo' => 'decimal'],
            ['campo' => 'porcentaje_extra', 'label' => 'Gastos por reserva: %', 'tipo' => 'decimal'],
            ['campo' => 'gastoacliente', 'label' => 'Gasto aplicable a reserva', 'tipo' => 'checkbox'],
            // Características especiales
            ['campo' => 'edita_tarifa', 'label' => 'Edición de tarifas', 'tipo' => 'radio', 'opciones' => 'siNoLetra', 'default' => 'N'],
            ['campo' => 'habilita', 'label' => 'Habilitar proveedor', 'tipo' => 'radio', 'opciones' => 'siNoLetra', 'default' => 'Y'],
            ['campo' => 'comentario', 'label' => 'Comentarios', 'tipo' => 'textarea'],
        ]);

        if ((int) Licencia::sysconfig('usa_oc', 0) === 1) {
            $campos[] = ['campo' => 'proveedor_oc', 'label' => 'Usar orden de compra', 'tipo' => 'checkbox'];
        }

        return $campos;
    }

    protected function opciones(): array
    {
        return [
            'paises' => $this->catalogos->paises(),
            'ciudades' => $this->catalogos->ciudades(),
            'cuentas' => $this->catalogos->planCuentas(false),
            'clientes' => $this->catalogos->clientes(),
            'monedas' => $this->catalogos->monedas(),
            'cadenas' => DB::table('cadenahotelera')->orderBy('cadenahotelera_nombre')->get(['cadenahotelera_id', 'cadenahotelera_nombre'])
                ->map(fn ($c) => ['value' => (int) $c->cadenahotelera_id, 'label' => $c->cadenahotelera_nombre])->all(),
            'condicionesIva' => DB::table('condicioniva')->orderBy('condicioniva_nombre')->get(['condicioniva_id', 'condicioniva_nombre'])
                ->map(fn ($c) => ['value' => (int) $c->condicioniva_id, 'label' => $c->condicioniva_nombre])->all(),
            'siNoLetra' => [['value' => 'Y', 'label' => 'Sí'], ['value' => 'N', 'label' => 'No']],
        ];
    }

    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        if ($id === null) {
            $data['fk_usuario_id'] = (int) auth()->id();
            $data['fechacarga'] = now()->toDateTimeString();
            $data['eliminar'] = 'N';
        }
        $data['um'] = now()->toDateTimeString();

        return $data;
    }

    protected function despuesDeGuardar(int|string $id, array $data, bool $nuevo): void
    {
        if ($this->replica()) {
            $this->colectora->replicarFila('proveedor', 'proveedor_id', (int) $id, $this->colectora->hijas());
        }
    }

    protected function despuesDeEliminar(int|string $id): void
    {
        if ($this->replica()) {
            $this->colectora->borrarFila('proveedor', 'proveedor_id', (int) $id, $this->colectora->hijas());
        }
    }

    /** Condición de proveedor.php: no replica mundotour_sdg ni licencias CL. */
    protected function replica(): bool
    {
        return ! Licencia::es('mundotour_sdg') && Licencia::pais() !== 'CL';
    }
}
