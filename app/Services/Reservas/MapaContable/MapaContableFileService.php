<?php

namespace App\Services\Reservas\MapaContable;

use App\Services\CotizacionService;
use App\Support\Licencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa contable de un file: todo lo que se emitió, cobró, facturó el proveedor,
 * se le pagó y se asentó, colgado de cada servicio, con lo que se gana en
 * realidad (EconomiaFile) y los desvíos detectados (DesviosFile).
 *
 * Sólo lectura. Cómo se vincula cada comprobante al file (relevado en el CI,
 * ver memoria del proyecto "mapa-contable-file-vinculos"):
 *
 *  - Factura: factura.fk_file_id, rel_filefactura (puede ser de varios files) y
 *    servicio → rel_serviciofactura / serviciofactura (tipodocumento 1). Ninguna
 *    de las tres alcanza sola: la librería Facturar no escribe rel_filefactura y
 *    la ficha del CI (get_facturas) sólo lee esa. Se toma la unión y se informa
 *    por qué camino apareció cada una.
 *  - NC: notacredito.fk_factura_id, notacredito.fk_file_id (sólo si nació en
 *    factura.php) o serviciofactura tipodocumento 2 (ahí fk_factura_id es el id
 *    de la NC).
 *  - ND: únicamente por fk_notacredito_id.
 *  - Recibo: rel_filerecibo (importe por file). Nunca por servicio.
 *  - OP / OS: rel_ordenadminocupacion por servicio. Sus movimientos no llevan file.
 *  - Factura de proveedor: rel_facturaproveedorocupacion por servicio, ídem.
 *  - Asientos en cta. cte. (ordenadmin tipo C) y manuales (A): movimiento.fk_file_id.
 *
 * No existe en ninguna tabla el importe facturado POR SERVICIO
 * (serviciofactura.monto y rel_serviciofactura.monto no los escribe nadie), así
 * que la comparación venta facturada vs servicios se hace por comprobante.
 *
 * Los files agrupados (fk_agrupado_id) se incluyen como hacen cobrado() del CI y
 * ReservaCobradoService.
 */
class MapaContableFileService
{
    public const TOLERANCIA = 1.0;

    /** Cuenta puente de factura.php (costo y renta a la vez). Ver Analitica_model::CUENTA_PUENTE. */
    private const CUENTA_PUENTE = 212201;

    private Tasas $tasas;

    private string $basica = 'ARS';

    private int $dec = 2;

    private float $coef = 1.21;

    /** @var array<string,bool> */
    private array $tablas = [];

    public function __construct(private CotizacionService $cotizaciones, private DesviosFile $desvios) {}

    public function armar(int $reservaId): ?array
    {
        $r = DB::table('reserva')
            ->leftJoin('cliente', 'cliente.cliente_id', '=', 'reserva.fk_cliente_id')
            ->where('reserva.reserva_id', $reservaId)
            ->first(['reserva.*', 'cliente.cliente_nombre']);
        if ($r === null) {
            return null;
        }

        $this->basica = $this->cotizaciones->monedaBasica();
        $this->tasas = new Tasas($this->cotizaciones, $this->basica);
        $this->dec = $this->decimales();
        $this->coef = $this->coef();

        $files = $this->files($reservaId);
        $servicios = $this->servicios($files, $this->fecha($r->fecha_alta));
        $venta = $this->circuitoVenta($files, $servicios);
        $costo = $this->circuitoCosto($files, $servicios);
        $contable = $this->contabilidad($files, $servicios, $venta, $costo);

        $economia = (new EconomiaFile($this->dec))->calcular(
            array_values(array_map(fn ($s) => [
                'id' => $s['id'],
                'cancelado' => $s['cancelado'],
                'total' => $s['total'],
                'iva' => $s['iva'],
                'costo_operado' => $s['costo_operado'],
                'cv' => $s['cv'],
                'cc' => $s['cc'],
                'renta_congelada' => $s['renta_congelada'],
                'tasa_factura' => $s['tasa_factura'],
                'fc3' => $s['fc3'],
                'pagos' => array_values(array_filter($s['pagos'], fn ($p) => ! $p['anulado'])),
            ], $servicios)),
            $venta['para_economia'],
            $venta['cobranza'],
            $venta['cobrado_base'],
        );

        $mapa = [
            'file' => [
                'id' => (int) $r->reserva_id,
                'codigo' => trim($r->tipocodigo.'-'.$r->codigo, '-'),
                'cliente' => (string) ($r->cliente_nombre ?? ''),
                'titular' => trim(($r->titular_apellido ?? '').', '.($r->titular_nombre ?? ''), ', '),
                'estado' => (string) $r->fk_filestatus_id,
                'moneda' => (string) $r->fk_moneda_id,
                'fecha_alta' => $this->fecha($r->fecha_alta),
                'total' => (float) $r->total,
                'files_agrupados' => array_values(array_diff($files, [(int) $r->reserva_id])),
                'link' => '/reserva/editar/'.(int) $r->reserva_id,
            ],
            'moneda_basica' => $this->basica,
            'decimales' => $this->dec,
            'tolerancia' => self::TOLERANCIA,
            'servicios' => array_values($servicios),
            'venta' => array_diff_key($venta, array_flip(['para_economia'])),
            'costo' => $costo,
            'contable' => $contable,
            'economia' => $economia,
        ];
        $mapa['desvios'] = $this->desvios->evaluar($mapa);

        return $mapa;
    }

