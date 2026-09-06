<?php

namespace App\Http\Controllers\Web\Abm;

use App\Http\Controllers\Controller;
use App\Services\TablaLegacyService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ABM genérico (config-driven) sobre tablas legacy de CI. Cada entidad de
 * configuración (regiones, países, ciudades, bancos, feriados, etc.) extiende
 * esta clase definiendo su tabla, columnas de listado y campos de formulario;
 * el front se resuelve con dos componentes Inertia compartidos: Abm/Index y
 * Abm/Form.
 *
 * Es el equivalente del Admin_Controller del CI para los controllers que sólo
 * declaraban `$this->_fields`: acá `campos()` es esa declaración.
 *
 * Tipos de campo soportados: text, number, decimal, select, radio, checkbox,
 * textarea, date. Un select/radio toma sus opciones de `opciones()` (por clave)
 * o de una lista inline en 'opciones'; con 'dependeDe' filtra por el valor de
 * otro campo (las opciones llevan 'padre'). 'soloAlta' deja el campo de sólo
 * lectura en edición (PKs escritas por el usuario: moneda_id).
 */
abstract class AbmController extends Controller
{
    protected string $tabla;

    protected string $pk;

    /** La PK es numérica (auto_increment o MAX+1). false para moneda_id/tipousuario_id. */
    protected bool $pkNumerica = true;

    /** Slug bajo /app (ej. 'geo/regiones'). */
    protected string $ruta;

    protected string $titulo;

    protected string $singular;

    /** Área de la botonera a la que pertenece (breadcrumb): Configuración, Administración… */
    protected string $area = 'Configuración';

    /** Acciones habilitadas (réplica de `_actions` del CI): crear, editar, eliminar. */
    protected array $acciones = ['crear', 'editar', 'eliminar'];

    /** Registros por página (`_limit` del CI). */
    protected int $porPagina = 50;

    /**
     * Columnas del listado. Con 'opciones' => clave se muestra el label de la
     * opción en lugar del valor crudo; con 'tipo' => 'bool' se muestra Sí/No.
     *
     * @var list<array{campo:string,label:string,opciones?:string,tipo?:string}>
     */
    protected array $columnasListado = [];

    /** @var list<string> */
    protected array $filtrosLike = [];

    /**
     * Filtros de igualdad por select/FK (fieles a CI). Cada uno referencia una
     * clave de opciones().
     *
     * @var list<array{campo:string,label:string,opciones:string}>
     */
    protected array $filtrosSelect = [];

    protected string $sortDefault;

    /** Dirección del orden por defecto (asc|desc). */
    protected string $dirDefault = 'asc';

    /**
     * Definición de los campos del formulario.
     *
     * @return list<array{campo:string,label:string,tipo:string,required?:bool,max?:int,opciones?:string|array,ayuda?:string,default?:mixed,dependeDe?:string,soloAlta?:bool,regla?:string}>
     */
    abstract protected function campos(): array;

    /**
     * Opciones para los selects, indexadas por la clave que referencia cada
     * campo en 'opciones'. Cada opción: ['value'=>..,'label'=>..,'padre'=>..?].
     *
     * @return array<string,list<array{value:mixed,label:string,padre?:mixed}>>
     */
    protected function opciones(): array
    {
        return [];
    }

    /** Where fijos del listado (réplica de `_filters['where']` del CI). */
    protected function filtrarListado(Builder $query, Request $request): void {}

