<?php

namespace Tests\Feature\Cuentas;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Cuenta corriente de proveedor: réplica de proveedor_model::getCuenta() —
 * facturas de proveedor al haber (NC al debe), órdenes de pago al debe netas
 * de créditos, asientos en cta cte y modo diferencias por imputación.
 */
class CuentaCorrienteProveedorTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('moneda')->insert([
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y', 'orden' => 1],
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N', 'orden' => 2],
        ]);
        DB::table('cotizacion')->insert(['cotizacion_moneda' => 'USD', 'cotizacion_fecha' => '2020-01-01', 'cotizacion_relacion' => 1000, 'cotizacion_costo' => 990]);
        DB::table('proveedor')->insert([['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol', 'eliminar' => 'N'], ['proveedor_id' => 6, 'proveedor_nombre' => 'Borrado', 'eliminar' => 'Y']]);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7]);
        DB::table('servicio')->insert([
            ['servicio_id' => 11, 'servicio_nombre' => 'Hotel', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'status' => 'CO'],
            ['servicio_id' => 12, 'servicio_nombre' => 'Crédito aplicado', 'fk_reserva_id' => 21, 'fk_tipoproducto_id' => 'CRE', 'fk_proveedor_id' => 5, 'status' => 'CO'],
        ]);

        // Factura de proveedor del período en USD (121 x 1000 = 121.000 al haber), imputada con asiento 900 / imputación 7.
        DB::table('facturaproveedor')->insert([
            ['facturaproveedor_id' => 90, 'facturaproveedor_nro' => 'A-90', 'facturaproveedor_tipodocumento' => 'FC', 'fk_proveedor_id' => 5, 'fecha' => '2025-03-15', 'fechacontable' => '2025-03-15', 'fk_moneda_id' => 'USD', 'montototal' => 121, 'cotizacion' => 1000],
            // Factura anterior al período en ARS (300): saldo anterior -300.
            ['facturaproveedor_id' => 91, 'facturaproveedor_nro' => 'A-91', 'facturaproveedor_tipodocumento' => 'FC', 'fk_proveedor_id' => 5, 'fecha' => '2025-01-10', 'fechacontable' => '2025-01-10', 'fk_moneda_id' => 'ARS', 'montototal' => 300, 'cotizacion' => 1],
            // Nota de crédito de proveedor (50) al debe.
            ['facturaproveedor_id' => 92, 'facturaproveedor_nro' => 'NC-92', 'facturaproveedor_tipodocumento' => 'Nota de Credito', 'fk_proveedor_id' => 5, 'fecha' => '2025-03-18', 'fechacontable' => '2025-03-18', 'fk_moneda_id' => 'ARS', 'montototal' => 50, 'cotizacion' => 1],
        ]);
        DB::table('rel_facturaproveedorocupacion')->insert(['fk_facturaproveedor_id' => 90, 'fk_ocupacion_id' => 11, 'monto' => 121]);
        DB::table('movimiento')->insert(['movimiento_id' => 1, 'fk_facturaproveedor_id' => 90, 'fk_asientocontable_id' => 900, 'fk_moneda_id' => 'USD', 'monto' => 121, 'fecha' => '2025-03-15']);
        DB::table('imputacion')->insert(['imputacion_orden' => 7, 'imputacion_movimiento1' => '900']);

        // Orden de pago del 20/03 en ARS por 1.000 con un crédito de 200 aplicado (=> debe 800 + línea de crédito al haber 200).
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fecha' => '2025-03-20', 'nropago' => 'OP-1', 'tipo' => 'P', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 1000, 'status' => 'OK'],
            ['ordenadmin_id' => 301, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fecha' => '2025-03-22', 'nropago' => 'C-1', 'tipo' => 'C', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 0, 'status' => 'OK'],
        ]);
        DB::table('rel_ordenadminocupacion')->insert([
            ['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 11, 'fk_moneda_id' => 'ARS', 'monto' => 800],
            ['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 12, 'fk_moneda_id' => 'ARS', 'monto' => 200],
        ]);
        DB::table('movimiento')->insert(['movimiento_id' => 2, 'fk_ordenadmin_id' => 300, 'fk_asientocontable_id' => 901, 'fk_moneda_id' => 'ARS', 'monto' => 1000, 'fecha' => '2025-03-20']);
        // Asiento en cta cte del proveedor por 70 (sin columna deha => al haber).
        DB::table('movimiento')->insert(['movimiento_id' => 3, 'fk_ordenadmin_id' => 301, 'fk_proveedor_id' => 5, 'fk_asientocontable_id' => 902, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1, 'monto' => 70, 'fecha' => '2025-03-22', 'fk_file_id' => 21]);
    }

    public function test_cuenta_completa(): void
    {
        $this->get('/app/cuentas/proveedor?empresa=5&from=2025-03-01&to=2025-03-31&tiporeporte=C&monedareporte=ARS')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Cuentas/CuentaCorriente')
                ->where('titulo', 'Cuenta corriente proveedor')
                ->has('empresas', 1)
                ->where('cuenta.anterior', -300)
                ->has('cuenta.grupos', 1)
                ->has('cuenta.grupos.0.movimientos', 5)
                ->where('cuenta.grupos.0.movimientos.0.concepto.0.texto', 'FC3 #FC #A-90 (USD)')
                ->where('cuenta.grupos.0.movimientos.0.concepto.1.texto', 'RE-100')
                ->where('cuenta.grupos.0.movimientos.0.h', 121000)
                ->where('cuenta.grupos.0.movimientos.0.saldo', -121300)
                ->where('cuenta.grupos.0.movimientos.1.concepto.0.texto', 'FC3 #Nota de Credito #NC-92 (ARS)')
                ->where('cuenta.grupos.0.movimientos.1.d', 50)
                ->where('cuenta.grupos.0.movimientos.2.concepto.0.texto', 'OP #OP-1')
                ->where('cuenta.grupos.0.movimientos.2.d', 800)
                ->where('cuenta.grupos.0.movimientos.3.concepto.0.texto', 'CREDITOS APLICADOS A OP #OP-1')
                ->where('cuenta.grupos.0.movimientos.3.h', 200)
                ->where('cuenta.grupos.0.movimientos.4.concepto.0.texto', 'Asiento en CTA #C-1')
                ->where('cuenta.grupos.0.movimientos.4.h', 70)
                ->where('cuenta.totales.d', 850)
                ->where('cuenta.totales.h', 121270)
                ->where('cuenta.totales.saldo', -120720));
    }

    public function test_modo_diferencias_agrupa_por_imputacion(): void
    {
        $this->get('/app/cuentas/proveedor?empresa=5&from=2025-03-01&to=2025-03-31&tiporeporte=D&monedareporte=ARS')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('cuenta.grupos', 2)
                ->where('cuenta.grupos.0.file_id', 7)
                ->where('cuenta.grupos.0.nombre', 'RE-100 // FC3 #FC #A-90 (USD)')
                ->has('cuenta.grupos.0.movimientos', 1)
                ->where('cuenta.grupos.1.file_id', 0)
                ->has('cuenta.grupos.1.movimientos', 4));
    }

    public function test_reporte_en_usd(): void
    {
        $this->get('/app/cuentas/proveedor?empresa=5&from=2025-03-01&to=2025-03-31&tiporeporte=C&monedareporte=USD')
            ->assertInertia(fn (Assert $p) => $p
                ->where('cuenta.anterior', -0.3)
                ->where('cuenta.grupos.0.movimientos.0.h', 121)
                // Fiel al legacy: la OP se convierte (1.000 ARS => 1 USD) pero el crédito aplicado (200) se resta sin convertir.
                ->where('cuenta.grupos.0.movimientos.2.d', -199));
    }
}
