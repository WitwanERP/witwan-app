<?php

namespace App\Services\Reservas\MapaContable;

/**
 * Reglas de desvío sobre el mapa contable ya armado. Puro: no consulta la base.
 *
 * Cada desvío dice qué pasa, por qué importa y, cuando se puede, cuánto vale en
 * moneda básica. Severidad:
 *  - alta  : el número del file está mal o falta un registro obligatorio.
 *  - media : hay una diferencia de plata que alguien tiene que mirar.
 *  - info  : contexto para leer los números (inferencias, pendientes, comprobantes compartidos).
 */
final class DesviosFile
{
    private const ORDEN = ['alta' => 0, 'media' => 1, 'info' => 2];

    private float $tol = 1.0;

    private int $dec = 2;

    private string $basica = 'ARS';

    /** @var list<array<string,mixed>> */
    private array $out = [];

    public function evaluar(array $m): array
    {
        $this->tol = (float) $m['tolerancia'];
        $this->dec = (int) $m['decimales'];
        $this->basica = (string) $m['moneda_basica'];
        $this->out = [];

        $this->venta($m);
        $this->costo($m);
        $this->cambio($m);
        $this->renta($m);
        $this->contable($m);

        usort($this->out, fn ($a, $b) => [self::ORDEN[$a['severidad']], $a['area']] <=> [self::ORDEN[$b['severidad']], $b['area']]);

        return $this->out;
    }

    // ------------------------------------------------------------------

