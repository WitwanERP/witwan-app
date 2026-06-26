<?php

namespace App\Services\Reservas;

use App\Support\Licencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aplica al query del listado de reservas TODAS las condiciones WHERE/JOIN de
 * reserva_model.php::listar() (líneas 98-404), fiel al legacy.
 *
 * El query base (reserva + JOIN servicio/cliente/usuario/rel_filefactura/factura/
 * usuariocomision) lo arma ReservaListadoService; acá se agregan los filtros y los
 * JOIN condicionales (pnraereo, servicio_nomina, historialfile).
 *
 * Mapa de claves de $filtros (nombres HTTP del front), equivalentes a CI:
 *   status, tipo (tipocodigo), codigo, cliente, responsable(→promotor), rsv(idsistema),
 *   proveedor, prestador, representante, cadenacliente, vendedor|fk_vendedor_id(→agente),
 *   negocio, operativo, usuario(→fk_usuario_id), ticket, recloc, nro_confirmacion,
 *   codigo_externo, residente, titular, solofacturado, solopagos, soloocultas,
 *   mostrarreprogramados, soloovencidas, tipoproducto(→servicio.fk_tipoproducto_id),
 *   pago, facturafrom, facturato, factura, from, to, tipofecha, fecha_alta, fecha_alta_to,
 *   auditado.
 */
class ReservaFiltroBuilder
{
    /** @var array<string,bool> joins condicionales ya agregados */
    private array $joins = [];

