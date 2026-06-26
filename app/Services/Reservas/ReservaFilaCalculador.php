<?php

namespace App\Services\Reservas;

use App\Support\Licencia;
use Illuminate\Support\Facades\DB;

/**
 * Calcula, para cada fila del listado, los campos derivados que la vista de CI
 * arma en reserva_model.php::listar() (líneas 484-1261), en modo _eslista:
 * serviciostxt, maxpax, fecha_in/out, srvpendientes/srvfacturados, badge de
 * facturación, cobrado/saldo, padre/hijos, tildar, cobrocomision, problemas,
 * vemitido, icono/color de estado.
 *
 * Carga por lote (por los reserva_id de la página) para evitar N+1; el resultado
 * es idéntico al de CI fila por fila.
 *
 * Limitaciones documentadas (fase 2): los "problemas" de pickup/dropoff de TRN y
 * el detalle de "Edades" del maxpax dependen de los extras JSON del servicio y no
 * se calculan acá.
 */
class ReservaFilaCalculador
{
    /** Tipos de producto exentos del aviso "falta confirmación". listar:828. */
    private const SIN_AVISO_CONFIRMACION = ['AER', 'DEV', 'CAE', 'TRN', 'EXC', 'FEE'];

    public function __construct(private ReservaCobradoService $cobrados) {}

    /**
     * @param  iterable  $reservas  modelos Reserva de la página (con atributos extra del JOIN)
     * @return list<array<string,mixed>>
     */
    public function calcular(iterable $reservas): array
    {
        $reservas = collect($reservas);
        $ids = $reservas->pluck('reserva_id')->map(fn ($x) => (int) $x)->all();

        if (empty($ids)) {
            return [];
        }

        $serviciosPorReserva = $this->cargarServicios($ids);
        $serviceIds = collect($serviciosPorReserva)->flatten(1)->pluck('servicio_id')->map(fn ($x) => (int) $x)->all();
        $nominaPorServicio = $this->cargarNomina($serviceIds);
        $padres = $this->cargarPadres($reservas);
        $relacionadosPorPadre = $this->cargarRelacionados($ids);
        $facturasMed = Licencia::flag('facturado_med') ? $this->cargarFacturasMed($ids) : [];
        $agentes = $this->cargarAgentes($reservas);
        $comisiones = $this->cargarComisiones($ids);

        $filas = [];
        foreach ($reservas as $r) {
            $id = (int) $r->reserva_id;
            $servicios = $serviciosPorReserva[$id] ?? [];
            $sysid = (int) ($r->fk_sistemaaplicacion_id ?: $r->fk_sistema_id);

            [$serviciostxt, $vemitido, $iniTs, $finTs] = $this->resumirServicios($servicios);
            [$maxpax, $maxpaxArr] = $this->maxpax($id, $servicios, $nominaPorServicio);
            $srvpend = $this->contar($servicios, fn ($s) => $s->status !== 'CA' && (int) $s->facturado === 0);
            $srvfact = $this->contar($servicios, fn ($s) => $s->status !== 'CA' && (int) $s->facturado === 1);
            $problemas = $this->problemas($r, $servicios, $nominaPorServicio, $sysid);

            $tieneFacturasMed = ($facturasMed[$id] ?? 0) > 0;
            $tildar = $this->tildar($r, $tieneFacturasMed);

            $cobrado = $this->cobrados->total($id, $r->fk_moneda_id);
            $total = (float) $r->total;

            $filas[] = [
                'id' => $id,
                'codigo' => $r->tipocodigo.'-'.$r->codigo,
                'ncodigo' => (int) $r->codigo,
                'tipocodigo' => $r->tipocodigo,
                'titular' => trim($r->titular_apellido.', '.$r->titular_nombre, ', '),
                'titular_nombre' => $r->titular_nombre,
                'titular_apellido' => $r->titular_apellido,
                'status' => $r->fk_filestatus_id,
                'icono' => $this->icono($r->fk_filestatus_id),
                'color' => $this->color($r->fk_filestatus_id),
                'cliente_id' => (int) ($r->cliente_id ?? 0),
                'cliente_nombre' => $r->cliente_nombre ?? '',
                'nagente' => $agentes[(int) $r->agente] ?? '',
                'agente' => (int) $r->agente,
                'fk_usuario_id' => (int) $r->fk_usuario_id,
                'usuario' => $r->usuario ?? '',
                'operativo' => (int) ($r->operativo ?? 0),
                'responsable' => (int) ($r->promotor ?? 0),
                'negocio_nombre' => $r->negocio_nombre ?? '',
                'codigo_externo' => $r->codigo_externo,
                'serviciostxt' => $serviciostxt,
                'maxpax' => $maxpax,
                'maxpax_detalle' => $maxpaxArr,
                'srvpendientes' => $srvpend,
                'srvfacturados' => $srvfact,
                'facturado_med' => $tieneFacturasMed,
                'fecha_alta' => $this->dmy($r->fecha_alta),
                'fecha_vencimiento' => $this->dmy($r->fecha_vencimiento),
                'fecha_in' => $iniTs ? date('d/m/Y', $iniTs) : '',
                'fecha_out' => $finTs ? date('d/m/Y', $finTs) : '',
                'moneda' => $r->fk_moneda_id,
                'total' => $total,
                'cobrado' => round($cobrado, 2),
                'saldo' => round($total - $cobrado, 2),
                'status_factura' => $r->status_factura,
                'padre' => $padres[(int) $r->fk_filepadre_id] ?? null,
                'fk_filepadre_id' => (int) $r->fk_filepadre_id,
                'relacionados' => $relacionadosPorPadre[$id] ?? [],
                'tildar' => $tildar,
                'cobrocomision' => $comisiones[$id] ?? 0,
                'problemas' => $problemas,
                'vemitido' => $vemitido,
                'auditado' => (int) ($r->auditado ?? 0),
                'cerrada' => (int) ($r->cerrada ?? 0),
                'autorizado' => (int) ($r->autorizado ?? 0),
                'historial_date' => $r->historial_date ?? null,
            ];
        }

        return $filas;
    }