    private function venta(array $m): void
    {
        $sinFactura = [];
        foreach ($m['servicios'] as $s) {
            if ($s['cancelado'] && $s['factura_vigente'] !== null) {
                $this->agregar('SRV_CANCELADO_FACTURADO', 'alta', 'venta', "Servicio cancelado con factura vigente: {$s['nombre']}",
                    'El servicio está cancelado pero sigue facturado y la factura no fue anulada con una NC.', null, ['servicio', $s['id']]);
            }
            if (! $s['cancelado'] && $s['factura_vigente'] === null) {
                $sinFactura[] = $s['nombre'];
                if ($s['facturado_flag'] === 1) {
                    $this->agregar('SRV_MARCADO_FACTURADO', 'media', 'venta', "Marcado como facturado sin factura vigente: {$s['nombre']}",
                        'servicio.facturado = 1 pero no hay ninguna factura no anulada vinculada. Los listados de "a facturar" no lo van a mostrar.', null, ['servicio', $s['id']]);
                }
            }
        }
        if ($sinFactura !== []) {
            $this->agregar('SRV_SIN_FACTURA', 'info', 'venta', count($sinFactura).' servicio(s) sin factura vigente',
                implode(' · ', $sinFactura).'. Su renta se toma a la cotización del servicio hasta que se facturen.');
        }

        foreach ($m['venta']['facturas'] as $f) {
            if ($f['status'] === 'NU') {
                continue;
            }
            if (! in_array('rel_filefactura', $f['caminos'], true)) {
                $this->agregar('FACTURA_FUERA_DE_FICHA', 'media', 'venta', "Factura {$f['numero']} no figura en rel_filefactura",
                    'La ficha del file en el CI (get_facturas) sólo lee esa tabla, así que no la muestra. Apareció por '.$this->caminos($f['caminos']).'.', null, ['factura', $f['id']]);
            }
            if (! in_array('servicio', $f['caminos'], true)) {
                $this->agregar('FACTURA_SIN_SERVICIOS', 'media', 'venta', "Factura {$f['numero']} sin servicios vinculados",
                    'No tiene filas en rel_serviciofactura ni serviciofactura: no se sabe qué facturó, y su importe entra entero en la diferencia de venta.', null, ['factura', $f['id']]);
            }
            if ($f['total_grabado'] != 0.0 && abs($f['total_grabado'] - $f['total']) > $this->tol) {
                $this->agregar('FACTURA_TOTAL_GRABADO', 'info', 'venta', "Factura {$f['numero']}: el total grabado no coincide con sus conceptos",
                    "factura_total = {$this->num($f['total_grabado'])}; por conceptos (como el listado de facturas) = {$this->num($f['total'])}.", null, ['factura', $f['id']]);
            }
        }
        foreach ($m['venta']['notas_credito'] as $n) {
            if ($n['status'] !== 'NU' && $n['fk_file_id'] === 0) {
                $this->agregar('NC_SIN_FILE', 'info', 'venta', "Nota de crédito {$n['numero']} sin file",
                    'Se emitió desde el módulo de notas de crédito, que no graba el file: sólo se la encuentra por su factura. Los reportes que buscan NC por file no la ven.', null, ['nc', $n['id']]);
            }
        }

        $v = $m['economia']['venta'];
        if (abs($v['diferencia_bruta']) > $this->tol) {
            $this->agregar('DIF_PRECIO_VENTA', 'media', 'venta', 'Lo facturado no coincide con los servicios facturados',
                "Comprobantes (FC − NC + ND, parte de este file) = {$this->num($v['documental'])}; servicios facturados a la tasa de su factura = {$this->num($v['esperada'])}. "
                .'Puede ser un descuento o recargo hecho en la factura, una NC parcial o un servicio modificado después de facturar.',
                $m['economia']['totales']['dif_precio_venta']);
        }

        // Saldo del cliente: en la moneda extranjera de facturación si la hay; si no, en básica.
        $c = $m['economia']['cobranza'];
        $hayDocs = $m['venta']['facturas'] !== [] || $m['venta']['recibos'] !== [];
        if ($hayDocs) {
            [$fact, $cob, $mon] = $c['moneda'] !== null
                ? [$c['facturado'], $c['cobrado'], $c['moneda']]
                : [$v['documental'], $m['economia']['totales']['cobrado'], $this->basica];
            $saldo = $fact - $cob;
            if ($saldo > $this->tol) {
                $this->agregar('SALDO_CLIENTE', 'info', 'venta', "Falta cobrar {$mon} {$this->num($saldo)}",
                    "Facturado {$mon} {$this->num($fact)}, cobrado {$mon} {$this->num($cob)}.");
            } elseif ($saldo < -$this->tol) {
                $this->agregar('COBRADO_SIN_FACTURAR', 'media', 'venta', "Se cobró {$mon} {$this->num(-$saldo)} más de lo facturado",
                    "Facturado {$mon} {$this->num($fact)}, cobrado {$mon} {$this->num($cob)}. Es un anticipo o falta emitir una factura.");
            }
        }

        foreach ($m['venta']['recibos'] as $r) {
            if ($r['duplicado']) {
                $this->agregar('RECIBO_IMPUTADO_DOS_VECES', 'media', 'venta', "Recibo {$r['numero']} imputado más de una vez al file",
                    'rel_filerecibo tiene filas repetidas para este recibo y este file. cobrado() las agrupa y lo cuenta una vez; otros reportes que suman la tabla sin agrupar lo cuentan doble.');
            }
        }

        if ($m['venta']['aplicaciones_disponibles']) {
            $aplicados = array_flip(array_column($m['venta']['aplicaciones'], 'recibo'));
            $sinAplicar = array_values(array_unique(array_map(fn ($r) => $r['numero'],
                array_filter($m['venta']['recibos'], fn ($r) => ! $r['anulado'] && ! isset($aplicados[$r['id']])))));
            if ($sinAplicar !== [] && $m['venta']['facturas'] !== []) {
                $this->agregar('RECIBO_SIN_APLICAR', 'info', 'venta', count($sinAplicar).' recibo(s) sin aplicar a facturas',
                    'Recibos '.implode(', ', $sinAplicar).' imputados al file pero sin fila en rel_facturarecibo: la factura figura impaga en los reportes de facturas impagas.');
            }
        }

        $compartidos = [];
        foreach ($m['venta']['facturas'] as $f) {
            if ($f['otros_files'] !== [] || $f['servicios_ajenos'] !== []) {
                $compartidos[] = "Factura {$f['numero']} (".round($f['share'] * 100).'% de este file)';
            }
        }
        foreach ($m['venta']['recibos'] as $r) {
            if ($r['otros_files'] !== []) {
                $compartidos[] = "Recibo {$r['numero']}";
            }
        }
        foreach ($m['costo']['ordenes'] as $o) {
            if ($o['servicios_ajenos'] > 0) {
                $compartidos[] = ($o['tipo'] === 'S' ? 'OS ' : 'OP ').$o['numero'];
            }
        }
        foreach ($m['costo']['facturas_proveedor'] as $f) {
            if ($f['servicios_ajenos'] > 0) {
                $compartidos[] = "Factura de proveedor {$f['numero']}";
            }
        }
        if ($compartidos !== []) {
            $this->agregar('COMPROBANTES_COMPARTIDOS', 'info', 'venta', count($compartidos).' comprobante(s) compartidos con otros files',
                implode(' · ', array_unique($compartidos)).'. En las facturas se toma sólo la parte de este file, prorrateada por sus servicios.');
        }
    }