    // ------------------------------------------------------------------
    // Operación
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> servicios indexados por id */
    private function servicios(array $files, ?string $fechaAlta): array
    {
        // submodulo.cuenta_renta es del esquema contable por producto; no todas las
        // bases la tienen.
        $colRenta = Schema::hasColumn('submodulo', 'cuenta_renta') ? 'submodulo.cuenta_renta as sm_cuenta_renta' : DB::raw('0 as sm_cuenta_renta');
        $rows = DB::table('servicio')
            ->leftJoin('proveedor', 'proveedor.proveedor_id', '=', 'servicio.fk_proveedor_id')
            ->leftJoin('submodulo', 'submodulo.tipoproducto_id', '=', 'servicio.fk_tipoproducto_id')
            ->whereIn('servicio.fk_reserva_id', $files)
            ->orderBy('servicio.vigencia_ini')
            ->orderBy('servicio.servicio_id')
            ->get(['servicio.*', 'proveedor.proveedor_nombre', 'submodulo.tipoproducto_nombre',
                $colRenta, 'submodulo.fk_plancuenta_id as sm_cuenta_costo']);

        $ids = $rows->pluck('servicio_id')->map(fn ($v) => (int) $v)->all();
        $conPnr = array_flip(DB::table('pnraereo')->whereIn('fk_ocupacion_id', $ids)->pluck('fk_ocupacion_id')->map(fn ($v) => (int) $v)->all());

        $out = [];
        foreach ($rows as $s) {
            $id = (int) $s->servicio_id;
            $tienePnr = isset($conPnr[$id]);
            [$cv, $cvOrigen] = $this->tasas->delServicio((string) $s->fk_moneda_id, (float) $s->cotventa, $fechaAlta);
            [$cc, $ccOrigen] = $this->tasas->delServicio((string) $s->moneda_costo, (float) $s->cotcosto, $fechaAlta);

            $out[$id] = [
                'id' => $id,
                'file_id' => (int) $s->fk_reserva_id,
                'nombre' => (string) $s->servicio_nombre,
                'tipo' => (string) $s->fk_tipoproducto_id,
                'tipo_nombre' => (string) ($s->tipoproducto_nombre ?? ''),
                'proveedor' => (string) ($s->proveedor_nombre ?? ''),
                'vigencia' => $this->fecha($s->vigencia_ini ?? null),
                'status' => (string) $s->status,
                'cancelado' => (string) $s->status === 'CA',
                'facturado_flag' => (int) ($s->facturado ?? 0),
                'moneda_venta' => (string) $s->fk_moneda_id,
                'moneda_costo' => (string) $s->moneda_costo,
                'total' => (float) $s->total,
                'iva' => (float) $s->iva,
                'costo' => (float) $s->costo,
                'iva_costo' => (float) $s->iva_costo,
                'impuestos' => (float) $s->impuestos,
                'tiene_pnr' => $tienePnr,
                // Mismo criterio que Analitica_model::sql_renta_operacion (impuestos fuera si hay PNR).
                'costo_operado' => (float) $s->costo + (float) $s->iva_costo + ($tienePnr ? 0.0 : (float) $s->impuestos),
                'cv' => $cv,
                'cv_origen' => $cvOrigen,
                'cc' => $cc,
                'cc_origen' => $ccOrigen,
                'renta_congelada' => null,
                'cuenta_renta' => (int) ($s->sm_cuenta_renta ?? 0),
                'cuenta_costo' => (int) ($s->sm_cuenta_costo ?? 0),
                'facturas' => [],
                'notas_credito' => [],
                'factura_vigente' => null,
                'tasa_factura' => null,
                'tasa_factura_origen' => null,
                'fc3' => [],
                'pagos' => [],
                'comisiones' => [],
                'ordenes_servicio' => [],
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Circuito de venta
    // ------------------------------------------------------------------

    private function circuitoVenta(array $files, array &$servicios): array
    {
        $ids = array_keys($servicios);
        $links = $this->vinculosServicio($ids, 'fk_servicio_id');

        // Renta congelada: la de la factura activa más reciente (serviciofactura tipo 1).
        foreach ($links as $l) {
            if ($l['tipodoc'] === 1 && $l['fuente'] === 'sf' && $l['activo'] && $l['renta'] != 0.0) {
                $servicios[$l['servicio']]['renta_congelada'] = $l['renta'];
            }
        }

        // ---- Facturas: unión de los tres caminos --------------------------
        $caminos = [];
        foreach (DB::table('factura')->whereIn('fk_file_id', $files)->pluck('factura_id') as $id) {
            $caminos[(int) $id]['fk_file_id'] = true;
        }
        foreach (DB::table('rel_filefactura')->whereIn('fk_file_id', $files)->pluck('fk_factura_id') as $id) {
            $caminos[(int) $id]['rel_filefactura'] = true;
        }
        foreach ($links as $l) {
            if ($l['tipodoc'] === 1) {
                $caminos[$l['doc']]['servicio'] = true;
            }
        }
        unset($caminos[0]);
        $facturaIds = array_keys($caminos);

        $filesPorFactura = [];
        foreach (DB::table('rel_filefactura')->whereIn('fk_factura_id', $facturaIds)->get() as $x) {
            $filesPorFactura[(int) $x->fk_factura_id][(int) $x->fk_file_id] = true;
        }

        // Servicios de otros files en las mismas facturas (para el prorrateo).
        $ajenosFc = $this->vinculosDocumento($facturaIds, 1, $ids);

        $facturas = [];
        foreach (DB::table('factura')->whereIn('factura_id', $facturaIds)->orderBy('factura_id')->get() as $f) {
            $id = (int) $f->factura_id;
            $fecha = $this->fecha($f->factura_fecha);
            $tc = (float) $f->factura_tipo_cambio;
            [$tasa, $origen] = $this->tasas->delDocumento((string) $f->fk_moneda_id, $tc, $fecha);
            if ((int) $f->fk_file_id !== 0) {
                $filesPorFactura[$id][(int) $f->fk_file_id] = true;
            }
            $propios = $this->serviciosDelDoc($links, $id, 1);
            $total = $this->totalFactura($f);

            $facturas[$id] = [
                'id' => $id,
                'tipo' => 'FC',
                'numero' => trim(($f->factura_tipo ?? '').' '.($f->factura_nro ?? '')),
                'fecha' => $fecha,
                'moneda' => (string) $f->fk_moneda_id,
                'total' => $total,
                'total_grabado' => (float) ($f->factura_total ?? 0),
                'tc' => $tc,
                'tasa' => $tasa,
                'tasa_origen' => $origen,
                'base' => round($total * $tasa, $this->dec),
                'status' => (string) $f->statusfactura,
                'fk_file_id' => (int) $f->fk_file_id,
                'caminos' => array_keys($caminos[$id] ?? []),
                'otros_files' => $this->otrosFiles(array_keys($filesPorFactura[$id] ?? []), $files),
                'servicios' => $propios,
                'servicios_ajenos' => array_keys($ajenosFc[$id] ?? []),
                'share' => 1.0,
                'link' => "/administracion/factura/imprimir/{$id}",
            ];
        }
        $this->participaciones($facturas, $servicios, $ajenosFc, $filesPorFactura, $files);

        // Factura vigente de cada servicio y tasa a la que se facturó.
        foreach ($links as $l) {
            if ($l['tipodoc'] !== 1 || ! isset($facturas[$l['doc']])) {
                continue;
            }
            $sid = $l['servicio'];
            if (! in_array($l['doc'], $servicios[$sid]['facturas'], true)) {
                $servicios[$sid]['facturas'][] = $l['doc'];
            }
            $f = $facturas[$l['doc']];
            $vigente = ($l['fuente'] === 'rel' || $l['activo']) && ! in_array($f['status'], ['AN', 'NU'], true);
            if ($vigente && ($servicios[$sid]['factura_vigente'] === null || $l['doc'] > $servicios[$sid]['factura_vigente'])) {
                $servicios[$sid]['factura_vigente'] = $l['doc'];
            }
        }
        foreach ($servicios as $sid => $s) {
            if ($s['factura_vigente'] === null) {
                continue;
            }
            $f = $facturas[$s['factura_vigente']];
            [$t, $o] = $this->tasas->paraItem($s['moneda_venta'], $f['moneda'], $f['tc'], $f['fecha']);
            if (in_array($o, [Tasas::TABLA, Tasas::FALTANTE], true) && $f['moneda'] === $this->basica) {
                $implicita = $this->tasaImplicita($f, $servicios, $ajenosFc, $s['moneda_venta']);
                if ($implicita > 0) {
                    [$t, $o] = [$implicita, Tasas::IMPLICITA];
                }
            }
            $servicios[$sid]['tasa_factura'] = $t;
            $servicios[$sid]['tasa_factura_origen'] = $o;
        }

        // ---- Notas de crédito ---------------------------------------------
        $ncCaminos = [];
        foreach (DB::table('notacredito')->whereIn('fk_factura_id', $facturaIds)->pluck('notacredito_id') as $id) {
            $ncCaminos[(int) $id]['factura'] = true;
        }
        foreach (DB::table('notacredito')->whereIn('fk_file_id', $files)->pluck('notacredito_id') as $id) {
            $ncCaminos[(int) $id]['fk_file_id'] = true;
        }
        foreach ($links as $l) {
            if ($l['tipodoc'] === 2) {
                $ncCaminos[$l['doc']]['servicio'] = true;
            }
        }
        unset($ncCaminos[0]);
        $ncIds = array_keys($ncCaminos);
        $ajenosNc = $this->vinculosDocumento($ncIds, 2, $ids);

        $ncs = [];
        foreach (DB::table('notacredito')->whereIn('notacredito_id', $ncIds)->orderBy('notacredito_id')->get() as $n) {
            $id = (int) $n->notacredito_id;
            $fecha = $this->fecha($n->notacredito_fecha);
            $tc = (float) $n->notacredito_tipo_cambio;
            [$tasa, $origen] = $this->tasas->delDocumento((string) $n->fk_moneda_id, $tc, $fecha);
            $total = $this->totalNotaCredito($n);
            $fac = (int) $n->fk_factura_id;
            $propios = $this->serviciosDelDoc($links, $id, 2);
            foreach ($propios as $sid) {
                $servicios[$sid]['notas_credito'][] = $id;
            }

            $ncs[$id] = [
                'id' => $id,
                'tipo' => 'NC',
                'numero' => trim(($n->notacredito_tipo ?? '').' '.($n->notacredito_nro ?? '')),
                'fecha' => $fecha,
                'moneda' => (string) $n->fk_moneda_id,
                'total' => $total,
                'tc' => $tc,
                'tasa' => $tasa,
                'tasa_origen' => $origen,
                'base' => round($total * $tasa, $this->dec),
                'status' => (string) $n->statusfactura,
                'fk_factura_id' => $fac,
                'fk_file_id' => (int) $n->fk_file_id,
                'caminos' => array_keys($ncCaminos[$id] ?? []),
                'servicios' => $propios,
                'servicios_ajenos' => array_keys($ajenosNc[$id] ?? []),
                'otros_files' => isset($facturas[$fac]) ? $facturas[$fac]['otros_files'] : [],
                // Una NC de una factura del file comparte su prorrateo.
                'share' => isset($facturas[$fac]) ? $facturas[$fac]['share'] : 1.0,
                'link' => "/administracion/notacredito/imprimir/{$id}",
            ];
        }
        $sinFactura = array_filter($ncs, fn ($n) => ! isset($facturas[$n['fk_factura_id']]));
        if ($sinFactura !== []) {
            $this->participaciones($sinFactura, $servicios, $ajenosNc, [], $files);
            $ncs = array_replace($ncs, $sinFactura);
        }

        // ---- Notas de débito (sólo cuelgan de una NC) ---------------------
        $nds = [];
        foreach (DB::table('notadebito')->whereIn('fk_notacredito_id', $ncIds)->orderBy('notadebito_id')->get() as $d) {
            $id = (int) $d->notadebito_id;
            $fecha = $this->fecha($d->notadebito_fecha);
            $tc = (float) $d->notadebito_tipo_cambio;
            [$tasa, $origen] = $this->tasas->delDocumento((string) $d->fk_moneda_id, $tc, $fecha);
            $total = $this->totalNotaDebito($d);
            $nc = (int) $d->fk_notacredito_id;

            $nds[$id] = [
                'id' => $id,
                'tipo' => 'ND',
                'numero' => trim(($d->notadebito_tipo ?? '').' '.($d->notadebito_nro ?? '')),
                'fecha' => $fecha,
                'moneda' => (string) $d->fk_moneda_id,
                'total' => $total,
                'tc' => $tc,
                'tasa' => $tasa,
                'tasa_origen' => $origen,
                'base' => round($total * $tasa, $this->dec),
                'status' => (string) $d->statusfactura,
                'fk_notacredito_id' => $nc,
                'share' => $ncs[$nc]['share'] ?? 1.0,
                'link' => "/administracion/notadebito/imprimir/{$id}",
            ];
        }

        // ---- Recibos -------------------------------------------------------
        [$recibos, $reciboIds] = $this->recibos($files);
        $asientosCliente = $this->asientosCtaCte($files, 'fk_cliente_id');
        foreach ($asientosCliente as &$a) {
            // cobrado() del CI: un asiento al crédito resta de lo cobrado.
            $a['signo'] = $a['cuenta_credito'] !== 0 ? -1 : 1;
        }
        unset($a);

        $aplicaciones = [];
        if ($this->hayTabla('rel_facturarecibo') && ($facturaIds !== [] || $reciboIds !== [])) {
            $q = DB::table('rel_facturarecibo')->where(function ($w) use ($facturaIds, $reciboIds) {
                $w->whereIn('fk_factura_id', $facturaIds)->orWhereIn('fk_recibo_id', $reciboIds);
            });
            foreach ($q->get() as $x) {
                $aplicaciones[] = ['factura' => (int) $x->fk_factura_id, 'recibo' => (int) $x->fk_recibo_id, 'monto' => (float) $x->monto];
            }
        }

        $cobradoBase = 0.0;
        foreach ($recibos as $rc) {
            if (! $rc['anulado']) {
                $cobradoBase += $rc['base'];
            }
        }
        foreach ($asientosCliente as $a) {
            $cobradoBase += $a['signo'] * $a['base'];
        }

        $paraEconomia = [];
        foreach ([[$facturas, 1], [$ncs, -1], [$nds, 1]] as [$docs, $signo]) {
            foreach ($docs as $d) {
                if ($d['status'] !== 'NU') {
                    $paraEconomia[] = ['signo' => $signo, 'neto_doc' => $d['total'], 'tasa' => $d['tasa'], 'share' => $d['share']];
                }
            }
        }

        return [
            'facturas' => array_values($facturas),
            'notas_credito' => array_values($ncs),
            'notas_debito' => array_values($nds),
            'recibos' => $recibos,
            'asientos' => $asientosCliente,
            'aplicaciones' => $aplicaciones,
            'aplicaciones_disponibles' => $this->hayTabla('rel_facturarecibo'),
            'cobrado_base' => round($cobradoBase, $this->dec),
            'cobranza' => $this->cobranza($facturas, $ncs, $nds, $recibos, $asientosCliente),
            'para_economia' => $paraEconomia,
        ];
    }

    /**
     * Vínculos servicio ↔ comprobante de venta de las dos tablas paralelas.
     *
     * @return list<array{servicio:int,doc:int,tipodoc:int,fuente:string,activo:bool,renta:float}>
     */
    private function vinculosServicio(array $ids, string $col): array
    {
        $out = [];
        foreach (DB::table('rel_serviciofactura')->whereIn($col, $ids)->get() as $x) {
            $out[] = ['servicio' => (int) $x->fk_servicio_id, 'doc' => (int) $x->fk_factura_id, 'tipodoc' => (int) $x->tipodocumento,
                'fuente' => 'rel', 'activo' => true, 'renta' => 0.0];
        }
        if ($this->hayTabla('serviciofactura')) {
            foreach (DB::table('serviciofactura')->whereIn($col, $ids)->orderBy('fk_factura_id')->get() as $x) {
                $out[] = ['servicio' => (int) $x->fk_servicio_id, 'doc' => (int) $x->fk_factura_id, 'tipodoc' => (int) $x->tipodocumento,
                    'fuente' => 'sf', 'activo' => (int) $x->activo === 1, 'renta' => (float) $x->renta];
            }
        }

        return $out;
    }

    /**
     * Servicios de OTROS files vinculados a los comprobantes, con lo necesario para
     * prorratear.
     *
     * @return array<int,array<int,object>> doc => [servicio_id => fila]
     */
    private function vinculosDocumento(array $docIds, int $tipodoc, array $propios): array
    {
        if ($docIds === []) {
            return [];
        }
        $pares = [];
        $tablas = ['rel_serviciofactura'];
        if ($this->hayTabla('serviciofactura')) {
            $tablas[] = 'serviciofactura';
        }
        foreach ($tablas as $t) {
            $q = DB::table($t)->whereIn('fk_factura_id', $docIds)->where('tipodocumento', $tipodoc);
            if ($propios !== []) {
                $q->whereNotIn('fk_servicio_id', $propios);
            }
            foreach ($q->get(['fk_factura_id', 'fk_servicio_id']) as $x) {
                $pares[(int) $x->fk_factura_id][(int) $x->fk_servicio_id] = true;
            }
        }
        $sids = [];
        foreach ($pares as $s) {
            $sids += $s;
        }
        $filas = DB::table('servicio')->whereIn('servicio_id', array_keys($sids))
            ->get(['servicio_id', 'fk_reserva_id', 'total', 'iva', 'fk_moneda_id', 'status'])->keyBy('servicio_id');

        $out = [];
        foreach ($pares as $doc => $s) {
            foreach (array_keys($s) as $sid) {
                if (isset($filas[$sid])) {
                    $out[$doc][$sid] = $filas[$sid];
                }
            }
        }

        return $out;
    }

    /** @return list<int> servicios propios vinculados a un comprobante */
    private function serviciosDelDoc(array $links, int $doc, int $tipodoc): array
    {
        $out = [];
        foreach ($links as $l) {
            if ($l['doc'] === $doc && $l['tipodoc'] === $tipodoc) {
                $out[$l['servicio']] = $l['servicio'];
            }
        }

        return array_values($out);
    }

    /**
     * Parte de cada comprobante que corresponde a este file: proporción de sus
     * servicios (a la tasa del comprobante) sobre todos los servicios vinculados;
     * si no tiene servicios, partes iguales entre los files que lo referencian.
     */
    private function participaciones(array &$docs, array $servicios, array $ajenos, array $filesPorDoc, array $files): void
    {
        foreach ($docs as $id => $d) {
            $propio = 0.0;
            $ajeno = 0.0;
            foreach ($d['servicios'] as $sid) {
                $s = $servicios[$sid];
                [$t] = $this->tasas->paraItem($s['moneda_venta'], $d['moneda'], $d['tc'], $d['fecha']);
                $propio += abs($s['total'] * $t);
            }
            foreach ($ajenos[$id] ?? [] as $s) {
                [$t] = $this->tasas->paraItem((string) $s->fk_moneda_id, $d['moneda'], $d['tc'], $d['fecha']);
                $ajeno += abs((float) $s->total * $t);
            }
            if ($propio + $ajeno > 0) {
                $docs[$id]['share'] = $propio / ($propio + $ajeno);

                continue;
            }
            $filesDoc = array_keys($filesPorDoc[$id] ?? []);
            if ($filesDoc !== []) {
                $docs[$id]['share'] = count(array_intersect($filesDoc, $files)) / count($filesDoc);
            }
        }
    }

    /**
     * Factura en moneda básica de servicios en moneda extranjera sin TC tipeado:
     * el TC se deduce del total si todos los servicios son de la misma moneda.
     */
    private function tasaImplicita(array $f, array $servicios, array $ajenos, string $moneda): float
    {
        $suma = 0.0;
        foreach ($f['servicios'] as $sid) {
            if ($servicios[$sid]['moneda_venta'] !== $moneda) {
                return 0.0;
            }
            $suma += $servicios[$sid]['total'];
        }
        foreach ($ajenos[$f['id']] ?? [] as $s) {
            if ((string) $s->fk_moneda_id !== $moneda) {
                return 0.0;
            }
            $suma += (float) $s->total;
        }

        return $suma > 0 ? $f['total'] / $suma : 0.0;
    }

    /** @return array{0:list<array<string,mixed>>,1:list<int>} */
    private function recibos(array $files): array
    {
        $rows = DB::table('rel_filerecibo')
            ->join('recibo', 'recibo.recibo_id', '=', 'rel_filerecibo.fk_recibo_id')
            ->whereIn('rel_filerecibo.fk_file_id', $files)
            ->orderBy('recibo.fecha')
            ->get(['rel_filerecibo.fk_file_id', 'rel_filerecibo.fk_recibo_id', 'rel_filerecibo.fecha as rel_fecha',
                'rel_filerecibo.fk_moneda_id as rel_moneda', 'rel_filerecibo.monto as rel_monto',
                'recibo.recibo_nro', 'recibo.fecha', 'recibo.statusrecibo', 'recibo.monto as recibo_monto', 'recibo.fk_moneda_id as recibo_moneda']);
        $ids = $rows->pluck('fk_recibo_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        // El recibo no guarda TC: sale del asiento en las cuentas de recibos
        // (ReservaCobradoService / reserva_model::cobrado()).
        $cotizaciones = [];
        $ctas = $this->ctasRecibos();
        foreach (DB::table('movimiento')->whereIn('fk_recibo_id', $ids)->whereIn('cuenta_debito', $ctas)->orderBy('movimiento_id')->get(['fk_recibo_id', 'cotizacion_moneda']) as $m) {
            $cotizaciones[(int) $m->fk_recibo_id] ??= (float) $m->cotizacion_moneda;
        }
        $otros = [];
        foreach (DB::table('rel_filerecibo')->whereIn('fk_recibo_id', $ids)->whereNotIn('fk_file_id', $files)->get(['fk_recibo_id', 'fk_file_id']) as $x) {
            $otros[(int) $x->fk_recibo_id][(int) $x->fk_file_id] = (int) $x->fk_file_id;
        }

        $out = [];
        $vistos = [];
        foreach ($rows as $x) {
            $id = (int) $x->fk_recibo_id;
            // rel_filerecibo no tiene clave: una imputación repetida cuenta una vez,
            // como en cobrado() (GROUP BY fk_file_id, fk_recibo_id), y se avisa.
            $clave = "{$x->fk_file_id}|{$id}";
            if (isset($vistos[$clave])) {
                $out[$vistos[$clave]]['duplicado'] = true;

                continue;
            }
            $vistos[$clave] = count($out);
            $moneda = (string) ($x->rel_moneda ?: $x->recibo_moneda);
            $fecha = $this->fecha($x->fecha ?: $x->rel_fecha);
            $cm = $cotizaciones[$id] ?? 0.0;
            [$tasa, $origen] = $this->tasas->delDocumento($moneda, $cm, $fecha);
            $out[] = [
                'id' => $id,
                'file_id' => (int) $x->fk_file_id,
                'numero' => (string) $x->recibo_nro,
                'fecha' => $fecha,
                'status' => (string) $x->statusrecibo,
                'anulado' => (string) $x->statusrecibo === 'AN',
                'moneda' => $moneda,
                'monto' => (float) $x->rel_monto,
                'monto_recibo' => (float) $x->recibo_monto,
                'cotizacion' => $cm,
                'tasa' => $tasa,
                'tasa_origen' => $origen,
                'base' => round((float) $x->rel_monto * $tasa, $this->dec),
                'otros_files' => array_values($otros[$id] ?? []),
                'duplicado' => false,
                'link' => "/administracion/recibo/imprimir/{$id}",
            ];
        }

        return [$out, $ids];
    }

    /**
     * Asientos en cta. cte. (ordenadmin tipo C, no anulados) imputados al file, del
     * lado cliente o del lado proveedor.
     */
    private function asientosCtaCte(array $files, string $lado): array
    {
        $q = DB::table('movimiento')
            ->join('ordenadmin', 'ordenadmin.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->where('ordenadmin.tipo', 'C')
            ->where('ordenadmin.status', '<>', 'AN')
            ->where("movimiento.{$lado}", '<>', 0)
            ->whereIn('movimiento.fk_file_id', $files)
            ->orderBy('movimiento.fecha');
        if ($lado === 'fk_cliente_id') {
            $q->where('movimiento.afecta_cobranza', 1);
        }

        $out = [];
        foreach ($q->get(['movimiento.*', 'ordenadmin.nropago', 'ordenadmin.observaciones as orden_obs']) as $m) {
            $fecha = $this->fecha($m->fecha);
            [$tasa, $origen] = $this->tasas->delDocumento((string) $m->fk_moneda_id, (float) $m->cotizacion_moneda, $fecha);
            $out[] = [
                'movimiento_id' => (int) $m->movimiento_id,
                'orden_id' => (int) $m->fk_ordenadmin_id,
                'numero' => (string) $m->nropago,
                'fecha' => $fecha,
                'descripcion' => trim((string) ($m->descripcion ?? '') ?: (string) ($m->orden_obs ?? '')),
                'moneda' => (string) $m->fk_moneda_id,
                'monto' => (float) $m->monto,
                'cuenta_credito' => (int) $m->cuenta_credito,
                'tasa' => $tasa,
                'tasa_origen' => $origen,
                'base' => round((float) $m->monto * $tasa, $this->dec),
                'proveedor_id' => (int) ($m->fk_proveedor_id ?? 0),
                'link' => "/administracion/ordenpago/imprimir/{$m->fk_ordenadmin_id}",
            ];
        }

        return $out;
    }

    /**
     * Insumo de DV2: lo facturado y lo cobrado expresado en la moneda extranjera
     * de facturación del file. Si se facturó en moneda básica no hay exposición;
     * si se facturó en más de una moneda extranjera no se puede atribuir cada
     * cobro a una de ellas y no se calcula.
     */
    private function cobranza(array $facturas, array $ncs, array $nds, array $recibos, array $asientos): array
    {
        $vivos = [];
        foreach ([[$facturas, 1], [$ncs, -1], [$nds, 1]] as [$docs, $signo]) {
            foreach ($docs as $d) {
                if ($d['status'] !== 'NU' && $d['share'] > 0) {
                    $vivos[] = $d + ['signo' => $signo];
                }
            }
        }
        $extranjeras = array_values(array_unique(array_filter(array_column($vivos, 'moneda'), fn ($m) => $m !== $this->basica && $m !== '')));

        if ($extranjeras === []) {
            return ['moneda' => null, 'motivo' => $vivos === [] ? 'sin_facturas' : 'moneda_basica', 'facturado' => [], 'cobrado' => []];
        }
        if (count($extranjeras) > 1) {
            return ['moneda' => null, 'motivo' => 'varias_monedas', 'facturado' => [], 'cobrado' => []];
        }

        $m = $extranjeras[0];
        $facturado = [];
        $mixta = false;
        foreach ($vivos as $d) {
            if ($d['moneda'] === $m) {
                $facturado[] = ['cantidad' => $d['signo'] * $d['total'] * $d['share'], 'tasa' => $d['tasa']];
            } else {
                $mixta = true;
            }
        }

        $cobrado = [];
        foreach ($recibos as $rc) {
            if ($rc['anulado']) {
                continue;
            }
            [$t] = $this->tasas->paraItem($m, $rc['moneda'], $rc['cotizacion'], $rc['fecha']);
            $cobrado[] = ['cantidad' => Tasas::cantidad($rc['monto'], $rc['moneda'], $rc['tasa'], $m, $t), 'tasa' => $t];
        }
        foreach ($asientos as $a) {
            [$t] = $this->tasas->paraItem($m, $a['moneda'], $a['tasa'], $a['fecha']);
            $cobrado[] = ['cantidad' => $a['signo'] * Tasas::cantidad($a['monto'], $a['moneda'], $a['tasa'], $m, $t), 'tasa' => $t];
        }

        return ['moneda' => $m, 'motivo' => $mixta ? 'mixta' : '', 'facturado' => $facturado, 'cobrado' => $cobrado];
    }

    // ------------------------------------------------------------------
    // Circuito de costo
    // ------------------------------------------------------------------

    private function circuitoCosto(array $files, array &$servicios): array
    {
        $ids = array_keys($servicios);

        // ---- Órdenes (OP 'P' y OS 'S') -------------------------------------
        $rows = DB::table('rel_ordenadminocupacion as r')
            ->join('ordenadmin as o', 'o.ordenadmin_id', '=', 'r.fk_ordenadmin_id')
            ->whereIn('r.fk_ocupacion_id', $ids)
            ->orderBy('o.fecha')
            ->get(['r.fk_ordenadmin_id', 'r.fk_ocupacion_id', 'r.fk_moneda_id as rel_moneda', 'r.monto as rel_monto', 'r.status as rel_status',
                'o.tipo', 'o.status', 'o.fecha', 'o.nropago', 'o.nroservicio', 'o.fk_moneda_id', 'o.cotizacion', 'o.monto', 'o.fk_ordenadmin_id as o_padre', 'o.fk_proveedor_id']);
        $ordenIds = $rows->pluck('fk_ordenadmin_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        // pagado() del CI no usa la cotización de la OP sino la de la OS que la
        // originó (`SELECT cotizacion FROM ordenadmin WHERE fk_ordenadmin_id = OP`).
        $cotOs = [];
        foreach (DB::table('ordenadmin')->whereIn('fk_ordenadmin_id', $ordenIds)->orderBy('ordenadmin_id')->get(['fk_ordenadmin_id', 'cotizacion']) as $x) {
            $cotOs[(int) $x->fk_ordenadmin_id] = (float) $x->cotizacion;
        }
        $compartidas = [];
        if ($ordenIds !== []) {
            foreach (DB::table('rel_ordenadminocupacion')->whereIn('fk_ordenadmin_id', $ordenIds)->whereNotIn('fk_ocupacion_id', $ids)
                ->selectRaw('fk_ordenadmin_id, COUNT(DISTINCT fk_ocupacion_id) AS n')->groupBy('fk_ordenadmin_id')->get() as $x) {
                $compartidas[(int) $x->fk_ordenadmin_id] = (int) $x->n;
            }
        }
        $proveedores = $this->nombresProveedor($rows->pluck('fk_proveedor_id')->all());

        $ordenes = [];
        foreach ($rows as $x) {
            $oid = (int) $x->fk_ordenadmin_id;
            $sid = (int) $x->fk_ocupacion_id;
            $s = $servicios[$sid];
            $fecha = $this->fecha($x->fecha);
            $moneda = (string) ($x->rel_moneda ?: $x->fk_moneda_id);
            $cot = (float) $x->cotizacion;
            [$tasaDoc, $origenDoc] = $this->tasas->delDocumento($moneda, $cot, $fecha, true);
            [$tasaItem, $origenItem] = $this->tasas->paraItem($s['moneda_costo'], $moneda, $cot, $fecha, true);
            $monto = (float) $x->rel_monto;
            $tipo = (string) $x->tipo;
            $anulada = (string) $x->status === 'AN';
            $comision = (string) ($x->rel_status ?? 'A') === 'C';

            $ordenes[$oid] ??= [
                'id' => $oid,
                'tipo' => $tipo,
                'numero' => $tipo === 'S' ? (string) $x->nroservicio : (string) $x->nropago,
                'fecha' => $fecha,
                'status' => (string) $x->status,
                'moneda' => (string) $x->fk_moneda_id,
                'cotizacion' => $cot,
                'cotizacion_os' => $cotOs[$oid] ?? null,
                'tasa' => $tasaDoc,
                'tasa_origen' => $origenDoc,
                'monto' => (float) $x->monto,
                'padre' => (int) $x->o_padre,
                'proveedor' => $proveedores[(int) $x->fk_proveedor_id] ?? '',
                'servicios_ajenos' => $compartidas[$oid] ?? 0,
                'items' => [],
                'link' => $tipo === 'S' ? "/administracion/ordenservicio/imprimir/{$oid}" : "/administracion/ordenpago/imprimir/{$oid}",
            ];
            $item = [
                'orden_id' => $oid,
                'numero' => $ordenes[$oid]['numero'],
                'fecha' => $fecha,
                'moneda' => $moneda,
                'monto' => $monto,
                'cantidad' => Tasas::cantidad($monto, $moneda, $tasaDoc, $s['moneda_costo'], $tasaItem),
                'tasa' => $tasaItem,
                'tasa_origen' => $origenItem,
                'base' => round($monto * $tasaDoc, $this->dec),
                'anulado' => $anulada,
                'comision' => $comision,
            ];
            $ordenes[$oid]['items'][] = $item + ['servicio_id' => $sid];

            if ($tipo === 'P' && ! $comision && (string) $x->status === 'OK') {
                $servicios[$sid]['pagos'][] = $item;
            } elseif ($tipo === 'P' && $comision && ! $anulada) {
                $servicios[$sid]['comisiones'][] = $item;
            } elseif ($tipo === 'S') {
                $servicios[$sid]['ordenes_servicio'][] = $item + ['status' => (string) $x->status];
            }
        }

        // ---- Facturas de proveedor ------------------------------------------
        $fcRows = DB::table('rel_facturaproveedorocupacion as r')
            ->join('facturaproveedor as f', 'f.facturaproveedor_id', '=', 'r.fk_facturaproveedor_id')
            ->whereIn('r.fk_ocupacion_id', $ids)
            ->orderBy('f.fecha')
            ->get(['r.fk_ocupacion_id', 'r.monto as rel_monto', 'f.facturaproveedor_id', 'f.facturaproveedor_nro', 'f.facturaproveedor_tipodocumento',
                'f.facturaproveedor_tipofactura', 'f.fecha', 'f.fk_moneda_id', 'f.cotizacion', 'f.montototal', 'f.fk_proveedor_id', 'f.tipomovimiento']);
        $fcIds = $fcRows->pluck('facturaproveedor_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $fcCompartidas = [];
        if ($fcIds !== []) {
            foreach (DB::table('rel_facturaproveedorocupacion')->whereIn('fk_facturaproveedor_id', $fcIds)->whereNotIn('fk_ocupacion_id', $ids)
                ->selectRaw('fk_facturaproveedor_id, COUNT(DISTINCT fk_ocupacion_id) AS n')->groupBy('fk_facturaproveedor_id')->get() as $x) {
                $fcCompartidas[(int) $x->fk_facturaproveedor_id] = (int) $x->n;
            }
        }
        $proveedores += $this->nombresProveedor($fcRows->pluck('fk_proveedor_id')->all());

        $fc3 = [];
        foreach ($fcRows as $x) {
            $fid = (int) $x->facturaproveedor_id;
            $sid = (int) $x->fk_ocupacion_id;
            $s = $servicios[$sid];
            $fecha = $this->fecha($x->fecha);
            $moneda = (string) $x->fk_moneda_id;
            $cot = (float) $x->cotizacion;
            // factura3ero.php:57 — la NC de proveedor resta.
            $signo = (string) $x->facturaproveedor_tipodocumento === 'Nota de Credito' ? -1 : 1;
            [$tasaDoc, $origenDoc] = $this->tasas->delDocumento($moneda, $cot, $fecha, true);
            [$tasaItem, $origenItem] = $this->tasas->paraItem($s['moneda_costo'], $moneda, $cot, $fecha, true);
            $monto = $signo * (float) $x->rel_monto;

            $fc3[$fid] ??= [
                'id' => $fid,
                'numero' => trim(($signo < 0 ? 'NC' : 'FC').' '.$x->facturaproveedor_tipofactura.' '.$x->facturaproveedor_nro),
                'fecha' => $fecha,
                'moneda' => $moneda,
                'cotizacion' => $cot,
                'tasa' => $tasaDoc,
                'tasa_origen' => $origenDoc,
                'total' => $signo * (float) $x->montototal,
                'tipomovimiento' => (string) $x->tipomovimiento,
                'proveedor' => $proveedores[(int) $x->fk_proveedor_id] ?? '',
                'servicios_ajenos' => $fcCompartidas[$fid] ?? 0,
                'items' => [],
                'link' => "/app/facturas-proveedor/{$fid}",
            ];
            $item = [
                'factura_id' => $fid,
                'numero' => $fc3[$fid]['numero'],
                'fecha' => $fecha,
                'moneda' => $moneda,
                'monto' => $monto,
                'cantidad' => Tasas::cantidad($monto, $moneda, $tasaDoc, $s['moneda_costo'], $tasaItem),
                'tasa' => $tasaItem,
                'tasa_origen' => $origenItem,
                'base' => round($monto * $tasaDoc, $this->dec),
            ];
            $fc3[$fid]['items'][] = $item + ['servicio_id' => $sid];
            $servicios[$sid]['fc3'][] = $item;
        }

        return [
            'ordenes' => array_values($ordenes),
            'facturas_proveedor' => array_values($fc3),
            'asientos' => $this->asientosCtaCte($files, 'fk_proveedor_id'),
        ];
    }

    // ------------------------------------------------------------------
    // Contabilidad
    // ------------------------------------------------------------------

    private function contabilidad(array $files, array $servicios, array $venta, array $costo): array
    {
        $claves = [
            'fk_factura_id' => array_column($venta['facturas'], 'id'),
            'fk_notacredito_id' => array_column($venta['notas_credito'], 'id'),
            'fk_notadebito_id' => array_column($venta['notas_debito'], 'id'),
            'fk_recibo_id' => array_values(array_unique(array_column($venta['recibos'], 'id'))),
            'fk_ordenadmin_id' => array_column($costo['ordenes'], 'id'),
            'fk_facturaproveedor_id' => array_column($costo['facturas_proveedor'], 'id'),
            'fk_file_id' => $files,
        ];

        $movs = [];
        foreach ($claves as $col => $ids) {
            foreach ($this->movimientos($col, $ids) as $m) {
                $movs[(int) $m->movimiento_id] = $m;
            }
        }
        // Asientos encontrados sólo por el file (C, A, M): completar con todas sus
        // líneas para poder verificar que balanceen.
        $ordenesDelFile = [];
        foreach ($movs as $m) {
            if ((int) $m->fk_ordenadmin_id !== 0 && ! in_array((int) $m->fk_ordenadmin_id, $claves['fk_ordenadmin_id'], true)) {
                $ordenesDelFile[(int) $m->fk_ordenadmin_id] = (int) $m->fk_ordenadmin_id;
            }
        }
        foreach ($this->movimientos('fk_ordenadmin_id', array_values($ordenesDelFile)) as $m) {
            $movs[(int) $m->movimiento_id] = $m;
        }
        // Ídem para las líneas sueltas que sólo tienen número de asiento. El 0 no es
        // un asiento: traerlo sería leer media tabla.
        $asientos = [];
        foreach ($movs as $m) {
            if ($this->claveGrupo($m)[1] === 'AS' && (int) $m->fk_asientocontable_id !== 0) {
                $asientos[(int) $m->fk_asientocontable_id] = (int) $m->fk_asientocontable_id;
            }
        }
        foreach ($this->movimientos('fk_asientocontable_id', array_values($asientos)) as $m) {
            if ($this->claveGrupo($m)[1] === 'AS') {
                $movs[(int) $m->movimiento_id] = $m;
            }
        }
        ksort($movs);

        $cuentas = [];
        foreach ($movs as $m) {
            $cuentas[$this->cuentaDe($m)] = true;
        }
        $plan = DB::table('plancuenta')->whereIn('plancuenta_id', array_keys($cuentas))
            ->get(['plancuenta_id', 'plancuenta_codigo', 'plancuenta_nombre'])->keyBy('plancuenta_id');

        $etiquetas = $this->etiquetasComprobantes($venta, $costo);
        $compartidos = $this->comprobantesCompartidos($venta, $costo);

        // Cuentas de renta (unión sysconfig + submódulo, como Analitica_model).
        [$ctasRenta, $ambiguas] = $this->cuentasRenta();
        $porServicio = [];
        foreach ($servicios as $s) {
            $porServicio[$s['id']] = $s;
        }

        $grupos = [];
        $rentaContable = 0.0;
        $movRenta = 0;
        $movAmbiguos = 0;
        $difCambioAsentada = 0.0;
        $ctasDifCambio = [];

        foreach ($movs as $m) {
            $cta = $this->cuentaDe($m);
            $pc = $plan[$cta] ?? null;
            $valido = (float) $m->monto != 0.0
                && in_array((int) ($m->auxiliar ?? 0), [0, 1], true)
                && ($m->oa_status === null || $m->oa_status !== 'AN')
                && ($m->rc_status === null || $m->rc_status !== 'AN');
            $signo = $this->signo($m);
            $fecha = $this->fecha($m->fecha);
            [$tasa, $origen] = $this->tasas->delDocumento((string) $m->fk_moneda_id, (float) $m->cotizacion_moneda, $fecha);
            $base = round((float) $m->monto * $tasa, $this->dec);
            [$clave, $tipo, $id] = $this->claveGrupo($m);

            $grupos[$clave] ??= [
                'clave' => $clave,
                'tipo' => $tipo,
                'id' => $id,
                'etiqueta' => $etiquetas[$clave]['etiqueta'] ?? $this->etiquetaPorDefecto($tipo, $id, $m),
                'link' => $etiquetas[$clave]['link'] ?? null,
                'orden_tipo' => $tipo === 'OR' ? (string) ($m->oa_tipo ?? '') : '',
                'compartido' => isset($compartidos[$clave]),
                'debe' => 0.0,
                'haber' => 0.0,
                'movimientos' => [],
            ];
            if ((int) $m->fk_file_id !== 0 && ! in_array((int) $m->fk_file_id, $files, true)) {
                $grupos[$clave]['compartido'] = true;
            }
            if ($valido) {
                $grupos[$clave][$signo > 0 ? 'debe' : 'haber'] += $base;
            }
            $grupos[$clave]['movimientos'][] = [
                'id' => (int) $m->movimiento_id,
                'fecha' => $fecha,
                'cuenta' => $cta,
                'cuenta_codigo' => (string) ($pc->plancuenta_codigo ?? ''),
                'cuenta_nombre' => (string) ($pc->plancuenta_nombre ?? ''),
                'dh' => $signo > 0 ? 'D' : 'H',
                'moneda' => (string) $m->fk_moneda_id,
                'monto' => (float) $m->monto,
                'cotizacion' => (float) $m->cotizacion_moneda,
                'tasa_origen' => $origen,
                'base' => $base,
                'file_id' => (int) $m->fk_file_id,
                'descripcion' => (string) ($m->descripcion ?? ''),
                'valido' => $valido,
            ];

            if (! $valido) {
                continue;
            }
            if (stripos((string) ($pc->plancuenta_nombre ?? ''), 'DIF') !== false && stripos((string) ($pc->plancuenta_nombre ?? ''), 'CAMBIO') !== false) {
                // Cuenta de resultado: el haber es ganancia.
                $difCambioAsentada += -$signo * $base;
                $ctasDifCambio[$cta] = trim(($pc->plancuenta_codigo ?? '').' '.($pc->plancuenta_nombre ?? ''));
            }
            if (in_array((int) $m->fk_file_id, $files, true) && in_array((int) $m->fk_plancuenta_id, $ctasRenta, true) && ! $this->esApertura($m)) {
                $decision = $this->rentaContableCuenta($m, $ambiguas, $porServicio);
                if ($decision === 'ambiguo') {
                    $movAmbiguos++;
                }
                if ($decision !== 'excluir') {
                    $rentaContable += -$signo * $base;
                    $movRenta++;
                }
            }
        }

        foreach ($grupos as &$g) {
            $g['debe'] = round($g['debe'], $this->dec);
            $g['haber'] = round($g['haber'], $this->dec);
            $g['diferencia'] = round($g['debe'] - $g['haber'], $this->dec);
        }
        unset($g);

        return [
            'grupos' => array_values($grupos),
            'renta' => $ctasRenta === [] ? null : round($rentaContable, $this->dec),
            'renta_movimientos' => $movRenta,
            'renta_ambigua' => $movAmbiguos > 0,
            'movimientos_ambiguos' => $movAmbiguos,
            'sin_cuentas_renta' => $ctasRenta === [],
            'dif_cambio_asentada' => round($difCambioAsentada, $this->dec),
            'cuentas_dif_cambio' => array_values($ctasDifCambio),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    private function movimientos(string $col, array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('movimiento')
            ->leftJoin('ordenadmin as oa', 'oa.ordenadmin_id', '=', 'movimiento.fk_ordenadmin_id')
            ->leftJoin('recibo as rc', 'rc.recibo_id', '=', 'movimiento.fk_recibo_id')
            ->whereIn("movimiento.{$col}", $ids)
            ->get(['movimiento.*', 'oa.status as oa_status', 'oa.tipo as oa_tipo', 'oa.nropago as oa_nro', 'oa.observaciones as oa_obs', 'rc.statusrecibo as rc_status']);
    }

    private function cuentaDe(object $m): int
    {
        if ((int) $m->fk_plancuenta_id !== 0) {
            return (int) $m->fk_plancuenta_id;
        }

        return (int) $m->cuenta_debito ?: (int) $m->cuenta_credito;
    }

    /**
     * +1 debe, −1 haber. Analitica_model::sql_signo_movimiento(): `deha` si está;
     * si no, derivado de las columnas con la inversión de los asientos de orden
     * (asientocontable.php graba las columnas cruzadas).
     */
    private function signo(object $m): int
    {
        $deha = (string) ($m->deha ?? '');
        if ($deha !== '') {
            return $deha === 'D' ? 1 : -1;
        }
        $cd = (int) $m->cuenta_debito;
        $cc = (int) $m->cuenta_credito;
        $s = ($cc !== 0 && $cd !== 0) ? 1 : ($cc !== 0 ? -1 : ($cd !== 0 ? 1 : -1));

        return (int) $m->fk_ordenadmin_id !== 0 ? -$s : $s;
    }

    /** @return array{0:string,1:string,2:int} */
    private function claveGrupo(object $m): array
    {
        foreach ([['fk_notadebito_id', 'ND'], ['fk_notacredito_id', 'NC'], ['fk_recibo_id', 'RC'],
            ['fk_facturaproveedor_id', 'FP'], ['fk_ordenadmin_id', 'OR'], ['fk_factura_id', 'FC']] as [$col, $tipo]) {
            $id = (int) ($m->{$col} ?? 0);
            if ($id !== 0) {
                return ["{$tipo}:{$id}", $tipo, $id];
            }
        }
        $as = (int) ($m->fk_asientocontable_id ?? 0);

        return ["AS:{$as}", 'AS', $as];
    }

    private function etiquetasComprobantes(array $venta, array $costo): array
    {
        $out = [];
        foreach ($venta['facturas'] as $d) {
            $out["FC:{$d['id']}"] = ['etiqueta' => "Factura {$d['numero']}", 'link' => $d['link']];
        }
        foreach ($venta['notas_credito'] as $d) {
            $out["NC:{$d['id']}"] = ['etiqueta' => "Nota de crédito {$d['numero']}", 'link' => $d['link']];
        }
        foreach ($venta['notas_debito'] as $d) {
            $out["ND:{$d['id']}"] = ['etiqueta' => "Nota de débito {$d['numero']}", 'link' => $d['link']];
        }
        foreach ($venta['recibos'] as $d) {
            $out["RC:{$d['id']}"] = ['etiqueta' => "Recibo {$d['numero']}", 'link' => $d['link']];
        }
        foreach ($costo['ordenes'] as $d) {
            $out["OR:{$d['id']}"] = ['etiqueta' => ($d['tipo'] === 'S' ? 'Orden de servicio ' : 'Orden de pago ').$d['numero'], 'link' => $d['link']];
        }
        foreach ($costo['facturas_proveedor'] as $d) {
            $out["FP:{$d['id']}"] = ['etiqueta' => "Factura de proveedor {$d['numero']}", 'link' => $d['link']];
        }

        return $out;
    }

    private function etiquetaPorDefecto(string $tipo, int $id, object $m): string
    {
        if ($tipo === 'OR') {
            $nombres = ['P' => 'Orden de pago', 'C' => 'Asiento en cta. cte.', 'A' => 'Asiento manual', 'M' => 'Movimiento de fondos', 'S' => 'Orden de servicio'];

            return ($nombres[(string) $m->oa_tipo] ?? 'Orden').' '.($m->oa_nro ?? $id);
        }

        return $tipo === 'AS' ? "Asiento #{$id}" : "{$tipo} #{$id}";
    }

    private function comprobantesCompartidos(array $venta, array $costo): array
    {
        $out = [];
        foreach ($venta['facturas'] as $d) {
            if ($d['otros_files'] !== [] || $d['servicios_ajenos'] !== []) {
                $out["FC:{$d['id']}"] = true;
            }
        }
        foreach ($venta['recibos'] as $d) {
            if ($d['otros_files'] !== []) {
                $out["RC:{$d['id']}"] = true;
            }
        }
        foreach ($costo['ordenes'] as $d) {
            if ($d['servicios_ajenos'] > 0) {
                $out["OR:{$d['id']}"] = true;
            }
        }
        foreach ($costo['facturas_proveedor'] as $d) {
            if ($d['servicios_ajenos'] > 0) {
                $out["FP:{$d['id']}"] = true;
            }
        }

        return $out;
    }

    /**
     * Cuentas de renta y cuentas ambiguas — Analitica_model::cuentas_renta() y
     * cuentas_ambiguas(): unión de sysconfig y submódulo; la cuenta puente siempre
     * es ambigua.
     *
     * @return array{0:list<int>,1:array<int,bool>}
     */
    private function cuentasRenta(): array
    {
        $renta = $this->cuentasConfig(['ventarentaa', 'ventarentat'], 'cuenta_renta');
        $costo = $this->cuentasConfig(['costoaereo', 'costoterrestre'], 'fk_plancuenta_id');
        $ambiguas = [self::CUENTA_PUENTE => true];
        foreach ($renta as $c) {
            if (in_array($c, $costo, true)) {
                $ambiguas[$c] = true;
            }
        }

        return [$renta, $ambiguas];
    }

    /** @return list<int> */
    private function cuentasConfig(array $claves, string $columnaSubmodulo): array
    {
        $out = [];
        foreach (DB::table('sysconfig')->whereIn('sysconfig_key', $claves)->pluck('sysconfig_value') as $v) {
            if ((int) $v !== 0) {
                $out[(int) $v] = (int) $v;
            }
        }
        if (Schema::hasColumn('submodulo', $columnaSubmodulo)) {
            foreach (DB::table('submodulo')->where($columnaSubmodulo, '<>', 0)->where($columnaSubmodulo, '<>', self::CUENTA_PUENTE)->distinct()->pluck($columnaSubmodulo) as $v) {
                $out[(int) $v] = (int) $v;
            }
        }

        return array_values($out);
    }

    /**
     * Si un movimiento en cuenta de renta cuenta como renta del file. Misma regla
     * que el conciliador: la ambigüedad de una cuenta de doble rol se decide por
     * los servicios del propio file.
     *
     * @return string 'incluir' | 'excluir' | 'ambiguo'
     */
    private function rentaContableCuenta(object $m, array $ambiguas, array $servicios): string
    {
        $cta = (int) $m->fk_plancuenta_id;
        if (! isset($ambiguas[$cta])) {
            return 'incluir';
        }
        $fs = (int) ($m->filtro_servicio ?? 0);
        if ($fs > 0) {
            $cr = $servicios[$fs]['cuenta_renta'] ?? (int) DB::table('servicio')
                ->join('submodulo', 'submodulo.tipoproducto_id', '=', 'servicio.fk_tipoproducto_id')
                ->where('servicio.servicio_id', $fs)->value('submodulo.cuenta_renta');

            return $cr === $cta ? 'incluir' : 'excluir';
        }
        $usaRenta = false;
        $mandaCosto = false;
        foreach ($servicios as $s) {
            if ($s['cancelado'] || $s['file_id'] !== (int) $m->fk_file_id) {
                continue;
            }
            $usaRenta = $usaRenta || $s['cuenta_renta'] === $cta;
            $mandaCosto = $mandaCosto || $s['cuenta_costo'] === $cta;
        }
        if (! $usaRenta) {
            return 'excluir';
        }

        return $mandaCosto ? 'ambiguo' : 'incluir';
    }

    /** Asientos manuales de apertura/cierre: heurística por texto, como el conciliador. */
    private function esApertura(object $m): bool
    {
        if ((string) ($m->oa_tipo ?? '') !== 'A') {
            return false;
        }
        $txt = strtoupper(($m->oa_obs ?? '').' '.($m->descripcion ?? ''));

        return str_contains($txt, 'APERTURA') || str_contains($txt, 'CIERRE');
    }

    // ------------------------------------------------------------------
    // Totales de comprobantes — mismas fórmulas que los listados de
    // Documentos (que replican los del CI): NC y ND emitidas desde sus módulos
    // no graban *_total (notacredito.php:449, notadebito.php:469).
    // ------------------------------------------------------------------

    private function totalFactura(object $f): float
    {
        $t = round((float) $f->factura_conceptos_gravados * $this->coef + (float) $f->factura_conceptos_gravadosespecial * 1.105
            + (float) $f->factura_conceptos_exentos + (float) $f->factura_conceptos_nogravados
            + (float) $f->factura_impuesto1 + (float) $f->factura_impuesto2 + (float) $f->factura_impuesto3
            + (float) $f->factura_impuesto4 + (float) $f->factura_impuesto5, $this->dec);
        if (substr((string) $f->factura_fecha, 0, 10) > '2015-12-17') {
            $t += (float) $f->factura_rgterrestres;
        }

        return round($t - $this->ivatur($f->remitofull ?? ''), $this->dec);
    }

    private function totalNotaCredito(object $n): float
    {
        return round((float) $n->notacredito_conceptos_gravados * $this->coef + (float) $n->notacredito_conceptos_gravadosespecial * 1.105
            + (float) $n->notacredito_conceptos_exentos + (float) $n->notacredito_conceptos_nogravados + (float) $n->notacredito_rgterrestres
            + (float) $n->notacredito_impuesto1 + (float) $n->notacredito_impuesto2 + (float) $n->notacredito_impuesto3
            + (float) $n->notacredito_impuesto4 + (float) $n->notacredito_impuesto5 + $this->ivatur($n->remitofull ?? ''), $this->dec);
    }

    private function totalNotaDebito(object $d): float
    {
        return round((float) $d->notadebito_conceptos_gravados * $this->coef + (float) $d->notadebito_conceptos_gravadosespecial * 1.105
            + (float) $d->notadebito_conceptos_exentos + (float) $d->notadebito_conceptos_nogravados + (float) $d->notadebito_rgterrestres, $this->dec);
    }

    /** Tercer elemento de remitofull ('a:b:ivatur'). */
    private function ivatur(?string $remitofull): float
    {
        $p = explode(':', (string) $remitofull);

        return (float) ($p[2] ?? 0);
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    /** File + agrupados en él (fk_agrupado_id), en profundidad. CI buscarhijos(). */
    private function files(int $id): array
    {
        $acc = [$id => $id];
        $pendientes = [$id];
        while ($pendientes !== []) {
            $hijos = DB::table('reserva')->whereIn('fk_agrupado_id', $pendientes)->pluck('reserva_id')->map(fn ($v) => (int) $v)->all();
            $pendientes = array_values(array_diff($hijos, $acc));
            foreach ($pendientes as $h) {
                $acc[$h] = $h;
            }
        }

        return array_values($acc);
    }

    private function otrosFiles(array $filesDoc, array $files): array
    {
        return array_values(array_diff($filesDoc, $files));
    }

    /** @return list<int> cuentas de recibos (Admin_Controller.php:606-621) */
    private function ctasRecibos(): array
    {
        $ctas = [];
        foreach (DB::table('sysconfig')->whereIn('sysconfig_key', ['cuentarecibos', 'cuentarecibosusd', 'anticiporecibos', 'anticiporecibosusd', 'auxrecibos'])->pluck('sysconfig_value') as $v) {
            if ((int) $v !== 0) {
                $ctas[(int) $v] = (int) $v;
            }
        }

        return $ctas === [] ? [54] : array_values($ctas);
    }

    private function nombresProveedor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return DB::table('proveedor')->whereIn('proveedor_id', $ids)->pluck('proveedor_nombre', 'proveedor_id')->map(fn ($v) => (string) $v)->all();
    }

    private function hayTabla(string $tabla): bool
    {
        return $this->tablas[$tabla] ??= Schema::hasTable($tabla);
    }

    private function fecha(mixed $v): ?string
    {
        $s = substr((string) $v, 0, 10);

        return ($s === '' || str_starts_with($s, '0000')) ? null : $s;
    }

    /** Decimales: 0 en CL (salvo mviajes), 2 en el resto (DocumentoListadoController). */
    private function decimales(): int
    {
        if (Licencia::pais() === 'CL') {
            return Licencia::es('witwan_mviajes') ? 2 : 0;
        }

        return 2;
    }

    /** Coeficiente de IVA general (tasageneral como 21 o 0.21), igual que `$this->coef` del CI. */
    private function coef(): float
    {
        $tasa = (float) Licencia::sysconfig('tasageneral', 21);

        return 1 + ($tasa < 1 ? $tasa : $tasa / 100);
    }
}
