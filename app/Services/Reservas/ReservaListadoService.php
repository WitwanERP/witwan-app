<?php

namespace App\Services\Reservas;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Núcleo del listado de reservas: arma la query base con los JOIN del legacy,
 * aplica filtros (ReservaFiltroBuilder) y scoping (ReservaScopeService), pagina
 * de a 20 (orden por reserva_id DESC) y mapea cada fila a sus campos calculados
 * (ReservaFilaCalculador), acumulando los totales por moneda de la página.
 *
 * Réplica de reserva_model.php::listar() (modo _eslista). No depende de Request
 * ni de Inertia, así puede reutilizarlo también la API JSON.
 */
class ReservaListadoService
{
    public function __construct(
        private ReservaFiltroBuilder $filtros,
        private ReservaScopeService $scope,
        private ReservaFilaCalculador $calculador,
    ) {}

    /**
     * @param  array<string,mixed>  $filtros  filtros HTTP normalizados (incluye 'rsv' = idsistema)
     * @return array{registros:LengthAwarePaginator,totales:array<string,array{total:float,cant:int,saldo:float}>}
     */
    public function listar(array $filtros, ?User $usuario, int $perPage = 20): array
    {
        $query = $this->baseQuery($filtros);

        $this->filtros->aplicar($query, $filtros);
        $this->scope->aplicar($query, $usuario);

        if (($filtros['tipofecha'] ?? '') === 'canceladas') {
            $query->addSelect('historialfile.historial_date');
        }

        $query->groupBy('reserva.reserva_id')->orderByDesc('reserva.reserva_id');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $filas = $this->calculador->calcular($paginator->getCollection());
        $totales = $this->acumularTotales($filas);

        $paginator->setCollection(collect($filas));

        return ['registros' => $paginator, 'totales' => $totales];
    }

    /** Igual que listar() pero sin paginar (para export). Devuelve filas calculadas + totales. */
    public function todos(array $filtros, ?User $usuario): array
    {
        $query = $this->baseQuery($filtros);
        $this->filtros->aplicar($query, $filtros);
        $this->scope->aplicar($query, $usuario);

        if (($filtros['tipofecha'] ?? '') === 'canceladas') {
            $query->addSelect('historialfile.historial_date');
        }

        $query->groupBy('reserva.reserva_id')->orderByDesc('reserva.reserva_id');

        $filas = $this->calculador->calcular($query->get());

        return ['registros' => $filas, 'totales' => $this->acumularTotales($filas)];
    }

    /** Query base con los JOIN obligatorios del legacy (listar 105-454). */
    private function baseQuery(array $filtros): Builder
    {
        return Reserva::query()
            ->leftJoin('servicio', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->leftJoin('cliente', 'reserva.fk_cliente_id', '=', 'cliente.cliente_id')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'reserva.fk_usuario_id')
            ->leftJoin('rel_filefactura', 'rel_filefactura.fk_file_id', '=', 'reserva.reserva_id')
            ->leftJoin('factura', 'factura.factura_id', '=', 'rel_filefactura.fk_factura_id')
            ->leftJoin('usuariocomision', 'usuariocomision.fk_file_id', '=', 'reserva.reserva_id')
            ->leftJoin('negocio', 'negocio.negocio_id', '=', 'reserva.fk_negocio_id')
            ->select('reserva.*')
            ->addSelect('cliente.cliente_id')
            ->addSelect('cliente.cliente_nombre')
            ->addSelect('negocio.negocio_nombre')
            ->addSelect('usuariocomision.usuariocomision_id')
            ->selectRaw("CONCAT(usuario.usuario_apellido, ', ', usuario.usuario_nombre) AS usuario");
    }

    /** Totales por moneda sobre las filas de la página (lista.php:461-463). */
    private function acumularTotales(array $filas): array
    {
        $totales = [];
        foreach ($filas as $fila) {
            $m = $fila['moneda'] ?: '?';
            if (! isset($totales[$m])) {
                $totales[$m] = ['total' => 0.0, 'cant' => 0, 'saldo' => 0.0];
            }
            $totales[$m]['total'] += (float) $fila['total'];
            $totales[$m]['cant'] += 1;
            $totales[$m]['saldo'] += (float) $fila['saldo'];
        }

        return $totales;
    }
}
