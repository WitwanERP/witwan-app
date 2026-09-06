<?php

namespace Tests\Feature\Documentos;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Smoke HTTP de los listados de documentos en modo lectura (facturas, notas de
 * crédito/débito, recibos, órdenes de pago y de servicio) y de cotizaciones.
 */
class DocumentosListadosTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();

        DB::table('sysconfig')->insert(['sysconfig_key' => 'tasageneral', 'sysconfig_value' => '21']);
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol']);
        DB::table('proveedor')->insert(['proveedor_id' => 5, 'proveedor_nombre' => 'Hotel Sol']);
        DB::table('moneda')->insert(['moneda_id' => 'ARS', 'moneda_nombre' => 'Peso', 'moneda_basica' => 'Y']);
        DB::table('reserva')->insert(['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1]);
        DB::table('servicio')->insert(['servicio_id' => 11, 'servicio_nombre' => 'Hotel', 'fk_reserva_id' => 21, 'fk_proveedor_id' => 5, 'status' => 'CO']);

        $hoy = now()->toDateString();
        DB::table('factura')->insert([
            'factura_id' => 40, 'factura_nro' => '0001-00000040', 'statusfactura' => 'EM', 'factura_fecha' => "{$hoy} 10:00:00", 'factura_tipo' => 'A',
            'factura_sucursal' => '0001', 'factura_conceptos_gravados' => 100, 'factura_conceptos_exentos' => 50, 'factura_impuesto1' => 5,
            'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_usuario_id' => 7, 'fk_moneda_id' => 'ARS', 'remitofull' => '0:0:10',
        ]);
        DB::table('factura')->insert([
            'factura_id' => 41, 'factura_nro' => '0001-00000041', 'statusfactura' => 'EM', 'factura_fecha' => '2020-01-01 10:00:00', 'factura_tipo' => 'B',
            'factura_conceptos_gravados' => 10, 'fk_cliente_id' => 3, 'fk_file_id' => 21, 'fk_moneda_id' => 'ARS',
        ]);
        DB::table('notacredito')->insert(['notacredito_id' => 50, 'notacredito_nro' => 'NC-50', 'statusfactura' => 'EM', 'notacredito_fecha' => "{$hoy} 09:00:00", 'notacredito_tipo' => 'A', 'notacredito_conceptos_gravados' => 100, 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'fk_factura_id' => 40, 'fk_moneda_id' => 'ARS', 'remitofull' => '0:0:5']);
        DB::table('notadebito')->insert(['notadebito_id' => 60, 'notadebito_nro' => 'ND-60', 'statusfactura' => 'EM', 'notadebito_fecha' => "{$hoy} 09:00:00", 'notadebito_tipo' => 'A', 'notadebito_conceptos_gravados' => 200, 'notadebito_conceptos_exentos' => 8, 'fk_cliente_id' => 3, 'fk_moneda_id' => 'ARS']);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_tipo' => 'RC', 'recibo_nro' => 'R-70', 'fecha' => $hoy, 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'statusrecibo' => 'EM', 'monto' => 300, 'fk_moneda_id' => 'ARS', 'observaciones' => 'Pago parcial']);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70]);
        DB::table('movimiento')->insert(['movimiento_id' => 1, 'fk_recibo_id' => 70, 'fk_facturaproveedor_id' => 0, 'fk_moneda_id' => 'ARS', 'cotizacion_moneda' => 1.5, 'monto' => 300, 'fecha' => $hoy]);
        DB::table('ordenadmin')->insert([
            ['ordenadmin_id' => 300, 'fk_ordenadmin_id' => 301, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-1', 'nropago' => '', 'tipo' => 'S', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 500, 'status' => 'PR'],
            ['ordenadmin_id' => 301, 'fk_ordenadmin_id' => 0, 'fk_proveedor_id' => 5, 'fk_usuario_id' => 7, 'fecha' => $hoy, 'nroservicio' => 'OS-1', 'nropago' => 'OP-9', 'tipo' => 'P', 'fk_moneda_id' => 'ARS', 'cotizacion' => 1, 'monto' => 500, 'status' => 'OK'],
        ]);
        DB::table('rel_ordenadminocupacion')->insert([
            ['fk_ordenadmin_id' => 300, 'fk_ocupacion_id' => 11, 'fk_moneda_id' => 'ARS', 'monto' => 500],
            ['fk_ordenadmin_id' => 301, 'fk_ocupacion_id' => 11, 'fk_moneda_id' => 'ARS', 'monto' => 500],
        ]);
        DB::table('ctz')->insert(['ctz_id' => 5, 'fk_cliente_id' => 3, 'fk_sistema_id' => 1, 'fk_usuario_id' => 7, 'fecha_alta' => $hoy, 'codigo' => '900', 'tipocodigo' => 'CT', 'titular_nombre' => 'Ana', 'titular_apellido' => 'Pérez', 'fk_moneda_id' => 'ARS', 'total' => 1200]);
        DB::table('servicioctz')->insert([
            ['servicio_nombre' => 'Hotel cotizado', 'fk_reserva_id' => 5, 'vigencia_ini' => '2026-12-01', 'adultos' => 2, 'menores' => 0],
            ['servicio_nombre' => 'Traslado cotizado', 'fk_reserva_id' => 5, 'vigencia_ini' => '2026-12-01', 'adultos' => 2, 'menores' => 1],
        ]);
    }

    public function test_facturas_calcula_total_como_el_ci_y_lista_ultimos_3_meses(): void
    {
        // total = round(100*1.21 + 50 + 5, 2) - 10 (remito) = 166
        $this->get('/app/documentos/facturas')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Reportes/Listado')
                ->where('config.grupo', 'Documentos')
                ->where('config.limite', 500)
                ->where('config.acciones.0.href', '/administracion/factura/create')
                ->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.numero', '0001-00000040')
                ->where('grupos.0.filas.0.reserva', 'RE-100')
                ->where('grupos.0.filas.0.total', 166)
                ->where('grupos.0.filas.0.usuario', 'Ana Pow')
                ->where('grupos.0.filas.0.acciones.4.label', 'Anular'));

        // Con un filtro explícito deja de aplicar el rango por defecto.
        $this->get('/app/documentos/facturas?factura_tipo=B')
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.numero', '0001-00000041')->where('grupos.0.filas.0.total', 12.1));
    }

    public function test_notas_de_credito_y_debito(): void
    {
        $this->get('/app/documentos/notas-credito')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.numero', 'NC-50')
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.total', 126)
                ->where('grupos.0.filas.0.acciones.2.label', 'Generar ND'));

        $this->get('/app/documentos/notas-debito?fk_cliente_id=3')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)->where('grupos.0.filas.0.numero', 'ND-60')->where('grupos.0.filas.0.total', 250));
    }

    public function test_recibos_con_tipo_de_cambio_y_reserva(): void
    {
        $this->get('/app/documentos/recibos?recibo_nro=R-7')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.numero', 'R-70')
                ->where('grupos.0.filas.0.reserva', 'RE-100')
                ->where('grupos.0.filas.0.monto', 300)
                ->where('grupos.0.filas.0.tipocambio', 1.5)
                ->where('grupos.0.filas.0.creadopor', 'Pow, Ana')
                ->where('grupos.0.filas.0.observaciones', 'Pago parcial'));
    }

    public function test_ordenes_de_pago_y_de_servicio(): void
    {
        $this->get('/app/documentos/ordenes-pago')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.numero', 'OP-9')
                ->where('grupos.0.filas.0.file', 'RE-100')
                ->where('grupos.0.filas.0.proveedor', 'Hotel Sol')
                ->where('grupos.0.filas.0.relacionada', 'OS-1')
                ->where('grupos.0.filas.0.status', 'OK'));

        $this->get('/app/documentos/ordenes-servicio')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.numero', 'OS-1')
                ->where('grupos.0.filas.0.relacionada', 'OP-9')
                ->where('grupos.0.filas.0.file_link', '/reserva/editar/21')
                ->where('grupos.0.filas.0.status', 'PR'));

        $this->get('/app/documentos/ordenes-servicio?status=AN')->assertInertia(fn (Assert $p) => $p->where('grupos', []));
    }

    public function test_cotizaciones_por_area(): void
    {
        $this->get('/app/cotizaciones/receptivo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('config.baseUrl', '/app/cotizaciones/receptivo')
                ->where('config.titulo', 'Cotizaciones (Receptivo)')
                ->has('grupos.0.filas', 1)
                ->where('grupos.0.filas.0.codigo', 'CT-900')
                ->where('grupos.0.filas.0.pax', 3)
                ->where('grupos.0.filas.0.checkin', '01/12/2026')
                ->where('grupos.0.filas.0.total', 1200)
                ->where('grupos.0.filas.0.acciones.1.href', '/reserva/ctzareserva/5'));

        $this->get('/app/cotizaciones/mayorista')->assertInertia(fn (Assert $p) => $p->where('grupos', []));
        $this->get('/app/cotizaciones/all?titular=pér')->assertInertia(fn (Assert $p) => $p->has('grupos.0.filas', 1));
        $this->get('/app/cotizaciones/inexistente')->assertNotFound();
    }
}