    public function aplicar(Builder $query, array $f): void
    {
        $this->joins = [];

        // --- Base obligatoria (listar 98-139) ---
        $query->where('reserva.fk_agrupado_id', 0);
        $query->where('reserva.reserva_id', '!=', 1);

        // --- ID puntual (listar 141-143; usado por el resumen) ---
        if (! empty($f['id']) && (int) $f['id'] !== 0) {
            $query->where('reserva.reserva_id', (int) $f['id']);
        }

        $tipofecha = (string) ($f['tipofecha'] ?? '');

        // --- Estado (status) y tipocodigo especial CM (listar 118-125) ---
        $status = $this->lista($f['status'] ?? null);
        if (! empty($status) && $status !== ['CM'] && $tipofecha !== 'canceladas') {
            $query->whereIn('reserva.fk_filestatus_id', $status);
        } elseif ($status === ['CM']) {
            $query->where('reserva.tipocodigo', 'CM');
        }
        if (! empty($status) && $status !== ['CM'] && $tipofecha === 'canceladas') {
            $query->whereIn('reserva.fk_filestatus_id', ['CA']);
        }

        // --- Cliente (listar 128) ---
        if (! empty($f['cliente']) && (int) $f['cliente'] !== 0) {
            $query->where('reserva.fk_cliente_id', (int) $f['cliente']);
        }

        // --- Código (múltiple con '*') (listar 132-136) ---
        if (! empty($f['codigo'])) {
            $codigos = array_map('trim', explode('*', (string) $f['codigo']));
            $query->whereIn('reserva.codigo', $codigos);
        }

        // --- Responsable (promotor) (listar 148) ---
        if (! empty($f['responsable']) && (int) $f['responsable'] !== 0) {
            $query->where('reserva.promotor', (int) $f['responsable']);
        }

        // --- Sistema/área (listar 153-155) ---
        $rsv = (int) ($f['rsv'] ?? 0);
        if ($rsv !== 0 && $rsv !== 10) {
            $query->where(function (Builder $q) use ($rsv) {
                $q->where(function (Builder $q2) use ($rsv) {
                    $q2->where('reserva.fk_sistema_id', $rsv)
                        ->where('reserva.fk_sistemaaplicacion_id', 0);
                })->orWhere('reserva.fk_sistemaaplicacion_id', $rsv);
            });
        }

        // --- Tipo (tipocodigo, dash) (listar 158-160) ---
        $tipo = $this->lista($f['tipo'] ?? null);
        if (! empty($tipo)) {
            $query->whereIn('reserva.tipocodigo', $tipo);
        }

        // --- Proveedor / prestador / representante (listar 161-171) ---
        if (! empty($f['proveedor']) && (int) $f['proveedor'] !== 0) {
            $query->where('servicio.fk_proveedor_id', (int) $f['proveedor'])
                ->where('servicio.status', '!=', 'CA');
        }
        if (! empty($f['prestador']) && (int) $f['prestador'] !== 0) {
            $query->where('servicio.fk_prestador_id', (int) $f['prestador'])
                ->where('servicio.status', '!=', 'CA');
        }
        if (! empty($f['representante']) && (int) $f['representante'] !== 0) {
            $query->where('cliente.nombre_representante', (string) (int) $f['representante']);
        }

        // --- auditado (solo si sysconfig.fileauditado==1) (listar 179-188) ---
        if ((int) Licencia::sysconfig('fileauditado', 0) === 1) {
            $aud = $f['auditado'] ?? null;
            $hayId = ! empty($f['id']);
            if ($aud !== null && (int) $aud === 1) {
                $query->where('reserva.auditado', 1);
            } elseif ($aud !== null && (int) $aud === 2) {
                // sin filtro (todos)
            } elseif ($aud === null && $hayId) {
                // sin filtro
            } else {
                $query->where('reserva.auditado', 0);
            }
        }

        // --- cadenacliente (listar 215) ---
        if (! empty($f['cadenacliente']) && (int) $f['cadenacliente'] !== 0) {
            $query->where('cliente.fk_cadenacliente_id', (int) $f['cadenacliente']);
        }

        // --- vendedor (agente) (listar 220-224) ---
        $vendedor = (int) ($f['vendedor'] ?? $f['fk_vendedor_id'] ?? 0);
        if ($vendedor !== 0) {
            $query->where('reserva.agente', $vendedor);
        }

        // --- negocio / operativo / usuario (listar 225-233) ---
        if (! empty($f['negocio']) && (int) $f['negocio'] !== 0) {
            $query->where('reserva.fk_negocio_id', (int) $f['negocio']);
        }
        if (! empty($f['operativo']) && (int) $f['operativo'] !== 0) {
            $query->where('reserva.operativo', (int) $f['operativo']);
        }
        if (! empty($f['usuario']) && (int) $f['usuario'] !== 0) {
            $query->where('reserva.fk_usuario_id', (int) $f['usuario']);
        }

        // --- ticket / recloc / nro_confirmacion / codigo_externo (listar 234-246) ---
        if (! empty($f['ticket'])) {
            $this->joinPnraereo($query);
            $query->where('pnraereo.pnraereo_tkt', (string) $f['ticket']);
        }
        if (! empty($f['recloc'])) {
            $recloc = (string) $f['recloc'];
            $query->whereIn('servicio.servicio_id', function ($q) use ($recloc) {
                $q->select('fk_ocupacion_id')->distinct()
                    ->from('pnraereo')->where('codigo_recloc', $recloc);
            });
        }
        if (! empty($f['nro_confirmacion'])) {
            $query->where('servicio.nro_confirmacion', 'LIKE', $f['nro_confirmacion'].'%');
        }
        if (! empty($f['codigo_externo'])) {
            $query->where('reserva.codigo_externo', 'LIKE', $f['codigo_externo'].'%');
        }

        // --- residente (tri-estado) (listar 247-252) ---
        if (isset($f['residente']) && $f['residente'] !== '') {
            $paisNombre = $this->paisLicencia();
            if ((int) $f['residente'] === 1) {
                $query->where('reserva.titular_email', $paisNombre);
            } elseif ((int) $f['residente'] === 0) {
                $query->where('reserva.titular_email', '!=', $paisNombre);
            }
        }

        // --- titular (listar 254-265) ---
        if (! empty($f['titular'])) {
            $titular = (string) $f['titular'];
            $this->joinServicioNomina($query);
            $query->where(function (Builder $q) use ($titular) {
                $q->where('reserva.titular_apellido', 'LIKE', "%{$titular}%");
                if (is_numeric($titular)) {
                    $q->orWhere('reserva.titular_nombre', 'LIKE', "%{$titular}%");
                }
                $q->orWhere('servicio_nomina.apellido', 'LIKE', "%{$titular}%");
            });
        }

        // --- solofacturado (bifurca por licencia) (listar 269-293) ---
        $this->solofacturado($query, $f);

        // --- solopagos (tri-estado) (listar 294-299) ---
        if (isset($f['solopagos']) && $f['solopagos'] !== '') {
            if ((int) $f['solopagos'] === 1) {
                $query->where('reserva.cobrado', '!=', 0);
            } elseif ((int) $f['solopagos'] === 0) {
                $query->where('reserva.cobrado', 0);
            }
        }

        // --- soloocultas / mostrarreprogramados / soloovencidas (listar 300-310) ---
        if (! empty($f['soloocultas']) && (int) $f['soloocultas'] !== 0) {
            $query->where('reserva.cerrada', 1);
        }
        if (isset($f['mostrarreprogramados']) && (int) $f['mostrarreprogramados'] === 1) {
            $query->where('reserva.mostrarreprogramados', 1);
        } elseif (isset($f['mostrarreprogramados']) && (int) $f['mostrarreprogramados'] === 2) {
            $query->where('reserva.mostrarreprogramados', 0);
        }
        if (! empty($f['soloovencidas']) && (int) $f['soloovencidas'] !== 0) {
            $query->whereRaw('reserva.fecha_vencimiento <= CURDATE()');
        }

        // --- tipoproducto (servicio) (listar 311-315) ---
        if (! empty($f['tipoproducto'])) {
            $query->where('servicio.fk_tipoproducto_id', (string) $f['tipoproducto']);
        }

        // --- pago (sin recibo) (listar 316-318) ---
        if (! empty($f['pago'])) {
            $query->where(function (Builder $q) {
                $q->whereNotIn('reserva.reserva_id', function ($sub) {
                    $sub->select('fk_file_id')->distinct()->from('rel_filerecibo');
                })->where('reserva.total', '>', 0);
            });
        }

        // --- factura: from/to/nro (listar 323-331) ---
        if (! empty($f['facturafrom'])) {
            $query->where('factura.factura_fecha', '>=', $this->fecha($f['facturafrom']).' 00:00:00')
                ->where('factura.statusfactura', '!=', 'AN')
                ->where('factura.statusfactura', '!=', 'NU');
        }
        if (! empty($f['facturato'])) {
            $query->where('factura.factura_fecha', '<=', $this->fecha($f['facturato']).' 23:59:59')
                ->where('factura.statusfactura', '!=', 'AN')
                ->where('factura.statusfactura', '!=', 'NU');
        }
        if (! empty($f['factura']) && (int) $f['factura'] !== 0) {
            $query->where('factura.factura_nro', 'LIKE', '%'.$f['factura'].'%');
        }

        // --- Rango de fechas según tipofecha (listar 333-401) ---
        $this->rangoFechas($query, $f, $tipofecha);

        // --- fecha_alta / fecha_alta_to explícitas (listar 394-401) ---
        if (! empty($f['fecha_alta_to'])) {
            $query->where('reserva.fecha_alta', '<=', $this->fecha($f['fecha_alta_to']));
        }
        if (! empty($f['fecha_alta'])) {
            $query->where('reserva.fecha_alta', '>=', $this->fecha($f['fecha_alta']));
        }
    }