    private function costo(array $m): void
    {
        $eco = $m['economia']['servicios'];
        $pendientes = [];

        foreach ($m['servicios'] as $s) {
            $e = $eco[$s['id']] ?? null;
            if ($e === null) {
                continue;
            }
            $q = $e['cantidades'];
            $mc = $s['moneda_costo'];
            $tieneFc3 = $s['fc3'] !== [];
            $pagos = array_filter($s['pagos'], fn ($p) => ! $p['anulado']);

            if ($s['cancelado'] && ($tieneFc3 || $pagos !== [])) {
                $this->agregar('COSTO_EN_CANCELADO', 'media', 'costo', "Costo real sobre un servicio cancelado: {$s['nombre']}",
                    "Tiene factura de proveedor o pagos ({$mc} {$this->num($tieneFc3 ? $q['facturado_proveedor'] : $q['pagado'])}) y no suma venta. Si es una penalidad, conviene que esté cargada como servicio.",
                    -($e['costo_real']), ['servicio', $s['id']]);

                continue;
            }
            if ($tieneFc3 && abs($e['dif_precio_costo']) > $this->tol) {
                $menos = $q['facturado_proveedor'] < $q['costo_operado'];
                $this->agregar('FC3_DIF_COSTO', 'media', 'costo', "El proveedor facturó distinto del costo cargado: {$s['nombre']}",
                    "Costo cargado {$mc} {$this->num($q['costo_operado'])}; facturado por el proveedor {$mc} {$this->num($q['facturado_proveedor'])}."
                    .($menos ? ' O el proveedor cobró menos, o todavía falta que facture una parte.' : ' La renta presupuestada estaba sobrestimada.'),
                    $e['dif_precio_costo'], ['servicio', $s['id']]);
            }
            if (! $tieneFc3 && $pagos !== []) {
                $this->agregar('PAGADO_SIN_FC3', 'media', 'costo', "Pagado sin factura del proveedor: {$s['nombre']}",
                    "Se pagaron {$mc} {$this->num($q['pagado'])} y no hay factura de proveedor imputada al servicio.", null, ['servicio', $s['id']]);
            }
            $deuda = $tieneFc3 ? $q['facturado_proveedor'] : $q['costo_operado'];
            if ($q['pagado'] > $deuda + $this->tol) {
                $this->agregar('PAGADO_DE_MAS', 'alta', 'costo', 'Se pagó más de lo '.($tieneFc3 ? 'facturado por el proveedor' : 'cargado como costo').": {$s['nombre']}",
                    "Pagado {$mc} {$this->num($q['pagado'])}; ".($tieneFc3 ? 'facturado' : 'costo')." {$mc} {$this->num($deuda)}. Queda un saldo a favor con el proveedor o hay un pago duplicado.",
                    null, ['servicio', $s['id']]);
            } elseif ($q['saldo_proveedor'] > $this->tol) {
                $pendientes[] = "{$s['nombre']} ({$mc} {$this->num($q['saldo_proveedor'])})";
            }
            foreach ($s['ordenes_servicio'] as $os) {
                if ($os['status'] === 'OK') {
                    $this->agregar('OS_PENDIENTE', 'info', 'costo', "Orden de servicio {$os['numero']} autorizada sin pagar",
                        "{$s['nombre']}: {$os['moneda']} {$this->num($os['monto'])}.", null, ['servicio', $s['id']]);
                }
            }
        }
        if ($pendientes !== []) {
            $this->agregar('SALDO_PROVEEDOR', 'info', 'costo', count($pendientes).' servicio(s) con saldo a pagar', implode(' · ', $pendientes).'.');
        }

        foreach ($m['costo']['ordenes'] as $o) {
            if ($o['tipo'] === 'P' && $o['status'] === 'OK' && $o['moneda'] !== $this->basica
                && ($o['cotizacion_os'] ?? 0) > 0 && $o['cotizacion'] > 0 && abs($o['cotizacion_os'] - $o['cotizacion']) > 0.0001) {
                $this->agregar('OP_COTIZACION_OS', 'info', 'costo', "OP {$o['numero']}: la ficha del CI valúa el pago a otra cotización",
                    "La OP se asentó a {$this->tasa($o['cotizacion'])}, pero reserva_model::pagado() usa la de la orden de servicio que la originó ({$this->tasa($o['cotizacion_os'])}). Acá se usa la de la OP.");
            }
        }
    }

