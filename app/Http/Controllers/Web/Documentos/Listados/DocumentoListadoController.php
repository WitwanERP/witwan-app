<?php

namespace App\Http\Controllers\Web\Documentos\Listados;

use App\Helpers\SysconfigHelper;
use App\Http\Controllers\Web\Reportes\ReporteController;
use App\Services\CatalogosService;
use App\Support\Licencia;

/**
 * Base de los listados de documentos (facturas, notas, recibos, órdenes) en
 * modo lectura: réplica de los Admin_Controller del CI con su `_query` y sus
 * filtros, con las acciones (imprimir, anular, editar, generar NC…) como links
 * al legacy hasta que cada flujo se porte.
 *
 * Sin filtros se listan los últimos 3 meses y se corta en $limite filas: el
 * CI paginaba de a 20 sobre tablas de decenas de miles de documentos.
 */
abstract class DocumentoListadoController extends ReporteController
{
    protected string $grupo = 'Documentos';

    protected ?string $agruparPor = null;

    protected bool $requiereFiltros = false;

    protected int $limite = 500;

    public function __construct(protected CatalogosService $catalogos) {}

    /** Coeficiente de IVA general (tasageneral como 21 o 0.21), igual que `$this->coef` del CI. */
    protected function coef(): float
    {
        $tasa = (float) SysconfigHelper::get('tasageneral', 21);

        return 1 + ($tasa < 1 ? $tasa : $tasa / 100);
    }

    /** Decimales del total: 0 en CL (salvo mviajes), 2 en el resto. */
    protected function decimales(): int
    {
        if (Licencia::pais() === 'CL') {
            return Licencia::es('witwan_mviajes') ? 2 : 0;
        }

        return 2;
    }

    /** Rango por defecto (últimos 3 meses) cuando no vino ninguna fecha. */
    protected function rangoPorDefecto(array $f, string $campo): array
    {
        if ($f[$campo] === '' && $f["{$campo}_to"] === '' && ! $this->hayOtroFiltro($f, $campo)) {
            $f[$campo] = now()->subMonths(3)->toDateString();
        }

        return $f;
    }

    private function hayOtroFiltro(array $f, string $campo): bool
    {
        foreach ($f as $k => $v) {
            if ($k !== $campo && $k !== "{$campo}_to" && $v !== '' && $v !== []) {
                return true;
            }
        }

        return false;
    }

    protected function link(string $label, string $href, bool $peligro = false): array
    {
        return ['label' => $label, 'href' => $href, 'peligro' => $peligro];
    }
}