    /** @return array{0:array<string>,1:int,2:?int,3:?int} [lineas serviciostxt, vemitido, iniTs, finTs] */
    private function resumirServicios(array $servicios): array
    {
        $lineas = [];
        $vemitido = 0;
        $ini = null;
        $fin = null;

        foreach ($servicios as $s) {
            $nombre = trim((string) $s->servicio_nombre) !== '' ? $s->servicio_nombre : (string) $s->producto_nombre;
            $lineas[] = $s->fk_tipoproducto_id.': '.mb_substr((string) $nombre, 0, 40);

            if ((int) $s->autoriza_evoucher === 2) {
                $vemitido = 1;
            }

            if ($s->status !== 'CA' && $s->vigencia_ini && $s->vigencia_ini !== '0000-00-00' && $s->vigencia_ini !== '1969-12-31') {
                $tsIni = strtotime((string) $s->vigencia_ini);
                $tsFin = $s->vigencia_fin && $s->vigencia_fin !== '0000-00-00' ? strtotime((string) $s->vigencia_fin) : $tsIni;
                if ($ini === null || $tsIni < $ini) {
                    $ini = $tsIni;
                }
                if ($fin === null || $tsFin > $fin) {
                    $fin = $tsFin;
                }
            }
        }

        if ($ini !== null && $fin === null) {
            $fin = $ini;
        }

        return [$lineas, $vemitido, $ini, $fin];
    }

    /** @return array{0:string,1:array{0:int,1:int,2:int}} */
    private function maxpax(int $reservaId, array $servicios, array $nominaPorServicio): array
    {
        $porNombre = [];
        foreach ($servicios as $s) {
            foreach ($nominaPorServicio[(int) $s->servicio_id] ?? [] as $pax) {
                if (trim((string) $pax->nombre) === '' || trim((string) $pax->apellido) === '') {
                    continue;
                }
                $porNombre[$pax->nombre] = $pax; // dedupe por nombre (GROUP BY nombre de CI)
            }
        }

        $adt = $mnr = $jnr = 0;
        foreach ($porNombre as $p) {
            match ($p->tipopax) {
                'ADU' => $adt++,
                'MEN', 'CHD' => $mnr++,
                'JNR', 'INF' => $jnr++,
                default => null,
            };
        }
        $total = count($porNombre);

        if ($reservaId === 40240 && $total > 0) { // hack literal de CI (listar:624)
            $total--;
            $adt--;
        }

        $partes = [];
        if ($adt != 0) {
            $partes[] = "{$adt} ADT";
        }
        if ($mnr != 0) {
            $partes[] = "{$mnr} MEN";
        }
        if ($jnr != 0) {
            $partes[] = "{$jnr} INF";
        }

        $str = $total.' ('.implode(' + ', $partes).')';

        return [$str, [$adt, $mnr, $jnr]];
    }