    /** solofacturado 1/2/3 con la bifurcación por licencia de listar 269-293. */
    private function solofacturado(Builder $query, array $f): void
    {
        if (! isset($f['solofacturado']) || $f['solofacturado'] === '') {
            return;
        }
        $v = (int) $f['solofacturado'];

        if (Licencia::flag('facturado_med')) {
            if ($v === 1) {
                $query->whereIn('reserva.reserva_id', function ($q) {
                    $q->select('fk_file_id')->distinct()->from('factura')
                        ->where('statusfactura', '!=', 'AN')->where('statusfactura', '!=', 'NU');
                });
            } elseif ($v === 2) {
                $query->whereNotIn('reserva.reserva_id', function ($q) {
                    $q->select('fk_file_id')->distinct()->from('factura')
                        ->where('statusfactura', '!=', 'AN')->where('statusfactura', '!=', 'NU');
                });
            }

            return;
        }

        // Resto de licencias: lógica por servicio (rel_serviciofactura).
        $pendienteSub = "(SELECT s2.servicio_id FROM servicio s2 WHERE s2.status!='CA' AND s2.fk_reserva_id=servicio.fk_reserva_id AND (s2.total!=0 OR s2.costo!=0) AND s2.servicio_id NOT IN (SELECT DISTINCT(rsf.fk_servicio_id) FROM rel_serviciofactura rsf) LIMIT 1)";

        if ($v === 1) {
            $query->whereRaw("servicio.servicio_id IN (SELECT DISTINCT(fk_servicio_id) FROM rel_serviciofactura) AND ISNULL({$pendienteSub})");
        } elseif ($v === 3) {
            $query->whereRaw("servicio.servicio_id IN (SELECT DISTINCT(fk_servicio_id) FROM rel_serviciofactura) AND !ISNULL({$pendienteSub})");
        } elseif ($v === 2) {
            $corte = Licencia::flag('mybeds_corte_servicio', null);
            $corteSql = is_numeric($corte) ? " AND sfc.servicio_id > {$corte}" : '';
            $query->whereRaw("reserva.reserva_id IN (SELECT rfc.reserva_id FROM reserva rfc WHERE NOT EXISTS (SELECT 1 FROM servicio sfc JOIN serviciofactura sffc ON sfc.servicio_id = sffc.fk_servicio_id WHERE sfc.fk_reserva_id = rfc.reserva_id{$corteSql}) AND rfc.reserva_id NOT IN (SELECT DISTINCT(fk_file_id) FROM factura WHERE statusfactura!='AN' AND statusfactura!='NU'))");
        }
    }