    private function cambio(array $m): void
    {
        $inferidas = [];
        foreach ($m['servicios'] as $s) {
            if ($s['cancelado']) {
                continue;
            }
            foreach ([['cv_origen', 'venta', 'moneda_venta'], ['cc_origen', 'costo', 'moneda_costo']] as [$campo, $lado, $mon]) {
                if ($s[$campo] === Tasas::FALTANTE) {
                    $this->agregar('SIN_COTIZACION', 'alta', 'cambio', "Sin cotización de {$lado}: {$s['nombre']}",
                        "El servicio está en {$s[$mon]}, no tiene cot{$lado} y no hay cotización cargada a la fecha de alta: su {$lado} vale 0 en moneda básica.", null, ['servicio', $s['id']]);
                } elseif ($s[$campo] === Tasas::TABLA) {
                    $inferidas[] = "cotización de {$lado} de {$s['nombre']} (tabla a la fecha de alta)";
                }
            }
            if (in_array($s['tasa_factura_origen'], [Tasas::TABLA, Tasas::IMPLICITA], true)) {
                $inferidas[] = "TC de facturación de {$s['nombre']} (".($s['tasa_factura_origen'] === Tasas::IMPLICITA ? 'deducido del total' : 'tabla').')';
            }
            foreach (array_merge($s['fc3'], $s['pagos']) as $i) {
                if ($i['tasa_origen'] === Tasas::FALTANTE) {
                    $this->agregar('SIN_COTIZACION_DOC', 'alta', 'cambio', "Comprobante {$i['numero']} sin cotización",
                        "No se pudo llevar a {$s['moneda_costo']} el importe {$i['moneda']} {$this->num($i['monto'])} imputado a {$s['nombre']}.", null, ['servicio', $s['id']]);
                } elseif ($i['tasa_origen'] === Tasas::TABLA) {
                    $inferidas[] = "TC de {$i['numero']} (tabla a su fecha)";
                }
            }
        }
        foreach (array_merge($m['venta']['facturas'], $m['venta']['notas_credito'], $m['venta']['notas_debito'], $m['venta']['recibos']) as $d) {
            if ($d['tasa_origen'] === Tasas::FALTANTE) {
                $this->agregar('SIN_COTIZACION_DOC', 'alta', 'cambio', "Comprobante {$d['numero']} en {$d['moneda']} sin cotización",
                    'No tiene tipo de cambio grabado ni hay cotización cargada a su fecha: vale 0 en moneda básica.');
            } elseif ($d['tasa_origen'] === Tasas::TABLA) {
                $inferidas[] = "TC de {$d['numero']} (tabla a su fecha)";
            }
        }
        if ($inferidas !== []) {
            $this->agregar('COTIZACION_INFERIDA', 'info', 'cambio', count(array_unique($inferidas)).' tipo(s) de cambio inferidos',
                'No estaban grabados en el comprobante: '.implode(' · ', array_unique($inferidas)).'.');
        }

        $c = $m['economia']['cobranza'];
        if ($c['motivo'] === 'varias_monedas') {
            $this->agregar('COBRANZA_VARIAS_MONEDAS', 'info', 'cambio', 'Diferencia de cambio de cobranza no calculada',
                'El file se facturó en más de una moneda extranjera y no se puede saber qué cobro canceló cada factura.');
        } elseif ($c['motivo'] === 'mixta') {
            $this->agregar('COBRANZA_MIXTA', 'info', 'cambio', 'Diferencia de cambio de cobranza aproximada',
                "Hay facturas en {$c['moneda']} y en moneda básica: se supone que los cobros cancelan primero lo facturado en {$c['moneda']}.");
        }

        $t = $m['economia']['totales'];
        $asentada = (float) $m['contable']['dif_cambio_asentada'];
        if (abs($t['resultado_cambio']) > $this->tol) {
            if (abs($asentada) <= $this->tol) {
                $this->agregar('DIF_CAMBIO_NO_ASENTADA', 'media', 'cambio', 'Hay diferencia de cambio y no está asentada',
                    "El file tiene {$this->basica} {$this->num($t['resultado_cambio'])} de diferencias de cambio y ninguno de sus asientos toca una cuenta de diferencia de cambio. "
                    .'factura.php la calcula al facturar pero no la graba.', $t['resultado_cambio']);
            } elseif (abs($t['resultado_cambio'] - $asentada) > $this->tol) {
                $this->agregar('DIF_CAMBIO_DISTINTA', 'info', 'cambio', 'La diferencia de cambio asentada no coincide con la calculada',
                    "Calculada {$this->num($t['resultado_cambio'])}; asentada en ".implode(', ', $m['contable']['cuentas_dif_cambio'])." {$this->num($asentada)}.",
                    $t['resultado_cambio'] - $asentada);
            }
        }
    }

