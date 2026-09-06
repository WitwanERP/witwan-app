<?php

namespace App\Http\Controllers\Web\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte / listado genérico (config-driven): filtros declarativos, una
 * consulta que devuelve filas, agrupación opcional (por moneda, como casi
 * todos los del CI), totales por columna numérica y export a CSV con el mismo
 * criterio.
 *
 * Es el equivalente de los controllers de `administracion/reportes` y
 * `operaciones` que sólo arman un SQL con filtros del query-string y lo
 * vuelcan a una tabla + PHPExcel, y de los listados Admin_Controller de
 * documentos (facturas, recibos, órdenes) en modo lectura. El front es un solo
 * componente: Reportes/Listado.
 *
 * Tipos de filtro: text, select, multi, rango (fechas desde/hasta => campo y
 * campo_to, como el `range=>true` del legacy), date, bool (1/0).
 * Tipos de columna: text (default), num (alineada y totalizable), pre (respeta
 * saltos), acciones (lista de links {label, href, target?} en fila['acciones']).
 */
abstract class ReporteController extends Controller
{
    protected string $titulo;

    /** Slug bajo /app (ej. 'admin/reportes/ventas-netas'). */
    protected string $ruta;

    protected string $area = 'Administración';

    protected string $grupo = 'Reportes';

    /** Campo por el que se agrupan las filas en tablas separadas (null = una sola). */
    protected ?string $agruparPor = 'moneda';

    /** El CI sólo consulta si vino algún filtro (QUERY_STRING != ''); false = consulta siempre. */
    protected bool $requiereFiltros = true;

    /** Texto de ayuda arriba de los filtros. */
    protected string $ayuda = '';

    /** Máximo de filas que devuelve consultar() (0 = sin límite). El front avisa si se alcanzó. */
    protected int $limite = 0;

    /**
     * @return list<array{campo:string,label:string,tipo:string,opciones?:list<array{value:mixed,label:string}>,default?:mixed}>
     */
    abstract protected function filtros(): array;

    /**
     * @return list<array{campo:string,label:string,tipo?:string,total?:bool}>
     */
    abstract protected function columnas(): array;

    /**
     * Ejecuta la consulta con los filtros normalizados y devuelve las filas
     * (arrays asociativos con las claves de columnas()).
     *
     * @param  array<string,mixed>  $f
     * @return list<array<string,mixed>>
     */
    abstract protected function consultar(array $f): array;

    /** Links globales del encabezado (ej. "Nueva factura" al legacy): [{label, href, target?}]. */
    protected function accionesGlobales(): array
    {
        return [];
    }

    /** Hook para leer parámetros de ruta ({area}) antes de armar el reporte. */
    protected function preparar(Request $request): void {}

