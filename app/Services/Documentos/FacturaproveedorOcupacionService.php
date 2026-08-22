<?php

namespace App\Services\Documentos;

use App\Models\Servicio;
use App\Support\Contable\SemanaBsp;
use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicios de reserva que se imputan a una factura de tercero ("ocupaciones").
 *
 * Port de `ajax.php::servicioproveedor()` (application/controllers/ajax.php:240-420),
 * que devolvía HTML armado en PHP; acá devuelve datos y el front los pinta.
 *
 * Dos correcciones sobre el legacy, que el original resolvía con una consulta por
 * servicio dentro del loop: el monto ya facturado y la fecha de emisión del PNR
 * ahora salen por JOIN. Con proveedores de muchos servicios eso eran cientos de
 * queries por request.
 */
class FacturaproveedorOcupacionService
{
    /**
     * Servicios pendientes de facturar de un proveedor.
     *
     * @param  string|null  $codigo  Código de reserva. Si viene, se ignora la
     *                               ventana de antigüedad (el usuario sabe lo que busca).
     * @return array{modo: string, grupos: list<array>}
     */
    public function pendientesDe(int $proveedorId, ?string $codigo = null): array
    {
        $servicios = $this->consultar($proveedorId, $codigo);

        if (! ProveedorBsp::es($proveedorId)) {
            return [
                'modo' => 'plano',
                'grupos' => [[
                    'clave' => null,
                    'label' => null,
                    'cantidad' => $servicios->count(),
                    'servicios' => $servicios->values()->all(),
                ]],
            ];
        }

        // BSP: los boletos se agrupan por semana de emisión del PNR.
        $grupos = $servicios
            ->groupBy(fn ($s) => SemanaBsp::desde($s['fechaEmision']) ?? '')
            ->sortKeys()
            ->map(fn ($items, $clave) => [
                'clave' => $clave === '' ? null : $clave,
                'label' => SemanaBsp::etiqueta($clave === '' ? null : $clave),
                'cantidad' => $items->count(),
                'servicios' => $items->values()->all(),
            ])
            ->values()
            ->all();

        return ['modo' => 'bsp', 'grupos' => $grupos];
    }

    /**
     * Vincula servicios existentes a la factura y confirma los que estaban
     * pedidos (factura3ero.php:1020-1037).
     *
     * @param  list<array{id: int, monto: float}>  $ocupaciones
     */
    public function imputar(int $facturaId, array $ocupaciones, ?string $conexion = null): void
    {
        $db = $conexion ? DB::connection($conexion) : DB::connection();

        foreach ($ocupaciones as $ocupacion) {
            $id = (int) ($ocupacion['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $db->table('rel_facturaproveedorocupacion')->insertOrIgnore([
                'fk_facturaproveedor_id' => $facturaId,
                'fk_ocupacion_id' => $id,
                'monto' => (float) ($ocupacion['monto'] ?? 0),
            ]);

            $db->table('servicio')
                ->where('servicio_id', $id)
                ->where('status', 'RQ')
                ->update(['status' => 'CO']);
        }
    }

    /**
     * Servicio contable sintético para las facturas que no imputan servicios
     * reales: cuelga de la reserva 1 con tipo de producto ADM
     * (factura3ero.php:998-1016).
     */
    public function crearServicioAdministrativo(array $datos, MontosFactura $m, int $facturaId, bool $esNotaCredito): int
    {
        // En notas de crédito el costo va en negativo.
        $costo = $esNotaCredito ? abs($m->montoperc) * -1 : $m->montoperc;
        $iva = $esNotaCredito ? abs($m->soloiva) * -1 : $m->soloiva;

        $fechaContable = $datos['fechacontable'] ?? $datos['fecha'];

        $servicio = Servicio::create([
            'fk_proveedor_id' => (int) $datos['fk_proveedor_id'],
            'servicio_nombre' => 'Factura '.$datos['facturaproveedor_nro'],
            'fk_tipoproducto_id' => 'ADM',
            'fk_reserva_id' => 1,
            'status' => 'CO',
            'vigencia_ini' => $fechaContable,
            'vigencia_fin' => $fechaContable,
            'vencimiento_proveedor' => ! empty($datos['vencimiento']) ? $datos['vencimiento'] : null,
            'fk_moneda_id' => $datos['fk_moneda_id'],
            'moneda_costo' => $datos['fk_moneda_id'],
            'costo' => $costo,
            'iva_costo' => $iva,
        ]);

        DB::table('rel_facturaproveedorocupacion')->insertOrIgnore([
            'fk_facturaproveedor_id' => $facturaId,
            'fk_ocupacion_id' => $servicio->servicio_id,
            'monto' => 0,
        ]);

        return (int) $servicio->servicio_id;
    }