    /** Hook antes de insertar/actualizar: permite completar o transformar el payload. */
    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        return $data;
    }

    /** Hook después de insertar/actualizar (réplica de `_after_create`/`_after_edit`). */
    protected function despuesDeGuardar(int|string $id, array $data, bool $nuevo): void {}

    /** Hook después de eliminar (réplica de `after_delete`). */
    protected function despuesDeEliminar(int|string $id): void {}

    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $cols = array_values(array_unique(array_merge([$this->pk], array_column($this->columnasListado, 'campo'))));

        $registros = $svc->listar(
            $this->tabla,
            $cols,
            $this->filtrosLike,
            array_merge(['dir' => $this->dirDefault], $request->all()),
            $this->pk,
            $this->sortDefault,
            $this->porPagina,
            array_column($this->filtrosSelect, 'campo'),
            fn (Builder $q) => $this->filtrarListado($q, $request),
        );

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $this->presentar($registros),
            'filtros' => $request->only(array_merge($this->filtrosLike, array_column($this->filtrosSelect, 'campo'), [$this->pk])),
        ]);
    }

    public function create(): Response
    {
        abort_unless(in_array('crear', $this->acciones, true), 404);

        return Inertia::render('Abm/Form', ['config' => $this->config(true)]);
    }

    public function store(Request $request, TablaLegacyService $svc): RedirectResponse
    {
        abort_unless(in_array('crear', $this->acciones, true), 404);

        $data = $this->antesDeGuardar($request->validate($this->reglas()), null);
        $id = $svc->insertar($this->tabla, $data);
        $this->despuesDeGuardar($id, $data, true);

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} creado correctamente.");
    }

    public function edit(string $id, TablaLegacyService $svc): Response
    {
        abort_unless(in_array('editar', $this->acciones, true), 404);

        $registro = $svc->paraEditar($this->tabla, $this->pk, $this->id($id));

        abort_if($registro === null, 404);

        return Inertia::render('Abm/Form', [
            'config' => $this->config(true),
            'registro' => $registro,
        ]);
    }

    public function update(Request $request, string $id, TablaLegacyService $svc): RedirectResponse
    {
        abort_unless(in_array('editar', $this->acciones, true), 404);

        $id = $this->id($id);
        $data = $this->antesDeGuardar($request->validate($this->reglas(true)), $id);
        $svc->actualizar($this->tabla, $this->pk, $id, $data);
        $this->despuesDeGuardar($id, $data, false);

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} actualizado correctamente.");
    }

    public function destroy(string $id, TablaLegacyService $svc): RedirectResponse
    {
        abort_unless(in_array('eliminar', $this->acciones, true), 404);

        $id = $this->id($id);
        $svc->eliminar($this->tabla, $this->pk, $id);
        $this->despuesDeEliminar($id);

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} eliminado correctamente.");
    }

    /** Normaliza el id de la ruta según el tipo de PK. */
    protected function id(string $id): int|string
    {
        if ($this->pkNumerica) {
            abort_unless(ctype_digit($id), 404);

            return (int) $id;
        }

        return $id;
    }

    /**
     * Post-procesa las filas del listado: labels de opciones y booleanos, para
     * que el Vue no tenga que conocer los catálogos.
     */
    protected function presentar($registros)
    {
        $conOpciones = array_filter($this->columnasListado, fn ($c) => isset($c['opciones']) || ($c['tipo'] ?? '') === 'bool');
        if ($conOpciones === []) {
            return $registros;
        }

        $todas = $this->opciones();
        $mapas = [];
        foreach ($conOpciones as $c) {
            if (isset($c['opciones'])) {
                $mapas[$c['campo']] = collect($todas[$c['opciones']] ?? [])->keyBy(fn ($o) => (string) $o['value'])->map(fn ($o) => $o['label'])->all();
            }
        }

        return $registros->through(function ($fila) use ($conOpciones, $mapas) {
            $fila = (array) $fila;
            foreach ($conOpciones as $c) {
                $campo = $c['campo'];
                $valor = $fila[$campo] ?? null;
                if (($c['tipo'] ?? '') === 'bool') {
                    $fila[$campo] = $valor === null || $valor === '' ? null : ((int) $valor === 1 || $valor === 'Y' || $valor === 'S' ? 'Sí' : 'No');
                } elseif (isset($mapas[$campo])) {
                    $fila[$campo] = $mapas[$campo][(string) $valor] ?? ($valor === null || (string) $valor === '' || (string) $valor === '0' ? null : $valor);
                }
            }

            return $fila;
        });
    }

    /** Config que consumen los componentes Inertia (incluye opciones solo en el form). */
    protected function config(bool $conOpciones = false): array
    {
        // Para los filtros select del listado solo necesitamos sus opciones.
        $opcionesFiltro = [];
        if ($this->filtrosSelect !== []) {
            $todas = $this->opciones();
            foreach ($this->filtrosSelect as $f) {
                $opcionesFiltro[$f['opciones']] = $todas[$f['opciones']] ?? [];
            }
        }

        return [
            'titulo' => $this->titulo,
            'singular' => $this->singular,
            'area' => $this->area,
            'baseUrl' => "/app/{$this->ruta}",
            'pk' => $this->pk,
            'acciones' => array_values($this->acciones),
            'columnas' => array_map(fn ($c) => ['campo' => $c['campo'], 'label' => $c['label']], $this->columnasListado),
            'filtrosLike' => $this->filtrosLike,
            'filtrosSelect' => $this->filtrosSelect,
            'opcionesFiltro' => $opcionesFiltro,
            'campos' => $conOpciones ? $this->campos() : [],
            'opciones' => $conOpciones ? $this->opciones() : [],
        ];
    }

    /** Reglas de validación derivadas de la definición de campos. */
    protected function reglas(bool $edicion = false): array
    {
        $reglas = [];

        foreach ($this->campos() as $c) {
            // Campos de sólo alta (PK escrita por el usuario) no se validan ni pisan al editar.
            if ($edicion && ! empty($c['soloAlta'])) {
                continue;
            }

            if (isset($c['regla'])) {
                $reglas[$c['campo']] = $c['regla'];

                continue;
            }

            $base = ! empty($c['required']) ? 'required' : 'nullable';
            $tipo = match ($c['tipo']) {
                'number' => 'integer',
                'decimal' => 'numeric',
                'checkbox' => 'integer|in:0,1',
                'date' => 'date',
                // Los selects/radios pueden ser ids numéricos o códigos ('HOT', 'ARS'): sin regla de tipo.
                'select', 'radio' => '',
                default => 'string'.(isset($c['max']) ? '|max:'.$c['max'] : ''),
            };

            $reglas[$c['campo']] = rtrim("$base|$tipo", '|');
        }

        return $reglas;
    }
}