    private function renta(array $m): void
    {
        $eco = $m['economia']['servicios'];
        foreach ($m['servicios'] as $s) {
            $e = $eco[$s['id']] ?? null;
            if ($e === null || $s['cancelado']) {
                continue;
            }
            if ($e['renta_real'] < -$this->tol) {
                $this->agregar('RENTA_NEGATIVA', 'media', 'renta', "Servicio a pérdida: {$s['nombre']}",
                    "Renta real {$this->num($e['renta_real'])} (presupuestada {$this->num($e['renta_presupuestada'])}).", $e['renta_real'], ['servicio', $s['id']]);
            }
            if ($e['renta_congelada'] !== null && abs($e['renta_congelada'] - $e['renta_presupuestada']) > $this->tol) {
                $this->agregar('RENTA_CONGELADA_DISTINTA', 'media', 'renta', "La renta congelada al facturar no es la del servicio: {$s['nombre']}",
                    "Congelada en la factura {$this->num($e['renta_congelada'])}; con los importes actuales del servicio {$this->num($e['renta_presupuestada'])}. "
                    .'El servicio se modificó después de facturar, o al facturar se usó otra cotización.', $e['renta_presupuestada'] - $e['renta_congelada'], ['servicio', $s['id']]);
            }
        }

        $co = $m['contable'];
        $t = $m['economia']['totales'];
        if ($co['sin_cuentas_renta']) {
            $this->agregar('SIN_CUENTAS_RENTA', 'info', 'renta', 'No hay cuentas de renta configuradas',
                'Ni sysconfig (ventarentaa/ventarentat) ni submodulo.cuenta_renta tienen cuentas: no se puede leer la renta contable.');

            return;
        }
        if ($co['renta_ambigua']) {
            $this->agregar('CUENTA_AMBIGUA', 'info', 'renta', 'La renta contable de este file no es separable del costo',
                "{$co['movimientos_ambiguos']} movimiento(s) en cuentas que este file usa a la vez como costo y como renta: factura.php los acumula en un mismo movimiento. No se compara contra contabilidad.");

            return;
        }
        $hayFacturas = array_filter($m['venta']['facturas'], fn ($f) => ! in_array($f['status'], ['AN', 'NU'], true)) !== [];
        if (! $hayFacturas) {
            return;
        }
        [$ref, $nombre] = $t['renta_congelada'] !== null ? [$t['renta_congelada'], 'congelada al facturar'] : [$t['renta_presupuestada'], 'presupuestada'];
        if (abs($co['renta'] - $ref) > $this->tol) {
            $this->agregar('RENTA_CONTABLE_DISTINTA', 'media', 'renta', 'La renta contable no coincide con la renta '.$nombre,
                "Mayor (cuentas de renta, {$co['renta_movimientos']} movimiento(s)) = {$this->num($co['renta'])}; renta {$nombre} = {$this->num($ref)}. "
                .'Suele ser un ajuste manual de asiento o una factura asentada con otra renta.', $co['renta'] - $ref);
        }
    }

