<?php

namespace App\Services\Reservas\MapaContable;

/**
 * Cuánto se gana en un file, separando qué parte es precio y qué parte es cambio.
 *
 * Cálculo puro (sin base): recibe los importes ya convertidos por
 * MapaContableFileService y devuelve todo en moneda básica.
 *
 * Punto de partida: la renta presupuestada, con la fórmula canónica de
 * Analitica_model::sql_renta_operacion() (la de rentamt) pero SIN reemplazarla
 * por serviciofactura.renta. La renta congelada al facturar se informa aparte,
 * como comparación:
 *
 *     R0 = (total − iva)·cv − costo_operado·cc
 *     costo_operado = costo + iva_costo + impuestos (si el servicio no tiene PNR)
 *
 * Sobre R0 se suman ajustes que, por construcción, cierran exacto contra lo
 * que efectivamente se facturó, cobró, facturó el proveedor y se le pagó.
 *
 * Precio (cantidades distintas de las operadas):
 *  - Pc  costo: (costo_operado − Q)·cc, con Q = lo que facturó el proveedor; sin
 *        factura de proveedor, max(costo_operado, pagado).
 *  - Pv  venta: venta documental (FC − NC + ND, a su TC y en la proporción del
 *        file) − servicios facturados a la tasa de su factura, llevado a neto de
 *        IVA con la proporción neta de esos servicios. No se restan percepciones:
 *        en factura.php RG, RG 4815, Qatar e impuesto PAÍS salen de servicios del
 *        propio file (`round($row->total * $ct)`, :3060-3105), así que ya están
 *        en la venta esperada.
 *
 * Cambio (mismas cantidades, tasas distintas):
 *  - DV1 servicio → factura:        (total − iva)·(tasa factura − cv)
 *  - DV2 factura → cobranza:        min(facturado, cobrado)·(tasa cobro − tasa factura), en la
 *                                   moneda extranjera de facturación del file
 *  - DC1 servicio → fc proveedor:   Qf·(cc − tasa fc proveedor)
 *  - DC2 fc proveedor → pago:       min(Qf, Qp)·(tasa fc proveedor − tasa pago)
 *  - DC3 servicio → pago (sin fc):  Qp·(cc − tasa pago)
 *
 * Signo: positivo = a favor de la agencia.
 */
final class EconomiaFile
{
    public function __construct(private int $decimales = 2) {}

