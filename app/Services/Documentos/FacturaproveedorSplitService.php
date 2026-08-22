<?php

namespace App\Services\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Models\Facturaproveedor;
use App\Models\Servicio;
use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * División de la factura entre bases contables.
 *
 * En el caso general la factura se contabiliza entera en la base del tenant. La
 * licencia `witwan_secontur` la reparte según la imputación por área entre sus
 * bases hermanas (`witwan_secontur1..3`), creando en cada una una copia
 * prorrateada marcada como PARCIAL (factura3ero.php:1040-1114).
 *
 * OJO: acá la comparación es contra la licencia EXACTA, no contra la familia
 * secontur; el legacy usa `LICENCIA == 'witwan_secontur'` en este bloque.
 *
 * El resto del módulo no sabe que esto existe: siempre recibe una lista de
 * tramos y la recorre.
 */
class FacturaproveedorSplitService
{
    /**
     * @return list<TramoSplit>
     */
    public function plan(array $datos, MontosFactura $m, int $facturaId, int $usuarioId): array
    {
        $bases = $this->basesDeLaLicencia();
        $areas = $datos['areaimputacion'] ?? null;

        if ($bases === [] || ! is_array($areas) || $areas === []) {
            return [new TramoSplit(1.0, null, $facturaId)];
        }

        $tramos = [];

        foreach ($areas as $k => $porcentaje) {
            if ((float) $porcentaje === 0.0) {
                continue;
            }

            $proporcion = (float) $porcentaje / 100;
            $conexion = 'witwan_secontur'.$k;

            // El port original saltaba en silencio las bases no configuradas, con
            // lo que la factura se contabilizaba sólo en parte y nadie se
            // enteraba. Ahora es un error explícito.
            if (! $this->conexionExiste($conexion)) {
                throw FacturaproveedorException::baseDeSplitAusente($conexion);
            }

            $tramos[] = new TramoSplit(
                $proporcion,
                $conexion,
                $this->replicar($conexion, $datos, $m, $proporcion, $usuarioId),
            );
        }

        return $tramos === [] ? [new TramoSplit(1.0, null, $facturaId)] : $tramos;
    }

    /** Crea la copia prorrateada de la factura y su servicio en la base hermana. */
    private function replicar(string $conexion, array $datos, MontosFactura $m, float $v, int $usuarioId): int
    {
        $fecha = $datos['fecha'];
        $fechaContable = $datos['fechacontable'] ?? $fecha;
        $vencimiento = ! empty($datos['vencimiento']) ? $datos['vencimiento'] : null;

        $prorrateado = [];
        foreach ($m->columnasFactura() as $columna => $valor) {
            $prorrateado[$columna] = $valor * $v;
        }

        $factura = Facturaproveedor::on($conexion)->create(array_merge($prorrateado, [
            'facturaproveedor_nro' => $datos['facturaproveedor_nro'],
            'facturaproveedor_tipodocumento' => $datos['facturaproveedor_tipodocumento'],
            'facturaproveedor_tipofactura' => $datos['facturaproveedor_tipofactura'] ?? '',
            'fk_proveedor_id' => (int) $datos['fk_proveedor_id'],
            'fk_proyecto_id' => (int) ($datos['fk_proyecto_id'] ?? 0),
            'fk_plancuenta_id' => (int) ($datos['fk_plancuenta_id'] ?? 0),
            'fk_moneda_id' => $datos['fk_moneda_id'],
            'cotizacion' => (float) ($datos['cotizacion'] ?? 1),
            'fechacarga' => Carbon::today()->toDateString(),
            'fecha' => $fecha,
            'fechacontable' => $fechaContable,
            'vencimiento' => $vencimiento,
            'descripcion' => $datos['descripcion'] ?? '',
            'tipomovimiento' => $datos['tipomovimiento'],
            'imputacion' => 'PARCIAL',
            'fk_usuario_id' => $usuarioId,
        ]));

        $servicio = Servicio::on($conexion)->create([
            'fk_proveedor_id' => (int) $datos['fk_proveedor_id'],
            'servicio_nombre' => 'Factura '.$datos['facturaproveedor_nro'],
            'fk_tipoproducto_id' => 'ADM',
            'fk_reserva_id' => 1,
            'status' => 'CO',
            'vigencia_ini' => Carbon::today()->toDateString(),
            'vigencia_fin' => Carbon::today()->toDateString(),
            'vencimiento_proveedor' => $vencimiento,
            'fk_moneda_id' => $datos['fk_moneda_id'],
            'moneda_costo' => $datos['fk_moneda_id'],
            'costo' => $m->montoperc * $v,
            'iva_costo' => $m->soloiva * $v,
        ]);

        DB::connection($conexion)->table('rel_facturaproveedorocupacion')->insertOrIgnore([
            'fk_facturaproveedor_id' => $factura->facturaproveedor_id,
            'fk_ocupacion_id' => $servicio->servicio_id,
            'monto' => $m->montototal * $v,
        ]);

        return (int) $factura->facturaproveedor_id;
    }

    /** @return list<string> */
    private function basesDeLaLicencia(): array
    {
        $bases = config('facturaproveedor.split_bases.'.Licencia::base());

        return is_array($bases) ? $bases : [];
    }

    private function conexionExiste(string $conexion): bool
    {
        return ! empty(config("database.connections.{$conexion}"));
    }
}