    private function contable(array $m): void
    {
        $grupos = [];
        foreach ($m['contable']['grupos'] as $g) {
            $grupos[$g['clave']] = $g;
        }
        $conAsiento = fn (string $clave) => isset($grupos[$clave]) && array_filter($grupos[$clave]['movimientos'], fn ($x) => $x['valido']) !== [];

        $sin = [];
        foreach ($m['venta']['facturas'] as $d) {
            if ($d['status'] !== 'NU' && ! $conAsiento("FC:{$d['id']}")) {
                $sin[] = "Factura {$d['numero']}";
            }
            if ($d['status'] === 'NU' && $conAsiento("FC:{$d['id']}")) {
                $this->agregar('FACTURA_ANULADA_CON_ASIENTO', 'alta', 'contable', "Factura {$d['numero']} anulada con asiento vivo",
                    'Está en estado NU (anular() borra sus movimientos) pero todavía tiene movimientos válidos en el mayor.', null, ['factura', $d['id']]);
            }
        }
        foreach ([['notas_credito', 'NC', 'Nota de crédito'], ['notas_debito', 'ND', 'Nota de débito']] as [$k, $p, $nombre]) {
            foreach ($m['venta'][$k] as $d) {
                if ($d['status'] !== 'NU' && ! $conAsiento("{$p}:{$d['id']}")) {
                    $sin[] = "{$nombre} {$d['numero']}";
                }
            }
        }
        foreach ($m['venta']['recibos'] as $d) {
            if (! $d['anulado'] && ! $conAsiento("RC:{$d['id']}")) {
                $sin[] = "Recibo {$d['numero']}";
            }
        }
        foreach ($m['costo']['ordenes'] as $d) {
            if ($d['tipo'] === 'P' && $d['status'] === 'OK' && ! $conAsiento("OR:{$d['id']}")) {
                $sin[] = "OP {$d['numero']}";
            }
        }
        foreach ($m['costo']['facturas_proveedor'] as $d) {
            if (! $conAsiento("FP:{$d['id']}")) {
                $sin[] = "Factura de proveedor {$d['numero']}";
            }
        }
        foreach (array_unique($sin) as $etq) {
            $this->agregar('COMPROBANTE_SIN_ASIENTO', 'alta', 'contable', "{$etq} sin asiento", 'No tiene ningún movimiento válido en el mayor.');
        }

        $manuales = [];
        foreach ($grupos as $g) {
            if (abs($g['diferencia']) > $this->tol) {
                $this->agregar('ASIENTO_DESBALANCEADO', $g['compartido'] ? 'info' : 'alta', 'contable', "{$g['etiqueta']}: el asiento no balancea",
                    "Debe {$this->num($g['debe'])} / Haber {$this->num($g['haber'])} en moneda básica."
                    .($g['compartido'] ? ' El comprobante es compartido con otros files: puede faltar alguna línea de ellos.' : ''), $g['diferencia']);
            }
            if (in_array($g['orden_tipo'], ['A', 'C'], true)) {
                $manuales[] = $g['etiqueta'];
            }
        }
        if ($manuales !== []) {
            $this->agregar('ASIENTOS_MANUALES', 'info', 'contable', count($manuales).' asiento(s) manuales o en cuenta corriente',
                implode(' · ', $manuales).'. No salen de ningún comprobante: revisar que estén explicados.');
        }
    }

    // ------------------------------------------------------------------

    private function agregar(string $codigo, string $severidad, string $area, string $titulo, string $detalle, ?float $importe = null, ?array $ref = null): void
    {
        $this->out[] = [
            'codigo' => $codigo,
            'severidad' => $severidad,
            'area' => $area,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'importe' => $importe === null ? null : round($importe, $this->dec),
            'ref' => $ref === null ? null : ['tipo' => $ref[0], 'id' => $ref[1]],
        ];
    }

    private function caminos(array $c): string
    {
        $nombres = ['fk_file_id' => 'factura.fk_file_id', 'rel_filefactura' => 'rel_filefactura', 'servicio' => 'sus servicios'];

        return implode(' y ', array_map(fn ($x) => $nombres[$x] ?? $x, $c)) ?: 'ningún camino';
    }

    private function num(float $v): string
    {
        return number_format($v, $this->dec, ',', '.');
    }

    private function tasa(float $v): string
    {
        return number_format($v, 4, ',', '.');
    }
}
