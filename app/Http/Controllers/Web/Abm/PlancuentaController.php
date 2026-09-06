<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\Admin\PlanCuentaReplicador;
use App\Services\CatalogosService;
use App\Services\TablaLegacyService;
use App\Support\Licencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración > Contabilidad > Plan de cuentas (CI: administracion/plancuenta).
 * Los flags SI/NO son radios como en el legacy; "Requiere analítico", "Reporte de
 * gastos" y "Ajuste por inflación" sólo aparecen para las licencias/config que
 * los usan. Cada alta/edición/baja se replica a las bases hijas de la colectora.
 */
class PlancuentaController extends AbmController
{
    protected string $tabla = 'plancuenta';

    protected string $pk = 'plancuenta_id';

    protected string $ruta = 'admin/plan-cuentas';

    protected string $titulo = 'Plan de cuentas';

    protected string $singular = 'Cuenta';

    protected string $area = 'Administración';

    protected int $porPagina = 350;

    protected array $filtrosLike = ['plancuenta_codigo', 'plancuenta_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'arqueo', 'label' => 'Aparece en arqueo', 'opciones' => 'siNo'],
        ['campo' => 'plancuenta_g', 'label' => 'Habilitada', 'opciones' => 'siNo'],
        ['campo' => 'plancuenta_titulo', 'label' => 'Es título', 'opciones' => 'siNo'],
        ['campo' => 'cuentagasto', 'label' => 'En facturas de 3ros', 'opciones' => 'siNo'],
        ['campo' => 'plancuenta_cli', 'label' => 'En recibos', 'opciones' => 'siNoLetra'],
    ];

    protected string $sortDefault = 'plancuenta_codigo';

    public function __construct(private CatalogosService $catalogos, private PlanCuentaReplicador $replicador)
    {
        $this->columnasListado = [
            ['campo' => 'plancuenta_id', 'label' => 'ID'],
            ['campo' => 'plancuenta_codigo', 'label' => 'Código'],
            ['campo' => 'plancuenta_nombre', 'label' => 'Nombre'],
            ['campo' => 'totalizadora', 'label' => 'Cuenta totalizadora'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda'],
            ['campo' => 'arqueo', 'label' => 'Arqueo', 'tipo' => 'bool'],
            ['campo' => 'plancuenta_g', 'label' => 'Habilitada', 'tipo' => 'bool'],
            ['campo' => 'cartera', 'label' => 'Cartera', 'tipo' => 'bool'],
            ['campo' => 'plancuenta_titulo', 'label' => 'Título', 'tipo' => 'bool'],
            ['campo' => 'cuentagasto', 'label' => 'FC 3ros', 'tipo' => 'bool'],
            ['campo' => 'plancuenta_cli', 'label' => 'Recibos', 'tipo' => 'bool'],
            ['campo' => 'conceptos_adicionales', 'label' => 'Conceptos adic.', 'tipo' => 'bool'],
            ['campo' => 'plancuenta_saldo', 'label' => 'Saldo', 'opciones' => 'saldos'],
        ];
    }

    /** Listado con la cuenta totalizadora resuelta por self-join (como el `_query` del CI). */
    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $query = DB::table('plancuenta as p')
            ->leftJoin('plancuenta as t', 't.plancuenta_id', '=', 'p.fk_plancuenta_id')
            ->select('p.*', 't.plancuenta_nombre as totalizadora');

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
        $id = trim((string) $request->get('plancuenta_id', ''));
        if ($id !== '' && ctype_digit($id)) {
            $query->where('p.plancuenta_id', (int) $id);
        }

        $registros = $query->orderBy('p.plancuenta_codigo')->paginate($this->porPagina)->withQueryString();

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $this->presentar($registros),
            'filtros' => $request->only(array_merge($this->filtrosLike, array_column($this->filtrosSelect, 'campo'), ['plancuenta_id'])),
        ]);
    }

    protected function campos(): array
    {
        $siNo = ['tipo' => 'radio', 'opciones' => 'siNo', 'default' => 0];

        $campos = [
            ['campo' => 'plancuenta_codigo', 'label' => 'Código', 'tipo' => 'text', 'required' => true, 'max' => 50],
            ['campo' => 'plancuenta_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 150],
            ['campo' => 'fk_plancuenta_id', 'label' => 'Cuenta totalizadora', 'tipo' => 'select', 'opciones' => 'cuentas'],
            ['campo' => 'fk_moneda_id', 'label' => 'Moneda', 'tipo' => 'select', 'opciones' => 'monedas'],
            ['campo' => 'arqueo', 'label' => '¿Aparece en arqueo?'] + $siNo,
            ['campo' => 'plancuenta_g', 'label' => 'Habilitar', 'default' => 1] + $siNo,
        ];

        if (Licencia::es('mundotour_sdg', 'witwan_rays', 'witwan_intertour', 'witwan_mitani')) {
            $campos[] = ['campo' => 'requiereanalitico', 'label' => '¿Requiere analítico?'] + $siNo;
        }
        if ((int) Licencia::sysconfig('reportegastos', 0) === 1) {
            $campos[] = ['campo' => 'reportegastos', 'label' => 'Agregar en reporte de gastos'] + $siNo;
        }
        if ((int) Licencia::sysconfig('ajustesinflacion', 0) === 1) {
            $campos[] = ['campo' => 'ajusteinflacion', 'label' => 'Afectada por ajuste de inflación'] + $siNo;
        }

        return array_merge($campos, [
            ['campo' => 'cartera', 'label' => '¿Maneja cartera?'] + $siNo,
            ['campo' => 'plancuenta_titulo', 'label' => '¿Es título?'] + $siNo,
            ['campo' => 'cuentagasto', 'label' => 'Aparece en facturas de 3ros'] + $siNo,
            ['campo' => 'plancuenta_cli', 'label' => 'Aparece en recibos', 'tipo' => 'radio', 'opciones' => 'siNoLetra', 'default' => 'N'],
            ['campo' => 'conceptos_adicionales', 'label' => 'Aparece en conceptos adicionales (recibos)'] + $siNo,
            ['campo' => 'plancuenta_saldo', 'label' => 'Saldo', 'tipo' => 'radio', 'opciones' => 'saldos', 'default' => 'D'],
        ]);
    }

    protected function opciones(): array
    {
        return [
            'cuentas' => $this->catalogos->planCuentas(),
            'monedas' => $this->catalogos->monedas(),
            'siNo' => [['value' => 1, 'label' => 'Sí'], ['value' => 0, 'label' => 'No']],
            'siNoLetra' => [['value' => 'Y', 'label' => 'Sí'], ['value' => 'N', 'label' => 'No']],
            'saldos' => [['value' => 'D', 'label' => 'Deudor'], ['value' => 'A', 'label' => 'Acreedor']],
        ];
    }

    protected function despuesDeGuardar(int|string $id, array $data, bool $nuevo): void
    {
        $nuevo ? $this->replicador->alta((int) $id) : $this->replicador->edicion((int) $id);
    }

    protected function despuesDeEliminar(int|string $id): void
    {
        $this->replicador->baja((int) $id);
    }
}