    private function problemas($r, array $servicios, array $nominaPorServicio, int $sysid): string
    {
        $problemas = '';
        $esMundotour = Licencia::flag('problema_vencimiento_proveedor');

        foreach ($servicios as $s) {
            if ($s->status === 'CA') {
                continue;
            }
            $item = '';

            if (! in_array($s->fk_tipoproducto_id, self::SIN_AVISO_CONFIRMACION, true) && trim((string) $s->nro_confirmacion) === '') {
                $item .= ' - Falta codigo de confirmacion del proveedor<br>';
            }
            if ($esMundotour && ($s->vencimiento_proveedor === '' || $s->vencimiento_proveedor === null || $s->vencimiento_proveedor === '0000-00-00')) {
                $item .= ' - Sin vencimiento de pago al proveedor.<br>';
            }

            $nomObl = $s->fk_tipoproducto_id === 'HOT';
            $dniObl = $s->fk_tipoproducto_id === 'ASV';
            $fnacObl = $s->fk_tipoproducto_id === 'ASV';

            $xpax = 1;
            foreach ($nominaPorServicio[(int) $s->servicio_id] ?? [] as $p) {
                if ($sysid === 1 && $xpax > 1) {
                    $xpax++;

                    continue;
                }
                if ($nomObl && (trim((string) $p->nombre) === '' || trim((string) $p->apellido) === '')) {
                    $item .= ' - Pasajero sin nombre y apellido<br>';
                }
                if ($dniObl && trim((string) $p->documento) === '') {
                    $item .= ' - Pasajero sin documento<br>';
                }
                if (($fnacObl || $p->tipopax === 'CHD') && trim((string) ($p->nacimiento ?? '')) === '') {
                    $item .= ' - Pasajero sin fecha de nacimiento<br>';
                }
                if ($p->tipopax === 'CHD' && trim((string) ($p->edad ?? '')) === '') {
                    $item .= ' - Pasajero menor sin edad<br>';
                }
                $xpax++;
            }

            if ($item !== '') {
                $problemas .= ($problemas !== '' ? '<br>' : '').' <b><u>SERVICIO '.stripslashes((string) $s->servicio_nombre).':</u></b> <br>'.$item;
            }
        }

        // Problemas a nivel reserva (listar:1138-1144).
        if ((int) ($r->reserva_mayorista ?? 0) === 1 && (int) ($r->vendedor_mayorista ?? 0) === 0) {
            $problemas .= ($problemas !== '' ? '<br>' : '').' <b><u>RESERVA:</u></b> <br>Debes completar el vendedor para el cual es la reserva.';
        }
        if ((int) ($r->reserva_mayorista ?? 0) === 1 && (int) ($r->facturar_a ?? 0) === 0) {
            $problemas .= ($problemas !== '' ? '<br>' : '').' <b><u>RESERVA:</u></b> <br>Debes completar el campo \'Facturar A\'.';
        }

        return $problemas;
    }

    /** tildar: 0 en consolidador sin autorizar (salvo licencias exceptuadas) o con facturas. listar:1115-1121. */
    private function tildar($r, bool $tieneFacturasMed): int
    {
        $tildar = 1;
        if ((int) $r->fk_sistema_id === 4 && (int) $r->autorizado !== 1 && ! Licencia::flag('tildar_consolidador_excepto')) {
            $tildar = 0;
        }
        if ($tieneFacturasMed) {
            $tildar = 0;
        }

        return $tildar;
    }

