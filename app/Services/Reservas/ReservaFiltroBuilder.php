<?php

namespace App\Services\Reservas;

use App\Support\Licencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aplica al query del listado de reservas TODAS las condiciones WHERE de
 * reserva_model.php::listar() (líneas 98-404), fiel al legacy.
 *
 * IMPORTANTE: para ser compatible con MySQL en modo ONLY_FULL_GROUP_BY (que
 * algunos tenants tienen activo), NO usamos JOIN a tablas 1:N (servicio,
 * factura, pnraereo, servicio_nomina, historialfile) en el FROM — eso obligaría
 * a GROUP BY. En su lugar, los filtros sobre esas tablas se expresan como
 * subconsultas EXISTS/IN correlacionadas por reserva.reserva_id, lo que da el
 * mismo resultado sin multiplicar filas ni necesitar GROUP BY. Los JOIN 1:1
 * (cliente, usuario, negocio) los arma ReservaListadoService.
 *
 * Mapa de claves de $filtros (nombres HTTP del front), equivalentes a CI:
 *   status, tipo (tipocodigo), codigo, cliente, responsable(→promotor), rsv(idsistema),
 *   proveedor, prestador, representante, cadenacliente, vendedor|fk_vendedor_id(→agente),
 *   negocio, operativo, usuario(→fk_usuario_id), ticket, recloc, nro_confirmacion,
 *   codigo_externo, residente, titular, solofacturado, solopagos, soloocultas,
 *   mostrarreprogramados, soloovencidas, tipoproducto(→servicio.fk_tipoproducto_id),
 *   pago, facturafrom, facturato, factura, from, to, tipofecha, fecha_alta, fecha_alta_to,
 *   auditado, id.
 */