    /** Rango de fechas según tipofecha: alta/vencimiento/checkin/checkout/emision/canceladas. */
    private function rangoFechas(Builder $query, array $f, string $tipofecha): void
    {
        if (! empty($f['from'])) {
            $from = $this->fecha($f['from']);
            match ($tipofecha) {
                'checkin' => $query->where('reserva.inicio', '>=', $from),
                'checkout' => $query->where('servicio.vigencia_fin', '>=', $from),
                'vencimiento' => $query->where('reserva.fecha_vencimiento', '>=', $from),
                'emision' => $this->joinPnraereo($query)->where('pnraereo.pnraereo_fechaemision', '>=', $from),
                'canceladas' => $this->joinHistorialfile($query)->where('historialfile.historial_date', '>=', $from),
                default => $query->where('reserva.fecha_alta', '>=', $from),
            };
        }

        if (! empty($f['to'])) {
            $to = $this->fecha($f['to']);
            match ($tipofecha) {
                'checkin' => $query->where('reserva.inicio', '<=', $to),
                'checkout' => $query->where('servicio.vigencia_fin', '<=', $to),
                'vencimiento' => $query->where('reserva.fecha_vencimiento', '<=', $to),
                'emision' => $query->where('pnraereo.pnraereo_fechaemision', '<=', $to),
                'canceladas' => $query->where('historialfile.historial_date', '<=', $to)
                    ->where('historialfile.historial_valor', 'CA')
                    ->where('historialfile.historial_campo', 'reserva.fk_filestatus_id'),
                default => $query->where('reserva.fecha_alta', '<=', $to),
            };
        }
    }

    private function joinPnraereo(Builder $query): Builder
    {
        if (empty($this->joins['pnraereo'])) {
            $query->leftJoin('pnraereo', 'pnraereo.fk_ocupacion_id', '=', 'servicio.servicio_id');
            $this->joins['pnraereo'] = true;
        }

        return $query;
    }

    private function joinServicioNomina(Builder $query): Builder
    {
        if (empty($this->joins['servicio_nomina'])) {
            $query->leftJoin('servicio_nomina', 'servicio_nomina.fk_servicio_id', '=', 'servicio.servicio_id');
            $this->joins['servicio_nomina'] = true;
        }

        return $query;
    }

    private function joinHistorialfile(Builder $query): Builder
    {
        if (empty($this->joins['historialfile'])) {
            $query->leftJoin('historialfile', 'historialfile.fk_reserva_id', '=', 'reserva.reserva_id');
            $this->joins['historialfile'] = true;
        }

        return $query;
    }

    /** Nombre de país de la licencia (LICPAIS) para el filtro residente. */
    private function paisLicencia(): string
    {
        $codigo = Licencia::pais();
        if ($codigo === '') {
            return '';
        }

        return (string) (DB::table('pais')->where('pais_codigo', $codigo)->value('pais_nombre') ?? '');
    }

    /** Normaliza un filtro multivaluado: array | 'A-B-C' | 'A,B,C' -> list<string>. */
    private function lista(mixed $v): array
    {
        if ($v === null || $v === '' || $v === 'A') {
            return [];
        }
        if (is_array($v)) {
            return array_values(array_filter(array_map('strval', $v), fn ($x) => $x !== ''));
        }

        $sep = str_contains((string) $v, '-') ? '-' : ',';

        return array_values(array_filter(array_map('trim', explode($sep, (string) $v)), fn ($x) => $x !== ''));
    }

    /** Acepta fechas ISO (yyyy-mm-dd) del front o dd/mm/yyyy del legacy (caltodb). */
    private function fecha(string $v): string
    {
        $v = trim($v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $v;
    }
}
