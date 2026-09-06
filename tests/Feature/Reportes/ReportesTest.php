<?php

namespace Tests\Feature\Reportes;

use App\Services\Reservas\ReservaCobradoService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de los reportes config-driven: filtros, agrupación por moneda,
 * totales y export CSV.
 */
class ReportesTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('cliente')->insert([['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol'], ['cliente_id' => 4, 'cliente_nombre' => 'Agencia Luna']]);
        DB::table('usuario')->insert(['usuario_id' => 9, 'usuario_nombre' => 'Pedro', 'usuario_apellido' => 'Gil', 'usuario_mail' => 'p@x.com', 'fk_tipousuario_id' => 'VEN', 'usuario_interno' => 'Y']);
        DB::table('proveedor')->insert(['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol']);
        DB::table('pais')->insert(['pais_id' => 10, 'pais_nombre' => 'Argentina']);
        DB::table('ciudad')->insert(['ciudad_id' => 1, 'ciudad_nombre' => 'Buenos Aires', 'fk_pais_id' => 10]);
        DB::table('submodulo')->insert(['tipoproducto_id' => 'HOT', 'tipoproducto_nombre' => 'Hoteles', 'submodulo_id' => 'HOT']);
        DB::table('negocio')->insert(['negocio_id' => 2, 'negocio_nombre' => 'Grupo Brasil']);

        DB::table('reserva')->insert([
            ['reserva_id' => 1, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'agente' => 7, 'promotor' => 9, 'fk_usuario_id' => 9, 'fecha_alta' => '2025-03-10', 'fecha_vencimiento' => '2025-04-01', 'inicio' => '2025-05-01', 'titular_apellido' => 'Pérez', 'titular_nombre' => 'Ana', 'fk_moneda_id' => 'USD', 'total' => 1000, 'cobrado' => 400, 'fk_filestatus_id' => 'CO', 'fk_negocio_id' => 2, 'codigo_externo' => 'ITR1'],
            ['reserva_id' => 2, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 4, 'agente' => 9, 'promotor' => 0, 'fk_usuario_id' => 7, 'fecha_alta' => '2025-03-20', 'fecha_vencimiento' => '2025-04-15', 'inicio' => '2025-06-01', 'titular_apellido' => 'López', 'titular_nombre' => 'Juan', 'fk_moneda_id' => 'ARS', 'total' => 500, 'cobrado' => 500, 'fk_filestatus_id' => 'CO', 'fk_negocio_id' => 0, 'codigo_externo' => ''],
            ['reserva_id' => 3, 'tipocodigo' => 'RE', 'codigo' => '102', 'fk_cliente_id' => 3, 'agente' => 7, 'promotor' => 0, 'fk_usuario_id' => 7, 'fecha_alta' => '2025-03-25', 'fecha_vencimiento' => '2025-04-20', 'inicio' => '2025-06-10', 'titular_apellido' => 'Cancelada', 'titular_nombre' => 'X', 'fk_moneda_id' => 'USD', 'total' => 300, 'cobrado' => 0, 'fk_filestatus_id' => 'CA', 'fk_negocio_id' => 0, 'codigo_externo' => ''],
        ]);
        DB::table('servicio')->insert([
            ['servicio_id' => 11, 'servicio_nombre' => 'Hotel Sol dbl', 'fk_reserva_id' => 1, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'fk_ciudad_id' => 1, 'status' => 'CO', 'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 1000, 'iva' => 100, 'renta' => 200, 'comision' => 50, 'costo' => 700, 'iva_costo' => 70, 'cotventa' => 900, 'cotcosto' => 880, 'adultos' => 2, 'menores' => 1, 'origen' => 'UA', 'nro_confirmacion' => 'ABC', 'comisionproveedor_porcentaje' => 10],
            ['servicio_id' => 12, 'servicio_nombre' => 'Traslado', 'fk_reserva_id' => 2, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'fk_ciudad_id' => 1, 'status' => 'CO', 'fk_moneda_id' => 'ARS', 'moneda_costo' => 'ARS', 'total' => 500, 'iva' => 0, 'renta' => 100, 'comision' => 0, 'costo' => 400, 'iva_costo' => 0, 'cotventa' => 1, 'cotcosto' => 1, 'adultos' => 1, 'menores' => 0, 'origen' => '', 'nro_confirmacion' => '', 'comisionproveedor_porcentaje' => 0],
            ['servicio_id' => 13, 'servicio_nombre' => 'Cancelado', 'fk_reserva_id' => 1, 'fk_tipoproducto_id' => 'HOT', 'fk_proveedor_id' => 5, 'fk_ciudad_id' => 1, 'status' => 'CA', 'fk_moneda_id' => 'USD', 'moneda_costo' => 'USD', 'total' => 999, 'iva' => 0, 'renta' => 0, 'comision' => 0, 'costo' => 0, 'iva_costo' => 0, 'cotventa' => 1, 'cotcosto' => 1, 'adultos' => 1, 'menores' => 0, 'origen' => 'UA', 'nro_confirmacion' => '', 'comisionproveedor_porcentaje' => 0],
        ]);
        DB::table('servicio_extra')->insert(['fk_servicio_id' => 11, 'extra_nombre' => 'evt_comisioncosto', 'extra_valor' => '35']);
    }

    public function test_ventas_netas_solo_consulta_con_filtros_y_agrupa_por_moneda(): void
    {
        $this->get('/app/admin/reportes/ventas-netas')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Reportes/Listado')
                ->where('config.consultado', false)
                ->where('grupos', []));

        $this->get('/app/admin/reportes/ventas-netas?fecha_alta=2025-03-01&fecha_alta_to=2025-03-31')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('config.consultado', true)
                ->has('grupos', 2)
                ->where('grupos.0.clave', 'ARS')
                ->where('grupos.1.clave', 'USD')
                ->where('grupos.1.filas.0.n_file', 'RE-100')
                ->where('grupos.1.filas.0.usuario', 'Pow Ana')
                ->where('grupos.1.filas.0.promotor', 'Gil Pedro')
                ->where('grupos.1.filas.0.venta_neta', 900)
                ->where('grupos.1.filas.0.rentabilidad_total', 300)
                ->where('grupos.1.filas.0.porcentaje_rentabilidad_neta', 20)
                ->where('grupos.1.totales.venta_total', 1000));

        // Filtro por cliente reduce al file de la agencia (y el servicio cancelado nunca aparece).
        $this->get('/app/admin/reportes/ventas-netas?fk_cliente_id=4')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 1)->where('grupos.0.clave', 'ARS')->where('grupos.0.filas.0.servicios', 'Traslado'));
    }

    public function test_export_csv_de_ventas_netas(): void
    {
        $resp = $this->get('/app/admin/reportes/ventas-netas/export?fk_cliente_id=3');
        $resp->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $resp->streamedContent();
        $this->assertStringContainsString('USD', $csv);
        $this->assertStringContainsString('RE-100', $csv);
        $this->assertStringContainsString('"N° file";Cliente;Vendedor', $csv);
    }

    public function test_reporte_de_deuda_usa_el_cobrado_real_y_filtra_saldos_en_cero(): void
    {
        $this->mock(ReservaCobradoService::class, function ($m) {
            $m->shouldReceive('total')->with(1, 'USD')->andReturn(400.0);
            $m->shouldReceive('total')->with(2, 'ARS')->andReturn(500.0);
        });

        $this->get('/app/admin/reportes/deuda?fk_cliente_id=3&saldo_0=0')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->where('grupos.0.clave', 'USD')
                ->where('grupos.0.filas.0.n_file', 'RE-100')
                ->where('grupos.0.filas.0.itr', 'ITR1')
                ->where('grupos.0.filas.0.titular', 'Pérez Ana')
                ->where('grupos.0.filas.0.servicios', 'Hotel Sol dbl')
                ->where('grupos.0.filas.0.saldo', 600)
                ->where('grupos.0.totales.saldo', 600));

        // Con saldo_0=1 aparece también el file saldado; el cancelado (CA) nunca.
        $this->get('/app/admin/reportes/deuda?buscar=1&saldo_0=1')
            ->assertInertia(fn (Assert $p) => $p->has('grupos', 2)->where('grupos.0.clave', 'ARS')->where('grupos.0.filas.0.saldo', 0));
    }

    public function test_productos_por_origen_lista_servicios_ua(): void
    {
        $this->get('/app/admin/reportes/productos-origen?fecha=2025-03-01&fecha_to=2025-03-31')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->has('grupos.0.filas', 2)
                ->where('grupos.0.filas.0.codigo', 'RE-100')
                ->where('grupos.0.filas.0.voucher', 'ABC')
                ->where('grupos.0.filas.0.vendedor', 'Ana Pow')
                ->where('grupos.0.filas.0.usuario', 'Pedro Gil')
                ->where('grupos.0.filas.0.total', 1000));
    }

    public function test_cierre_de_grupo_convierte_a_moneda_local_y_trae_comision(): void
    {
        $this->get('/app/operaciones/cierre-grupo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('config.area', 'Operaciones')->where('config.filtros.0.opciones', [['value' => 2, 'label' => 'Grupo Brasil']]));

        $this->get('/app/operaciones/cierre-grupo?negocio=2')
            ->assertInertia(fn (Assert $p) => $p
                ->has('grupos', 1)
                ->where('grupos.0.clave', null)
                ->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.pax', 3)
                ->where('grupos.0.filas.0.proveedor', 'Hotel Sol')
                ->where('grupos.0.filas.0.venta_total', 900000)
                ->where('grupos.0.filas.0.venta_iva', 90000)
                ->where('grupos.0.filas.0.costo_total', 677600)
                ->where('grupos.0.filas.0.comision', 35));
    }
}