class ReservaFiltroBuilder
{
    public function aplicar(Builder $query, array $f): void
    {
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

        // --- Proveedor / prestador (servicio, EXISTS) (listar 161-167) ---
        if (! empty($f['proveedor']) && (int) $f['proveedor'] !== 0) {
            $this->existsServicio($query, fn ($q) => $q->where('servicio.fk_proveedor_id', (int) $f['proveedor'])->where('servicio.status', '!=', 'CA'));
        }
        if (! empty($f['prestador']) && (int) $f['prestador'] !== 0) {
            $this->existsServicio($query, fn ($q) => $q->where('servicio.fk_prestador_id', (int) $f['prestador'])->where('servicio.status', '!=', 'CA'));
        }

        // --- Representante (cliente, JOIN 1:1) (listar 169-171, 402) ---
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

        // --- cadenacliente (cliente, JOIN 1:1) (listar 215) ---
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
            $ticket = (string) $f['ticket'];
            $this->existsServicio($query, function ($q) use ($ticket) {
                $q->whereExists(fn ($p) => $p->from('pnraereo')->whereColumn('pnraereo.fk_ocupacion_id', 'servicio.servicio_id')->where('pnraereo.pnraereo_tkt', $ticket));
            });
        }
        if (! empty($f['recloc'])) {
            $recloc = (string) $f['recloc'];
            $this->existsServicio($query, function ($q) use ($recloc) {
                $q->whereExists(fn ($p) => $p->from('pnraereo')->whereColumn('pnraereo.fk_ocupacion_id', 'servicio.servicio_id')->where('pnraereo.codigo_recloc', $recloc));
            });
        }
        if (! empty($f['nro_confirmacion'])) {
            $this->existsServicio($query, fn ($q) => $q->where('servicio.nro_confirmacion', 'LIKE', $f['nro_confirmacion'].'%'));
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

        // --- titular (reserva + servicio_nomina via EXISTS) (listar 254-265) ---
        if (! empty($f['titular'])) {
            $titular = (string) $f['titular'];
            $query->where(function (Builder $q) use ($titular) {
                $q->where('reserva.titular_apellido', 'LIKE', "%{$titular}%");
                if (is_numeric($titular)) {
                    $q->orWhere('reserva.titular_nombre', 'LIKE', "%{$titular}%");
                }
                $q->orWhereExists(function ($sub) use ($titular) {
                    $sub->from('servicio_nomina as sn')
                        ->join('servicio as sv', 'sv.servicio_id', '=', 'sn.fk_servicio_id')
                        ->whereColumn('sv.fk_reserva_id', 'reserva.reserva_id')
                        ->where('sn.apellido', 'LIKE', "%{$titular}%");
                });
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

        // --- tipoproducto (servicio, EXISTS) (listar 311-315) ---
        if (! empty($f['tipoproducto'])) {
            $this->existsServicio($query, fn ($q) => $q->where('servicio.fk_tipoproducto_id', (string) $f['tipoproducto']));
        }

        // --- pago (sin recibo) (listar 316-318) ---
        if (! empty($f['pago'])) {
            $query->where(function (Builder $q) {
                $q->whereNotIn('reserva.reserva_id', function ($sub) {
                    $sub->select('fk_file_id')->distinct()->from('rel_filerecibo');
                })->where('reserva.total', '>', 0);
            });
        }

        // --- factura: from/to/nro (rel_filefactura+factura, EXISTS) (listar 323-331) ---
        $this->factura($query, $f);

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

    /** EXISTS sobre servicio de la reserva (reemplaza el JOIN a servicio del legacy). */
    private function existsServicio(Builder $query, callable $cond): void
    {
        $query->whereExists(function ($q) use ($cond) {
            $q->from('servicio')->whereColumn('servicio.fk_reserva_id', 'reserva.reserva_id');
            $cond($q);
        });
    }

    /** Filtros de factura (rel_filefactura + factura) como EXISTS. */
    private function factura(Builder $query, array $f): void
    {
        $aplica = function (callable $cond) use ($query) {
            $query->whereExists(function ($q) use ($cond) {
                $q->from('rel_filefactura')
                    ->join('factura', 'factura.factura_id', '=', 'rel_filefactura.fk_factura_id')
                    ->whereColumn('rel_filefactura.fk_file_id', 'reserva.reserva_id')
                    ->where('factura.statusfactura', '!=', 'AN')
                    ->where('factura.statusfactura', '!=', 'NU');
                $cond($q);
            });
        };

        if (! empty($f['facturafrom'])) {
            $aplica(fn ($q) => $q->where('factura.factura_fecha', '>=', $this->fecha($f['facturafrom']).' 00:00:00'));
        }
        if (! empty($f['facturato'])) {
            $aplica(fn ($q) => $q->where('factura.factura_fecha', '<=', $this->fecha($f['facturato']).' 23:59:59'));
        }
        if (! empty($f['factura']) && (int) $f['factura'] !== 0) {
            $aplica(fn ($q) => $q->where('factura.factura_nro', 'LIKE', '%'.$f['factura'].'%'));
        }
    }

    /** solofacturado 1/2/3 con la bifurcación por licencia de listar 269-293 (EXISTS). */
    private function solofacturado(Builder $query, array $f): void
    {
        if (! isset($f['solofacturado']) || $f['solofacturado'] === '') {
            return;
        }
        $v = (int) $f['solofacturado'];

        if (Licencia::flag('facturado_med')) {
            $sub = fn ($q) => $q->select('fk_file_id')->distinct()->from('factura')
                ->where('statusfactura', '!=', 'AN')->where('statusfactura', '!=', 'NU');
            if ($v === 1) {
                $query->whereIn('reserva.reserva_id', $sub);
            } elseif ($v === 2) {
                $query->whereNotIn('reserva.reserva_id', $sub);
            }

            return;
        }

        // Resto de licencias: por servicio (rel_serviciofactura), correlacionado a la reserva.
        $facturado = fn ($q) => $q->from('servicio as sf')
            ->whereColumn('sf.fk_reserva_id', 'reserva.reserva_id')
            ->whereIn('sf.servicio_id', fn ($s) => $s->select('fk_servicio_id')->distinct()->from('rel_serviciofactura'));

        $pendiente = fn ($q) => $q->from('servicio as s2')
            ->where('s2.status', '!=', 'CA')
            ->whereColumn('s2.fk_reserva_id', 'reserva.reserva_id')
            ->where(fn ($w) => $w->where('s2.total', '!=', 0)->orWhere('s2.costo', '!=', 0))
            ->whereNotIn('s2.servicio_id', fn ($s) => $s->select('fk_servicio_id')->distinct()->from('rel_serviciofactura'));

        if ($v === 1) {
            $query->whereExists($facturado)->whereNotExists($pendiente);
        } elseif ($v === 3) {
            $query->whereExists($facturado)->whereExists($pendiente);
        } elseif ($v === 2) {
            $corte = Licencia::flag('mybeds_corte_servicio', null);
            $corteSql = is_numeric($corte) ? " AND sfc.servicio_id > {$corte}" : '';
            $query->whereRaw("reserva.reserva_id IN (SELECT rfc.reserva_id FROM reserva rfc WHERE NOT EXISTS (SELECT 1 FROM servicio sfc JOIN serviciofactura sffc ON sfc.servicio_id = sffc.fk_servicio_id WHERE sfc.fk_reserva_id = rfc.reserva_id{$corteSql}) AND rfc.reserva_id NOT IN (SELECT DISTINCT(fk_file_id) FROM factura WHERE statusfactura!='AN' AND statusfactura!='NU'))");
        }
    }

    /** Rango de fechas según tipofecha: alta/vencimiento/checkin/checkout/emision/canceladas. */
    private function rangoFechas(Builder $query, array $f, string $tipofecha): void
    {
        $from = ! empty($f['from']) ? $this->fecha($f['from']) : null;
        $to = ! empty($f['to']) ? $this->fecha($f['to']) : null;
        if ($from === null && $to === null) {
            return;
        }

        switch ($tipofecha) {
            case 'checkin':
                if ($from) {
                    $query->where('reserva.inicio', '>=', $from);
                }
                if ($to) {
                    $query->where('reserva.inicio', '<=', $to);
                }
                break;

            case 'vencimiento':
                if ($from) {
                    $query->where('reserva.fecha_vencimiento', '>=', $from);
                }
                if ($to) {
                    $query->where('reserva.fecha_vencimiento', '<=', $to);
                }
                break;

            case 'checkout':
                $this->existsServicio($query, function ($q) use ($from, $to) {
                    if ($from) {
                        $q->where('servicio.vigencia_fin', '>=', $from);
                    }
                    if ($to) {
                        $q->where('servicio.vigencia_fin', '<=', $to);
                    }
                });
                break;

            case 'emision':
                $this->existsServicio($query, function ($q) use ($from, $to) {
                    $q->whereExists(function ($p) use ($from, $to) {
                        $p->from('pnraereo')->whereColumn('pnraereo.fk_ocupacion_id', 'servicio.servicio_id');
                        if ($from) {
                            $p->where('pnraereo.pnraereo_fechaemision', '>=', $from);
                        }
                        if ($to) {
                            $p->where('pnraereo.pnraereo_fechaemision', '<=', $to);
                        }
                    });
                });
                break;

            case 'canceladas':
                $query->whereExists(function ($q) use ($from, $to) {
                    $q->from('historialfile')
                        ->whereColumn('historialfile.fk_reserva_id', 'reserva.reserva_id')
                        ->where('historialfile.historial_valor', 'CA')
                        ->where('historialfile.historial_campo', 'reserva.fk_filestatus_id');
                    if ($from) {
                        $q->where('historialfile.historial_date', '>=', $from);
                    }
                    if ($to) {
                        $q->where('historialfile.historial_date', '<=', $to);
                    }
                });
                break;

            default:
                if ($from) {
                    $query->where('reserva.fecha_alta', '>=', $from);
                }
                if ($to) {
                    $query->where('reserva.fecha_alta', '<=', $to);
                }
                break;
        }
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
