<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

class IvaVentaTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        DB::table('condicioniva')->insert(['condicioniva_id' => 1, 'condicioniva_nombre' => 'Resp. Inscripto']);
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'SOL', 'cliente_razonsocial' => 'Agencia Sol Sociedad Anónima', 'cuit' => '30-1', 'fk_condicioniva_id' => 1]);
        DB::table('factura')->insert([
            // ARS: gravado 1000 (IVA 210), gravadoespecial 200 (IVA 21), exento 50, no gravado 10, imp1 5, rgt 7, IVA TUR (remito) 3 => total 1210+221+50+10+5+7-3 = 1500
            ['factura_id' => 40, 'factura_nro' => '40', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-11 10:00:00', 'factura_tipo' => 'A', 'factura_sucursal' => '0001', 'factura_conceptos_gravados' => 1000, 'factura_conceptos_gravadosespecial' => 200, 'factura_conceptos_exentos' => 50, 'factura_conceptos_nogravados' => 10, 'factura_impuesto1' => 5, 'factura_rgterrestres' => 7, 'remitofull' => '0:0:3', 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'ARS', 'factura_tipo_cambio' => 0],
            // USD con tipo de cambio 1000: gravado 10 => 10000 gr21, IVA 2100, total 12100
            ['factura_id' => 41, 'factura_nro' => '41', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-12 10:00:00', 'factura_tipo' => 'B', 'factura_sucursal' => '0001', 'factura_conceptos_gravados' => 10, 'factura_conceptos_gravadosespecial' => 0, 'factura_conceptos_exentos' => 0, 'factura_conceptos_nogravados' => 0, 'factura_impuesto1' => 0, 'factura_rgterrestres' => 0, 'remitofull' => '0:0:0', 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'USD', 'factura_tipo_cambio' => 1000],
            ['factura_id' => 42, 'factura_nro' => '42', 'statusfactura' => 'EM', 'factura_fecha' => '2026-08-12 10:00:00', 'factura_tipo' => 'X', 'factura_sucursal' => '0001', 'factura_conceptos_gravados' => 999, 'factura_conceptos_gravadosespecial' => 0, 'factura_conceptos_exentos' => 0, 'factura_conceptos_nogravados' => 0, 'factura_impuesto1' => 0, 'factura_rgterrestres' => 0, 'remitofull' => '0:0:0', 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'ARS', 'factura_tipo_cambio' => 0],
        ]);
        DB::table('notacredito')->insert(['notacredito_id' => 50, 'notacredito_nro' => '50', 'statusfactura' => 'EM', 'notacredito_fecha' => '2026-08-13 09:00:00', 'notacredito_tipo' => 'A', 'notacredito_sucursal' => '0001', 'notacredito_conceptos_gravados' => 100, 'fk_cliente_id' => 3, 'fk_factura_id' => 40, 'fk_moneda_id' => 'ARS', 'remitofull' => '0:0:0']);
        DB::table('notadebito')->insert(['notadebito_id' => 60, 'notadebito_nro' => '60', 'statusfactura' => 'EM', 'notadebito_fecha' => '2026-08-14 09:00:00', 'notadebito_tipo' => 'A', 'notadebito_sucursal' => '0001', 'notadebito_conceptos_gravados' => 200, 'notadebito_conceptos_exentos' => 8, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'ARS']);
    }

    public function test_iva_venta_agrupa_por_tipo_con_alicuotas_y_tipo_de_cambio(): void
    {
        $this->get('/app/contabilidad/iva-venta?fecha=2026-08-01&fecha_to=2026-08-31')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->has('grupos', 3)
            ->where('grupos.0.clave', 'F')
            ->has('grupos.0.filas', 2)
            ->where('grupos.0.filas.0.nrof', 'FC A 0001-40')
            ->where('grupos.0.filas.0.ncliente', 'Agencia Sol Sociedad')
            ->where('grupos.0.filas.0.condicioniva', 'Resp. Inscripto')
            ->where('grupos.0.filas.0.i21', 210)
            ->where('grupos.0.filas.0.i10', 21)
            ->where('grupos.0.filas.0.rg1043', -3)
            ->where('grupos.0.filas.0.totalfinal', 1500)
            ->where('grupos.0.filas.1.c21', 10000)
            ->where('grupos.0.filas.1.totalfinal', 12100)
            ->where('grupos.0.totales.totalfinal', 13600)
            ->where('grupos.1.clave', 'NC')
            ->where('grupos.1.filas.0.c21', -100)
            ->where('grupos.1.filas.0.totalfinal', -121)
            ->where('grupos.2.clave', 'ND')
            ->where('grupos.2.filas.0.totalfinal', 250)
        );
        $this->get('/app/contabilidad/iva-venta?fecha=2026-08-01&fecha_to=2026-08-31&tipo=NC')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos', 1));
        $this->get('/app/contabilidad/iva-venta?fecha=2026-08-12&fecha_to=2026-08-12')->assertOk()->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1));
    }
}
