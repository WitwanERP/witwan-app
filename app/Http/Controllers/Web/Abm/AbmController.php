<?php

namespace App\Http\Controllers\Web\Abm;

use App\Http\Controllers\Controller;
use App\Services\TablaLegacyService;
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
 * Tipos de campo soportados: text, number, select, checkbox, textarea, date.
 */
abstract class AbmController extends Controller
{
    protected string $tabla;
    protected string $pk;
    /** Slug bajo /app (ej. 'geo/regiones'). */
    protected string $ruta;
    protected string $titulo;
    protected string $singular;

    /** @var list<array{campo:string,label:string}> */
    protected array $columnasListado = [];
    /** @var list<string> */
    protected array $filtrosLike = [];
    protected string $sortDefault;

    /**
     * Definición de los campos del formulario.
     *
     * @return list<array{campo:string,label:string,tipo:string,required?:bool,max?:int,opciones?:string,ayuda?:string}>
     */
    abstract protected function campos(): array;

    /**
     * Opciones para los selects, indexadas por la clave que referencia cada
     * campo en 'opciones'. Cada opción: ['value'=>..,'label'=>..].
     *
     * @return array<string,list<array{value:mixed,label:string}>>
     */
    protected function opciones(): array
    {
        return [];
    }

    public function index(Request $request, TablaLegacyService $svc): Response
    {
        $cols = array_values(array_unique(array_merge([$this->pk], array_column($this->columnasListado, 'campo'))));

        $registros = $svc->listar(
            $this->tabla,
            $cols,
            $this->filtrosLike,
            $request->all(),
            $this->pk,
            $this->sortDefault,
        );

        return Inertia::render('Abm/Index', [
            'config' => $this->config(),
            'registros' => $registros,
            'filtros' => $request->only(array_merge($this->filtrosLike, [$this->pk])),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Abm/Form', ['config' => $this->config(true)]);
    }

    public function store(Request $request, TablaLegacyService $svc): RedirectResponse
    {
        $svc->insertar($this->tabla, $request->validate($this->reglas()));

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} creado correctamente.");
    }

    public function edit(int $id, TablaLegacyService $svc): Response
    {
        $registro = $svc->paraEditar($this->tabla, $this->pk, $id);

        abort_if($registro === null, 404);

        return Inertia::render('Abm/Form', [
            'config' => $this->config(true),
            'registro' => $registro,
        ]);
    }

    public function update(Request $request, int $id, TablaLegacyService $svc): RedirectResponse
    {
        $svc->actualizar($this->tabla, $this->pk, $id, $request->validate($this->reglas()));

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} actualizado correctamente.");
    }

    public function destroy(int $id, TablaLegacyService $svc): RedirectResponse
    {
        $svc->eliminar($this->tabla, $this->pk, $id);

        return redirect("/app/{$this->ruta}")->with('success', "{$this->singular} eliminado correctamente.");
    }

    /** Config que consumen los componentes Inertia (incluye opciones solo en el form). */
    protected function config(bool $conOpciones = false): array
    {
        return [
            'titulo' => $this->titulo,
            'singular' => $this->singular,
            'baseUrl' => "/app/{$this->ruta}",
            'pk' => $this->pk,
            'columnas' => $this->columnasListado,
            'filtrosLike' => $this->filtrosLike,
            'campos' => $conOpciones ? $this->campos() : [],
            'opciones' => $conOpciones ? $this->opciones() : [],
        ];
    }

    /** Reglas de validación derivadas de la definición de campos. */
    protected function reglas(): array
    {
        $reglas = [];

        foreach ($this->campos() as $c) {
            if (isset($c['regla'])) {
                $reglas[$c['campo']] = $c['regla'];
                continue;
            }

            $base = ! empty($c['required']) ? 'required' : 'nullable';
            $tipo = match ($c['tipo']) {
                'number' => 'integer',
                'checkbox' => 'integer|in:0,1',
                default => 'string'.(isset($c['max']) ? '|max:'.$c['max'] : ''),
            };

            $reglas[$c['campo']] = "$base|$tipo";
        }

        return $reglas;
    }
}
