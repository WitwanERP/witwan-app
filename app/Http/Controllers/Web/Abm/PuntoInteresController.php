<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Configuración > Geo > Puntos de interés (CI: configuracion/puntos). Son las
 * filas de `ciudad` con fk_ciudad_id != 0 (zonas/barrios/aeropuertos que
 * cuelgan de una ciudad principal). El país se hereda de la ciudad relacionada.
 */
class PuntoInteresController extends AbmController
{
    protected string $tabla = 'ciudad';

    protected string $pk = 'ciudad_id';

    protected string $ruta = 'config/puntos-interes';

    protected string $titulo = 'Puntos de interés';

    protected string $singular = 'Punto de interés';

    protected array $columnasListado = [
        ['campo' => 'ciudad_id', 'label' => 'ID'],
        ['campo' => 'ciudad_nombre', 'label' => 'Nombre'],
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad relacionada', 'opciones' => 'ciudades'],
    ];

    protected array $filtrosLike = ['ciudad_nombre'];

    protected array $filtrosSelect = [
        ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad relacionada', 'opciones' => 'ciudades'],
    ];

    protected string $sortDefault = 'ciudad_nombre';

    public function __construct(private CatalogosService $catalogos) {}

    protected function filtrarListado(Builder $query, Request $request): void
    {
        $query->where('fk_ciudad_id', '<>', 0);
    }

    protected function campos(): array
    {
        return [
            ['campo' => 'ciudad_nombre', 'label' => 'Nombre', 'tipo' => 'text', 'required' => true, 'max' => 100],
            ['campo' => 'fk_ciudad_id', 'label' => 'Ciudad relacionada', 'tipo' => 'select', 'required' => true, 'opciones' => 'ciudades'],
        ];
    }

    protected function antesDeGuardar(array $data, int|string|null $id): array
    {
        // El punto hereda el país de su ciudad principal (el CI lo dejaba en 0).
        $padre = DB::table('ciudad')->where('ciudad_id', (int) ($data['fk_ciudad_id'] ?? 0))->first(['fk_pais_id']);
        if ($padre) {
            $data['fk_pais_id'] = (int) $padre->fk_pais_id;
        }
        if ($id === null) {
            $data['ciudad_activo'] = 1;
        }

        return $data;
    }

    protected function opciones(): array
    {
        return ['ciudades' => $this->catalogos->ciudades()];
    }
}