    /**
     * @param  list<array<string,mixed>>  $servicios  id, cancelado, total, iva, costo_operado, cv, cc,
     *                                                renta_congelada (?float, moneda de venta), tasa_factura (?float),
     *                                                fc3: list<{cantidad,tasa,base}>, pagos: list<{cantidad,tasa,base}>
     * @param  list<array<string,mixed>>  $ventas  comprobantes de venta: signo, neto_doc (total del comprobante), tasa, share
     * @param  array<string,mixed>  $cobranza  moneda (?string), facturado: list<{cantidad,tasa}>, cobrado: list<{cantidad,tasa}>
     * @param  float  $cobradoBase  todo lo cobrado del file en básica (recibos + asientos en cta. cte.)
     */
    public function calcular(array $servicios, array $ventas, array $cobranza, float $cobradoBase): array
    {
        $porServicio = [];
        $t = array_fill_keys(['renta_presupuestada', 'venta_neta', 'costo', 'dif_precio_costo', 'dc_servicio_factura',
            'dc_servicio_fc3', 'dc_fc3_pago', 'dc_servicio_pago', 'renta_congelada', 'pagado'], 0.0);
        $hayCongelada = false;
        $esperado = 0.0;
        $brutoFacturado = 0.0;
        $netoFacturado = 0.0;

        foreach ($servicios as $s) {
            $r = $this->servicio($s);
            $porServicio[$s['id']] = $r;

            foreach (['renta_presupuestada', 'venta_neta', 'costo', 'dif_precio_costo', 'dc_servicio_factura', 'dc_servicio_fc3', 'dc_fc3_pago', 'dc_servicio_pago'] as $k) {
                $t[$k] += $r[$k];
            }
            $t['pagado'] += $r['pagado_base'];
            if ($r['renta_congelada'] !== null) {
                $t['renta_congelada'] += $r['renta_congelada'];
                $hayCongelada = true;
            }

            if (empty($s['cancelado']) && $s['tasa_factura'] !== null) {
                $esperado += (float) $s['total'] * (float) $s['tasa_factura'];
                $brutoFacturado += (float) $s['total'];
                $netoFacturado += (float) $s['total'] - (float) $s['iva'];
            }
        }

        $documental = 0.0;
        foreach ($ventas as $v) {
            $documental += (int) $v['signo'] * (float) $v['neto_doc'] * (float) $v['tasa'] * (float) $v['share'];
        }
        $proporcionNeta = $brutoFacturado != 0.0 ? $netoFacturado / $brutoFacturado : 1.0;
        $difVentaBruta = $ventas === [] ? 0.0 : $documental - $esperado;
        $difPrecioVenta = $difVentaBruta * $proporcionNeta;

        $cob = $this->cobranza($cobranza);

        $rentaComercial = $t['renta_presupuestada'] + $difPrecioVenta + $t['dif_precio_costo'];
        $resultadoCambio = $t['dc_servicio_factura'] + $cob['diferencia'] + $t['dc_servicio_fc3'] + $t['dc_fc3_pago'] + $t['dc_servicio_pago'];

        $d = $this->decimales;

        return [
            'servicios' => array_map(fn ($r) => $this->redondear($r), $porServicio),
            'venta' => [
                'documental' => round($documental, $d),
                'esperada' => round($esperado, $d),
                'diferencia_bruta' => round($difVentaBruta, $d),
                'proporcion_neta' => round($proporcionNeta, 6),
            ],
            'cobranza' => $this->redondear($cob),
            'totales' => $this->redondear([
                'venta_neta' => $t['venta_neta'],
                'costo' => $t['costo'],
                'renta_presupuestada' => $t['renta_presupuestada'],
                'dif_precio_venta' => $difPrecioVenta,
                'dif_precio_costo' => $t['dif_precio_costo'],
                'renta_comercial' => $rentaComercial,
                'dc_servicio_factura' => $t['dc_servicio_factura'],
                'dc_factura_cobranza' => $cob['diferencia'],
                'dc_servicio_fc3' => $t['dc_servicio_fc3'],
                'dc_fc3_pago' => $t['dc_fc3_pago'],
                'dc_servicio_pago' => $t['dc_servicio_pago'],
                'resultado_cambio' => $resultadoCambio,
                'renta_real' => $rentaComercial + $resultadoCambio,
                'renta_congelada' => $hayCongelada ? $t['renta_congelada'] : null,
                'cobrado' => $cobradoBase,
                'pagado' => $t['pagado'],
                'caja' => $cobradoBase - $t['pagado'],
            ]),
        ];
    }

