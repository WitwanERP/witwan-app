<?php

namespace Tests\Feature\Proveedores;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/** Canjes, pre-compras, crédito de proveedores y movimientos de fondos. */
class ProveedoresListadosTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('proveedor')->insert(['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol']);
        DB::table('moneda')->insert([
            ['moneda_id' => 'USD', 'moneda_nombre' => 'Dólar', 'moneda_basica' => 'N'],
            ['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y'],
        ]);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1]);
        $fin = now()->addMonths(2)->toDateString();
        DB::table('canje')->insert([
            ['canje_id' => 1, 'canje_contrato' => 'C-2026', 'canje_inicio' => '2026-01-01', 'canje_fin' => $fin, 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'canje_noches' => 10, 'canje_dinero' => 1000, 'canje_utilizado' => 0, 'observaciones' => 'x'],
            ['canje_id' => 2, 'canje_contrato' => 'VENCIDO', 'canje_inicio' => '2020-01-01', 'canje_fin' => '2020-12-31', 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'canje_noches' => 5, 'canje_dinero' => 500, 'canje_utilizado' => 500, 'observaciones' => ''],
            ['canje_id' => 3, 'canje_contrato' => 'PESOS', 'canje_inicio' => '2026-01-01', 'canje_fin' => $fin, 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'ARS', 'canje_noches' => 1, 'canje_dinero' => 100, 'canje_utilizado' => 0, 'observaciones' => ''],
        ]);
        // Servicios que consumen el canje 1: uno con OS válida (300 USD), otro con OS anulada, otro en otra moneda.
        DB::table('servicio')->insert([
            ['servicio_id' => 11, 'servicio_nombre' => 'Canje 1', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'CAN', 'status' => 'CO', 'moneda_costo' => 'USD', 'costo' => -300],
            ['servicio_id' => 12, 'servicio_nombre' => 'Canje anulado', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'CAN', 'status' => 'CO', 'moneda_costo' => 'USD', 'costo' => 200],
            ['servicio_id' => 13, 'servicio_nombre' => 'Canje pesos', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'CAN', 'status' => 'CO', 'moneda_costo' => 'ARS', 'costo' => 999],
            ['servicio_id' => 14, 'servicio_nombre' => 'Precompra', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'PRC', 'status' => 'CL', 'moneda_costo' => 'USD', 'costo' => 150],
        ]);
        foreach ([11 => 1, 12 => 1, 13 => 1, 14 => 7] as $sid => $paquete) {
            DB::table('servicio_extra')->insert(['fk_servicio_id' => $sid, 'extra_nombre' => 'paquete', 'extra_valor' => (string) $paquete]);
        }
        $hoy = now()->toDateString();
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-1', 'nropago' => '', 'tipo' => 'S', 'fk_moneda_id' => 'USD', 'cotizacion' => 1, 'monto' => 300, 'status' => 'PR'],
            ['ordenadmin_id' => 301, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-2', 'nropago' => '', 'tipo' => 'S', 'fk_moneda_id' => 'USD', 'cotizacion' => 1, 'monto' => 200, 'status' => 'AN'],
            ['ordenadmin_id' => 302, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-3', 'nropago' => '', 'tipo' => 'S', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 999, 'status' => 'PR'],
            ['ordenadmin_id' => 303, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-4', 'nropago' => '', 'tipo' => 'S', 'fk_moneda_id' => 'USD', 'cotizacion' => 1, 'monto' => 150, 'status' => 'PR'],
            ['ordenadmin_id' => 400, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 0, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => '', 'nropago' => 'MF-1', 'tipo' => 'M', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 2500, 'status' => 'OK'],
            ['ordenadmin_id' => 401, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 0, 'fk_usuario_id' => 7, 'fecha' => '2020-01-01', 'nroservicio' => '', 'nropago' => 'MF-0', 'tipo' => 'M', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 1, 'status' => 'AN'],
        ]);
        DB::table('rel_ordenadminocupacion')->insert([
            ['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 11, 'fk_moneda_id' => 'USD', 'monto' => 300],
            ['fk_ordenadmin_id' => 301, 'fk_ocupacion_id' => 12, 'fk_moneda_id' => 'USD', 'monto' => 200],
            ['fk_ordenadmin_id' => 302, 'fk_ocupacion_id' => 13, 'fk_moneda_id' => 'ARS', 'monto' => 999],
            ['fk_ordenadmin_id' => 303, 'fk_ocupacion_id' => 14, 'fk_moneda_id' => 'USD', 'monto' => 150],
        ]);
        DB::table('precompra')->insert([
            ['precompra_id' => 7, 'precompra_tipo' => 'P', 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'precompra_inicio' => '2026-01-01', 'precompra_fin' => $fin, 'precompra_dinero' => 1000, 'precompra_utilizado' => 0, 'fk_file_id' => 0],
            ['precompra_id' => 8, 'precompra_tipo' => 'C', 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'precompra_inicio' => '2026-02-01', 'precompra_fin' => $fin, 'precompra_dinero' => 400, 'precompra_utilizado' => 100, 'fk_file_id' => 21],
            ['precompra_id' => 9, 'precompra_tipo' => 'C', 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'precompra_inicio' => '2026-02-01', 'precompra_fin' => $fin, 'precompra_dinero' => 100, 'precompra_utilizado' => 100, 'fk_file_id' => 0],
            ['precompra_id' => 10, 'precompra_tipo' => 'C', 'fk_proveedor_id' => 5, 'fk_moneda_id' => 'USD', 'precompra_inicio' => '2026-02-01', 'precompra_fin' => $fin, 'precompra_dinero' => 50, 'precompra_utilizado' => 80, 'fk_file_id' => 0],
        ]);
        DB::table('servicio')->where('servicio_id', 11)->update(['id_devolucion' => 8, 'nro_confirmacion' => 'CONF-8']);
    }

    public function test_canjes_recalcula_utilizado_y_por_defecto_muestra_vigentes_en_usd(): void
    {
        $this->get('/app/proveedores/canjes')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->where('grupos.0.filas.0.contrato', 'C-2026')
            ->where('grupos.0.filas.0.utilizado', 300)
            ->where('grupos.0.filas.0.saldo', 700)
            // Como en el legacy, la columna de órdenes no filtra por moneda ni por OS anulada.
            ->where('grupos.0.filas.0.ordenes', 'OS-1,OS-2,OS-3')
            ->where('grupos.0.totales.dinero', 1000)
            ->has('grupos', 1)
            ->has('grupos.0.filas', 1)
        );

        $this->assertSame(300.0, (float) DB::table('canje')->where('canje_id', 1)->value('canje_utilizado'));
        // El canje 2 no tiene consumos: queda en 0 aunque estuviera cargado a mano.
        $this->assertSame(0.0, (float) DB::table('canje')->where('canje_id', 2)->value('canje_utilizado'));
    }

    public function test_canjes_con_filtros_muestra_otras_monedas_y_vencidos(): void
    {
        $this->get('/app/proveedores/canjes?fk_proveedor_id=5&vervencido=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos', 2)
        );
        $this->get('/app/proveedores/canjes?fk_moneda_id=USD&vervencido=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.1.contrato', 'VENCIDO')
            ->has('grupos.0.filas', 2)
        );
        $this->get('/app/proveedores/canjes?fk_moneda_id=USD&disponibles=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
        );
    }

    public function test_precompras_recalcula_y_lista_solo_tipo_p(): void
    {
        $this->get('/app/proveedores/precompras')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->where('grupos.0.filas.0.id', 7)
            ->where('grupos.0.filas.0.aplicado', 150)
            ->where('grupos.0.filas.0.saldo', 850)
            ->has('grupos.0.filas', 1)
        );
    }

    public function test_credito_proveedor_oculta_saldo_cero_por_defecto_y_filtra_excedidos(): void
    {
        $this->get('/app/proveedores/creditos')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 2)
            ->where('grupos.0.filas.1.file', 'RE-100')
            ->where('grupos.0.filas.1.confirmacion', 'CONF-8')
            ->where('grupos.0.filas.1.saldo', 300)
        );
        $this->get('/app/proveedores/creditos?excedidos=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.saldo', -30)
        );
        $this->get('/app/proveedores/creditos?disponibles=0')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.saldo', 0)
        );
    }

    public function test_movimientos_de_fondos_lista_tipo_m_y_exporta(): void
    {
        $this->get('/app/documentos/movimientos-fondos')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.0.numero', 'MF-1')
            ->where('grupos.0.filas.0.status', 'Ok')
            ->where('grupos.0.filas.0.usuario', 'Ana Pow')
            ->has('grupos.0.filas', 1)
        );
        $this->get('/app/documentos/movimientos-fondos?status=AN')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('grupos.0.filas.0.numero', 'MF-0')
        );
        $csv = $this->get('/app/documentos/movimientos-fondos/export?status=OK')->assertOk()->streamedContent();
        $this->assertStringContainsString('MF-1', $csv);
    }
}