    private function cargarServicios(array $ids): array
    {
        $rows = DB::table('servicio')
            ->leftJoin('producto', 'producto.producto_id', '=', 'servicio.fk_producto_id')
            ->whereIn('servicio.fk_reserva_id', $ids)
            ->orderBy('servicio.vigencia_ini')
            ->get([
                'servicio.servicio_id', 'servicio.fk_reserva_id', 'servicio.fk_tipoproducto_id',
                'servicio.status', 'servicio.servicio_nombre', 'producto.producto_nombre',
                'servicio.vigencia_ini', 'servicio.vigencia_fin', 'servicio.nro_confirmacion',
                'servicio.vencimiento_proveedor', 'servicio.autoriza_evoucher', 'servicio.facturado',
            ]);

        return $rows->groupBy('fk_reserva_id')->map(fn ($g) => $g->all())->all();
    }

    private function cargarNomina(array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }
        $rows = DB::table('servicio_nomina')
            ->whereIn('fk_servicio_id', $serviceIds)
            ->get(['fk_servicio_id', 'nombre', 'apellido', 'tipopax', 'documento', 'nacimiento', 'edad']);

        return $rows->groupBy('fk_servicio_id')->map(fn ($g) => $g->all())->all();
    }

    private function cargarPadres($reservas): array
    {
        $padreIds = $reservas->pluck('fk_filepadre_id')->map(fn ($x) => (int) $x)->filter()->unique()->values()->all();
        if (empty($padreIds)) {
            return [];
        }
        $rows = DB::table('reserva')->whereIn('reserva_id', $padreIds)->get(['reserva_id', 'tipocodigo', 'codigo']);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->reserva_id] = ['id' => (int) $row->reserva_id, 'codigo' => $row->tipocodigo.'-'.$row->codigo];
        }

        return $out;
    }

    private function cargarRelacionados(array $ids): array
    {
        $rows = DB::table('reserva')
            ->where('fk_filestatus_id', '!=', 'CA')
            ->whereIn('fk_filepadre_id', $ids)
            ->get(['reserva_id', 'fk_filepadre_id', 'tipocodigo', 'codigo']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->fk_filepadre_id][] = ['id' => (int) $row->reserva_id, 'codigo' => $row->tipocodigo.'-'.$row->codigo];
        }

        return $out;
    }

    /** Reservas que tienen alguna comisión de usuario (cobrocomision=1). */
    private function cargarComisiones(array $ids): array
    {
        $rows = DB::table('usuariocomision')
            ->whereIn('fk_file_id', $ids)
            ->distinct()
            ->pluck('fk_file_id');

        $out = [];
        foreach ($rows as $id) {
            $out[(int) $id] = 1;
        }

        return $out;
    }

    private function cargarFacturasMed(array $ids): array
    {
        $rows = DB::table('rel_filefactura')
            ->join('factura', 'factura.factura_id', '=', 'rel_filefactura.fk_factura_id')
            ->whereIn('rel_filefactura.fk_file_id', $ids)
            ->where('factura.statusfactura', '!=', 'AN')
            ->where('factura.statusfactura', '!=', 'NU')
            ->groupBy('rel_filefactura.fk_file_id')
            ->select('rel_filefactura.fk_file_id', DB::raw('COUNT(DISTINCT factura.factura_id) as cant'))
            ->pluck('cant', 'fk_file_id');

        return $rows->map(fn ($x) => (int) $x)->all();
    }

    private function contar(array $servicios, callable $cond): int
    {
        $n = 0;
        foreach ($servicios as $s) {
            if ($cond($s)) {
                $n++;
            }
        }

        return $n;
    }

    /** Nombre del agente por id (CI lo resuelve por fila; acá por lote para evitar N+1). */
    private function cargarAgentes($reservas): array
    {
        $ids = $reservas->pluck('agente')->map(fn ($x) => (int) $x)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return [];
        }

        return DB::table('usuario')
            ->whereIn('usuario_id', $ids)
            ->selectRaw("usuario_id, CONCAT(usuario_apellido, ', ', usuario_nombre) AS nagente")
            ->pluck('nagente', 'usuario_id')
            ->all();
    }

    private function icono(string $status): string
    {
        return config("reservas.estados.{$status}.icono", 'pin');
    }

    private function color(string $status): string
    {
        return config("reservas.estados.{$status}.color", 'BDC102');
    }

    private function dmy($fecha): string
    {
        if (! $fecha) {
            return '';
        }
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $s = (string) $fecha;
        if (str_starts_with($s, '0000') || $s === '') {
            return '';
        }
        $ts = strtotime($s);

        return $ts ? date('d/m/Y', $ts) : '';
    }
}