    /** Servicios ya imputados a una factura, para la vista de detalle. */
    public function serviciosDeFactura(int $facturaId): array
    {
        return DB::table('servicio')
            ->join('rel_facturaproveedorocupacion', 'rel_facturaproveedorocupacion.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->leftJoin('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->where('rel_facturaproveedorocupacion.fk_facturaproveedor_id', $facturaId)
            ->groupBy('servicio.servicio_id')
            ->select([
                'servicio.servicio_id',
                'servicio.servicio_nombre',
                'servicio.nro_confirmacion',
                'servicio.vigencia_ini',
                'servicio.moneda_costo',
                'servicio.costo',
                'servicio.iva_costo',
                'reserva.codigo',
                'rel_facturaproveedorocupacion.monto',
            ])
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->servicio_id,
                'nombre' => $s->servicio_nombre,
                'nroConfirmacion' => $s->nro_confirmacion,
                'codigo' => $s->codigo,
                'vigenciaIni' => $this->iso($s->vigencia_ini),
                'moneda' => $s->moneda_costo,
                'costo' => (float) $s->costo,
                'ivaCosto' => (float) $s->iva_costo,
                'monto' => (float) $s->monto,
            ])
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function consultar(int $proveedorId, ?string $codigo)
    {
        $costofinal = "(servicio.costo + servicio.iva_costo + IF(servicio.fk_tipoproducto_id <> 'AER', servicio.impuestos, 0))";

        $cargado = DB::table('rel_facturaproveedorocupacion')
            ->selectRaw('fk_ocupacion_id, SUM(monto) AS montocargado')
            ->groupBy('fk_ocupacion_id');

        $q = DB::table('servicio')
            ->join('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id')
            ->leftJoin('producto', 'producto.producto_id', '=', 'servicio.fk_producto_id')
            ->leftJoinSub($cargado, 'mc', 'mc.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->leftJoin('pnraereo', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id')
            ->where('servicio.fk_proveedor_id', $proveedorId)
            ->where('servicio.status', '<>', 'CA')
            ->where('reserva.fk_filestatus_id', '<>', 'CA')
            ->where('reserva.fk_filestatus_id', '<>', 'PG')
            ->where('servicio.fk_tipoproducto_id', '<>', 'CRE')
            ->where('servicio.fk_reserva_id', '<>', 1)
            // Descarta lo ya facturado por completo.
            ->whereRaw("ROUND(COALESCE(mc.montocargado, 0), 2) <> {$costofinal}")
            ->groupBy('servicio.servicio_id')
            ->orderBy('reserva.reserva_id')
            ->orderByDesc('servicio.vigencia_ini')
            ->selectRaw("
                servicio.servicio_id,
                IF(servicio.fk_producto_id <> 0, producto.producto_nombre, servicio.servicio_nombre) AS nombre,
                servicio.vigencia_ini,
                servicio.nro_confirmacion,
                servicio.moneda_costo,
                servicio.cotcosto,
                servicio.vencimiento_proveedor,
                {$costofinal} AS costofinal,
                COALESCE(mc.montocargado, 0) AS montocargado,
                MIN(pnraereo.pnraereo_fechaemision) AS fechaemision,
                CONCAT(reserva.tipocodigo, '-', reserva.codigo) AS codigo,
                CONCAT(reserva.titular_apellido, ', ', reserva.titular_nombre) AS titular
            ");

        if ($codigo !== null && $codigo !== '') {
            // Con código explícito el legacy no acota por antigüedad.
            $q->where('reserva.codigo', 'like', $codigo);
        } else {
            $limite = $this->fechaLimite();
            $q->where(function ($w) use ($limite) {
                $w->where('servicio.vigencia_fin', '>=', $limite)
                    ->orWhere(function ($w2) {
                        $w2->where('servicio.vigencia_fin', '=', '0000-00-00')
                            ->where('servicio.vigencia_ini', '<>', '0000-00-00');
                    });
            });
        }

        return $q->get()->map(function ($s) {
            $costofinal = (float) $s->costofinal;
            $cargado = (float) $s->montocargado;

            return [
                'id' => (int) $s->servicio_id,
                'nombre' => $s->nombre,
                'nroConfirmacion' => $s->nro_confirmacion,
                'codigo' => $s->codigo,
                'titular' => $s->titular,
                'vigenciaIni' => $this->iso($s->vigencia_ini),
                'moneda' => $s->moneda_costo,
                'cotizacion' => (float) $s->cotcosto,
                'costoFinal' => $costofinal,
                'montoCargado' => $cargado,
                'montoSugerido' => round($costofinal - $cargado, 2),
                'fechaEmision' => $this->iso($s->fechaemision ?: $s->vencimiento_proveedor),
            ];
        });
    }

    /** Ventana de antigüedad configurable por licencia (ajax.php:246-253). */
    private function fechaLimite(): string
    {
        $ventanas = (array) config('facturaproveedor.ventana_servicios', []);
        $ventana = $ventanas[Licencia::base()] ?? ($ventanas['default'] ?? '-18 months');

        // Puede ser una fecha fija o un modificador relativo.
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $ventana)) {
            return $ventana;
        }

        return Carbon::now()->modify($ventana)->format('Y-m-d');
    }

    private function iso(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '' || str_starts_with($fecha, '0000')) {
            return null;
        }

        return substr($fecha, 0, 10);
    }
}