    public function index(Request $request): Response
    {
        $this->preparar($request);
        $filtros = $this->leerFiltros($request);
        $consulta = ! $this->requiereFiltros || $this->hayFiltros($request);
        $filas = $consulta ? $this->consultar($filtros) : [];

        return Inertia::render('Reportes/Listado', [
            'config' => [
                'titulo' => $this->titulo,
                'area' => $this->area,
                'grupo' => $this->grupo,
                'baseUrl' => "/app/{$this->ruta}",
                'ayuda' => $this->ayuda,
                'filtros' => $this->filtros(),
                'columnas' => $this->columnas(),
                'agruparPor' => $this->agruparPor,
                'consultado' => $consulta,
                'limite' => $this->limite,
                'acciones' => $this->accionesGlobales(),
            ],
            'filtros' => $filtros,
            'grupos' => $this->agrupar($filas),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->preparar($request);
        $filas = $this->consultar($this->leerFiltros($request));
        $columnas = array_values(array_filter($this->columnas(), fn ($c) => ($c['tipo'] ?? '') !== 'acciones'));
        $nombre = str_replace(['/', ' '], '-', mb_strtolower($this->titulo)).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filas, $columnas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel abra UTF-8
            foreach ($this->agrupar($filas) as $grupo) {
                if ($grupo['clave'] !== null) {
                    fputcsv($out, [$grupo['clave']], ';');
                }
                fputcsv($out, array_column($columnas, 'label'), ';');
                foreach ($grupo['filas'] as $fila) {
                    fputcsv($out, array_map(fn ($c) => $fila[$c['campo']] ?? '', $columnas), ';');
                }
                if ($grupo['totales'] !== []) {
                    fputcsv($out, array_map(fn ($c) => $grupo['totales'][$c['campo']] ?? ($c === $columnas[0] ? 'TOTAL' : ''), $columnas), ';');
                }
                fputcsv($out, [], ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Agrupa por $agruparPor y calcula totales de las columnas con total=>true.
     *
     * @return list<array{clave:?string,filas:list<array>,totales:array<string,float>}>
     */
    protected function agrupar(array $filas): array
    {
        $totalizables = array_column(array_filter($this->columnas(), fn ($c) => ! empty($c['total'])), 'campo');
        $grupos = [];

        foreach ($filas as $fila) {
            $clave = $this->agruparPor !== null ? (string) ($fila[$this->agruparPor] ?? '') : '';
            $grupos[$clave] ??= ['clave' => $this->agruparPor !== null ? $clave : null, 'filas' => [], 'totales' => array_fill_keys($totalizables, 0.0)];
            $grupos[$clave]['filas'][] = $fila;
            foreach ($totalizables as $c) {
                $grupos[$clave]['totales'][$c] += (float) ($fila[$c] ?? 0);
            }
        }

        foreach ($grupos as &$g) {
            $g['totales'] = array_map(fn ($v) => round($v, 2), $g['totales']);
        }

        return array_values($grupos);
    }

    /** Normaliza los filtros del query-string según su definición. */
    protected function leerFiltros(Request $request): array
    {
        $out = [];
        foreach ($this->filtros() as $f) {
            $campo = $f['campo'];
            if ($f['tipo'] === 'rango') {
                $out[$campo] = $this->fecha($request->get($campo));
                $out["{$campo}_to"] = $this->fecha($request->get("{$campo}_to"));

                continue;
            }
            if ($f['tipo'] === 'date') {
                $out[$campo] = $this->fecha($request->get($campo));

                continue;
            }
            $valor = $request->get($campo, $f['default'] ?? '');
            $out[$campo] = is_array($valor) ? array_values(array_filter($valor, fn ($v) => $v !== '')) : trim((string) $valor);
        }

        return $out;
    }

    protected function hayFiltros(Request $request): bool
    {
        foreach ($this->leerFiltros($request) as $v) {
            if ($v !== '' && $v !== null && $v !== []) {
                return true;
            }
        }

        return (string) $request->get('buscar', '') === '1';
    }

    /** Acepta ISO (input date) y dd/mm/yyyy (links viejos del CI); devuelve ISO o ''. */
    protected function fecha(mixed $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
    }

    /** dd/mm/yyyy para mostrar (como dbtocal() del CI). Acepta datetime. */
    protected function dmy(?string $fecha): string
    {
        if ($fecha === null || $fecha === '' || str_starts_with($fecha, '0000')) {
            return '';
        }

        return substr($fecha, 8, 2).'/'.substr($fecha, 5, 2).'/'.substr($fecha, 0, 4);
    }

    /** Aplica un filtro de rango de fechas (desde/hasta, cualquiera opcional) a un query builder. */
    protected function rango($query, string $columna, array $f, string $campo, bool $conHora = false): void
    {
        if (($f[$campo] ?? '') !== '') {
            $query->where($columna, '>=', $f[$campo].($conHora ? ' 00:00:00' : ''));
        }
        if (($f["{$campo}_to"] ?? '') !== '') {
            $query->where($columna, '<=', $f["{$campo}_to"].($conHora ? ' 23:59:59' : ''));
        }
    }

    /** Aplica el límite configurado al query builder. */
    protected function limitar($query): void
    {
        if ($this->limite > 0) {
            $query->limit($this->limite);
        }
    }
}
