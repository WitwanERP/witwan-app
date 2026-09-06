<?php

namespace Tests\Feature\Cuentas;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Cuenta corriente de cliente: réplica de cliente_model::getCuenta() con saldo
 * anterior, movimientos del período en orden cronológico y modo "sólo
 * diferencias" agrupado por file.
 */
class CuentaCorrienteClienteTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('sysconfig')->insert(['sysconfig_key' => 'tasageneral', 'sysconfig_value' => '21']);
        DB::table('moneda')->insert([
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y', 'orden' => 1],
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N', 'orden' => 2],
        ]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2020-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('cliente')->insert([['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol'], ['cliente_id' => 4, 'cliente_nombre' => 'Otra']]);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7]);

        // Asiento en cta cte (crédito => positivo), 10/03.
        DB::table('ordenadmin')->insert(['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 0, 'tipo' => 'C', 'nropago' => 'A1', 'status' => 'OK']);
        DB::table('movimiento')->insert(['movimiento_id' => 1, 'fk_ordenadmin_id' => 300, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 50, 'fecha' => '2025-03-10', 'cuenta_credito' => 5, 'cuenta_debito' => 0]);

        // Facturas: una del período (171), una anterior (121).
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '0001-00000040', 'statusfactura' => 'EM', 'factura_fecha' => '2025-03-15 10:00:00', 'factura_tipo' => 'A', 'factura_conceptos_gravados' => 100, 'factura_conceptos_exentos' => 50, 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'ARS']);
        DB::table('factura')->insert(['factura_id' => 42, 'factura_nro' => '0001-00000042', 'statusfactura' => 'EM', 'factura_fecha' => '2025-01-10 10:00:00', 'factura_tipo' => 'A', 'factura_conceptos_gravados' => 100, 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'ARS']);
        DB::table('rel_filefactura')->insert([['fk_file_id' => 21, 'fk_factura_id' => 40], ['fk_file_id' => 21, 'fk_factura_id' => 42]]);

        // NC del 20/03 (121) ligada a la factura 40; ND del 22/03 (242).
        DB::table('notacredito')->insert(['notacredito_id' => 50, 'notacredito_nro' => 'NC-50', 'statusfactura' => 'EM', 'notacredito_fecha' => '2025-03-20 09:00:00', 'notacredito_tipo' => 'A', 'notacredito_conceptos_gravados' => 100, 'fk_cliente_id' => 3, 'fk_factura_id' => 40, 'fk_moneda_id' => 'ARS']);
        DB::table('notadebito')->insert(['notadebito_id' => 60, 'notadebito_nro' => 'ND-60', 'statusfactura' => 'EM', 'notadebito_fecha' => '2025-03-22 09:00:00', 'notadebito_tipo' => 'A', 'notadebito_conceptos_gravados' => 200, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'ARS']);

        // Recibos: uno en ARS aplicado al file (300), uno en USD sin file (10 => 10.000 ARS).
        DB::table('recibo')->insert([
            ['recibo_id' => 70, 'recibo_nro' => 'R-70', 'fecha' => '2025-03-25', 'fk_cliente_id' => 3, 'statusrecibo' => 'EM', 'monto' => 300, 'fk_moneda_id' => 'ARS'],
            ['recibo_id' => 71, 'recibo_nro' => 'R-71', 'fecha' => '2025-03-26', 'fk_cliente_id' => 3, 'statusrecibo' => 'EM', 'monto' => 10, 'fk_moneda_id' => 'USD'],
        ]);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70, 'monto' => 300]);
    }

    public function test_cuenta_completa_en_moneda_basica(): void
    {
        $this->get('/app/cuentas/cliente?empresa=3&from=2025-03-01&to=2025-03-31&tiporeporte=C&monedareporte=ARS')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Cuentas/CuentaCorriente')
                ->where('monedaBasica', 'ARS')
                ->where('cuenta.anterior', 121)
                ->has('cuenta.grupos', 1)
                ->has('cuenta.grupos.0.movimientos', 6)
                ->where('cuenta.grupos.0.movimientos.0.concepto.0.texto', 'Asiento en CTA #A1')
                ->where('cuenta.grupos.0.movimientos.0.d', 50)
                ->where('cuenta.grupos.0.movimientos.0.saldo', 171)
                ->where('cuenta.grupos.0.movimientos.1.concepto.0.texto', 'FC #A 0001-00000040 (ARS)')
                ->where('cuenta.grupos.0.movimientos.1.concepto.1.texto', 'FILE: RE-100')
                ->where('cuenta.grupos.0.movimientos.1.d', 171)
                ->where('cuenta.grupos.0.movimientos.2.h', 121)
                ->where('cuenta.grupos.0.movimientos.3.d', 242)
                ->where('cuenta.grupos.0.movimientos.4.h', 300)
                ->where('cuenta.grupos.0.movimientos.5.h', 10000)
                ->where('cuenta.grupos.0.movimientos.5.saldo', -9837)
                ->where('cuenta.totales.d', 463)
                ->where('cuenta.totales.h', 10421)
                ->where('cuenta.totales.saldo', -9837));
    }

    public function test_modo_diferencias_agrupa_por_file_y_oculta_files_saldados(): void
    {
        $this->get('/app/cuentas/cliente?empresa=3&from=2025-03-01&to=2025-03-31&tiporeporte=D&monedareporte=ARS')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('cuenta.grupos', 2)
                ->where('cuenta.grupos.0.file_id', 0)
                ->has('cuenta.grupos.0.movimientos', 2)
                ->where('cuenta.grupos.1.file_id', 21)
                ->where('cuenta.grupos.1.total', -250)
                ->has('cuenta.grupos.1.movimientos', 3));

        // Si el file queda saldado no aparece en modo diferencias.
        DB::table('rel_filerecibo')->where('fk_recibo_id', 70)->update(['monto' => 50]);
        DB::table('recibo')->where('recibo_id', 70)->update(['monto' => 50]);
        $this->get('/app/cuentas/cliente?empresa=3&from=2025-03-01&to=2025-03-31&tiporeporte=D&monedareporte=ARS')
            ->assertInertia(fn (Assert $p) => $p->has('cuenta.grupos', 1)->where('cuenta.grupos.0.file_id', 0));
    }

    public function test_reporte_en_usd_convierte_con_la_cotizacion_de_venta(): void
    {
        $this->get('/app/cuentas/cliente?empresa=3&from=2025-03-01&to=2025-03-31&tiporeporte=C&monedareporte=USD')
            ->assertInertia(fn (Assert $p) => $p
                ->where('cuenta.anterior', 0.12)
                ->where('cuenta.grupos.0.movimientos.1.d', 0.17)
                ->where('cuenta.grupos.0.movimientos.5.h', 10));
    }

    public function test_sin_empresa_no_consulta_y_exporta_csv(): void
    {
        $this->get('/app/cuentas/cliente')->assertOk()->assertInertia(fn (Assert $p) => $p->where('cuenta', null)->has('empresas', 2));

        $resp = $this->get('/app/cuentas/cliente?empresa=3&from=2025-03-01&tiporeporte=C&export=1');
        $resp->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $resp->streamedContent();
        $this->assertStringContainsString('Agencia Sol', $csv);
        $this->assertStringContainsString('"SALDO ANTERIOR";;;;121', $csv);
        $this->assertStringContainsString('FC #A 0001-00000040 (ARS) // FILE: RE-100', $csv);
    }
}