    /** @param array<string,mixed> $s */
    private function servicio(array $s): array
    {
        $cancelado = ! empty($s['cancelado']);
        $cv = (float) $s['cv'];
        $cc = (float) $s['cc'];
        $neto = $cancelado ? 0.0 : (float) $s['total'] - (float) $s['iva'];
        $cOp = $cancelado ? 0.0 : (float) $s['costo_operado'];

        [$qf, $rf] = $this->ponderar($s['fc3'] ?? []);
        [$qp, $rp] = $this->ponderar($s['pagos'] ?? []);
        $pagadoBase = array_sum(array_map(fn ($x) => (float) $x['base'], $s['pagos'] ?? []));

        $pc = 0.0;
        $dc1 = 0.0;
        $dc2 = 0.0;
        $dc3 = 0.0;
        $q = $cOp;

        if (($s['fc3'] ?? []) !== []) {
            // La factura del proveedor es el costo: lo que difiera del costo operado es precio.
            $q = $qf;
            $pc = ($cOp - $qf) * $cc;
            $dc1 = $qf * ($cc - $rf);
            if ($qp > 0 && $qf > 0) {
                $dc2 = min($qf, $qp) * ($rf - $rp);
            }
        } elseif ($qp > 0) {
            // Sin factura de proveedor: lo pagado reemplaza al costo sólo en la parte pagada;
            // lo que falta pagar sigue a la cotización del servicio.
            $q = max($cOp, $qp);
            $pc = ($cOp - $q) * $cc;
            $dc3 = $qp * ($cc - $rp);
        }

        $ventaNeta = $neto * $cv;
        $costo = $cOp * $cc;
        $dv1 = (! $cancelado && $s['tasa_factura'] !== null) ? $neto * ((float) $s['tasa_factura'] - $cv) : 0.0;
        $costoReal = $costo - $pc - $dc1 - $dc2 - $dc3;
        $congelada = $s['renta_congelada'] ?? null;

        // Saldo con el proveedor, en moneda de costo: contra lo facturado si hay
        // factura, si no contra el costo operado.
        $deuda = ($s['fc3'] ?? []) !== [] ? $qf : $cOp;

        return [
            'venta_neta' => $ventaNeta,
            'costo' => $costo,
            'renta_presupuestada' => $ventaNeta - $costo,
            'renta_congelada' => ($congelada !== null && (float) $congelada != 0.0) ? (float) $congelada * $cv : null,
            'dif_precio_costo' => $pc,
            'dc_servicio_factura' => $dv1,
            'dc_servicio_fc3' => $dc1,
            'dc_fc3_pago' => $dc2,
            'dc_servicio_pago' => $dc3,
            'costo_real' => $costoReal,
            'renta_real' => $ventaNeta + $dv1 - $costoReal,
            'pagado_base' => $pagadoBase,
            'cantidades' => [
                'costo_operado' => $cOp,
                'costo_reconocido' => $q,
                'facturado_proveedor' => $qf,
                'tasa_fc3' => $rf,
                'pagado' => $qp,
                'tasa_pago' => $rp,
                'tasa_factura' => $s['tasa_factura'],
                'saldo_proveedor' => $deuda - $qp,
            ],
        ];
    }

    /**
     * DV2: diferencia entre la tasa a la que se facturó y la tasa a la que se cobró,
     * sobre la parte efectivamente cobrada de lo facturado en moneda extranjera.
     *
     * @param  array<string,mixed>  $c
     */
    private function cobranza(array $c): array
    {
        [$qf, $rf] = $this->ponderar($c['facturado'] ?? []);
        [$qc, $rc] = $this->ponderar($c['cobrado'] ?? []);
        $aplicado = ($qf > 0 && $qc > 0) ? min($qf, $qc) : 0.0;

        return [
            'moneda' => $c['moneda'] ?? null,
            'motivo' => $c['motivo'] ?? '',
            'facturado' => $qf,
            'tasa_factura' => $rf,
            'cobrado' => $qc,
            'tasa_cobro' => $rc,
            'aplicado' => $aplicado,
            'diferencia' => ($c['moneda'] ?? null) !== null ? $aplicado * ($rc - $rf) : 0.0,
        ];
    }

    /**
     * Suma de cantidades y tasa promedio ponderada por cantidad.
     *
     * @param  list<array<string,mixed>>  $items
     * @return array{0:float,1:float}
     */
    private function ponderar(array $items): array
    {
        $q = 0.0;
        $qt = 0.0;
        foreach ($items as $i) {
            $q += (float) $i['cantidad'];
            $qt += (float) $i['cantidad'] * (float) $i['tasa'];
        }

        return [$q, $q != 0.0 ? $qt / $q : 0.0];
    }

    /** Redondea importes; deja tasas y proporciones con más precisión. */
    private function redondear(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = $this->redondear($v);
            } elseif (is_float($v)) {
                $a[$k] = str_starts_with((string) $k, 'tasa') ? round($v, 4) : round($v, $this->decimales);
            }
        }

        return $a;
    }
}
