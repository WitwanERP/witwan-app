<?php

namespace Tests\Feature;

use App\Services\Reservas\ReservaListadoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

/**
 * Inicio: widgets por permiso, gráficos de reservas/cobranzas por período y
 * tablas de mis reservas / operaciones / cotizaciones.
 */
class DashboardTest extends TestCase
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
        DB::table('cliente')->insert(['cliente_id' => 3, 'cliente_nombre' => 'Agencia Sol']);
        DB::table('sistema')->insert(['sistema_id' => 1, 'sistema_nombre' => 'Receptivo']);

        $hoy = now()->toDateString();
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'RE', 'codigo' => '100', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CO', 'fecha_alta' => $hoy, 'fk_moneda_id' => 'USD', 'total' => 1000, 'observaciones' => ''],
            ['reserva_id' => 22, 'tipocodigo' => 'RE', 'codigo' => '101', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CO', 'fecha_alta' => $hoy, 'fk_moneda_id' => 'ARS', 'total' => 500, 'observaciones' => ''],
            ['reserva_id' => 23, 'tipocodigo' => 'RE', 'codigo' => '102', 'fk_cliente_id' => 3, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 1, 'fk_filestatus_id' => 'CA', 'fecha_alta' => $hoy, 'fk_moneda_id' => 'ARS', 'total' => 100, 'observaciones' => ''],
        ]);
        // costo/renta/impuestos se cargan aparte para no chocar con el insert múltiple.
        DB::table('reserva')->where('reserva_id', 21)->update(['costo' => 10, 'renta' => 2, 'impuestos' => 1]);
        DB::table('reserva')->where('reserva_id', 22)->update(['costo' => 1000, 'renta' => 100, 'impuestos' => 50]);
        DB::table('recibo')->insert(['recibo_id' => 70, 'recibo_nro' => 'R-70', 'fecha' => $hoy, 'fk_cliente_id' => 3, 'statusrecibo' => 'EM', 'monto' => 300, 'fk_moneda_id' => 'ARS']);
        DB::table('rel_filerecibo')->insert(['fk_file_id' => 21, 'fk_recibo_id' => 70, 'monto' => 2000, 'fecha' => $hoy, 'fk_moneda_id' => 'ARS']);
        DB::table('sysnotification')->insert([
            ['fk_usuario_id' => 7, 'sysnotification_nombre' => 'File RE-100 vence', 'sysnotification_url' => '/reserva/editar/21', 'sysnotification_type' => 'reservas24', 'sysnotification_icon' => '2'],
            ['fk_usuario_id' => 7, 'sysnotification_nombre' => 'File RE-100 vence', 'sysnotification_url' => '/reserva/editar/21', 'sysnotification_type' => 'reservas24', 'sysnotification_icon' => '2'],
            ['fk_usuario_id' => 9, 'sysnotification_nombre' => 'De otro usuario', 'sysnotification_url' => '', 'sysnotification_type' => 'reservasv', 'sysnotification_icon' => '1'],
        ]);
        DB::table('ctz')->insert(['ctz_id' => 5, 'fk_cliente_id' => 3, 'fk_sistema_id' => 1, 'fk_usuario_id' => 7, 'fecha_alta' => $hoy, 'codigo' => '900', 'tipocodigo' => 'CT', 'titular_nombre' => 'Ana', 'titular_apellido' => 'Pérez', 'fk_moneda_id' => 'ARS', 'total' => 1200, 'fk_filestatus_id' => 'CT']);
    }

    private function mockListado(): void
    {
        $this->mock(ReservaListadoService::class, function ($m) {
            $m->shouldReceive('listar')->andReturn([
                'registros' => new LengthAwarePaginator([[
                    'id' => 21, 'icono' => 'Receptivo', 'ncodigo' => 'RE-100', 'status' => 'CO', 'cliente_nombre' => 'Agencia Sol', 'usuario' => 'Ana Pow',
                    'titular' => 'Pérez, Ana', 'fecha_alta' => '01/09/2026', 'fecha_vencimiento' => '10/09/2026', 'moneda' => 'USD', 'total' => 1000,
                ]], 1, 15),
                'totales' => [],
            ]);
        });
    }

    public function test_pow_ve_todos_los_widgets_con_datos(): void
    {
        $this->mockListado();

        $this->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Dashboard')
                ->where('modulos', array_values(\App\Services\DashboardService::MODULOS))
                ->has('notificaciones', 1)
                ->where('notificaciones.0.nombre', 'File RE-100 vence')
                ->where('notificaciones.0.cantidad', 2)
                ->where('notificaciones.0.prioridad.label', '24 horas')
                ->where('notificaciones.0.area', 'Mayorista')
                ->has('misReservas', 1)
                ->where('misReservas.0.codigo', 'RE-100')
                ->where('misReservas.0.href', '/reserva/editar/21')
                ->has('misCotizaciones', 1)
                ->where('misCotizaciones.0.codigo', 'CT-900')
                ->where('misCotizaciones.0.area', 'Receptivo'));
    }

    public function test_rol_sin_permisos_de_inicio_no_ve_widgets(): void
    {
        DB::table('tipousuario')->insert(['tipousuario_id' => 'VEN', 'tipousuario_nombre' => 'Vendedor']);
        DB::table('usuario')->insert(['usuario_id' => 9, 'usuario_nombre' => 'Vera', 'usuario_mail' => 'v@x.com', 'fk_tipousuario_id' => 'VEN']);
        DB::table('permisogrupo')->insert(['fk_tipousuario_id' => 'VEN', 'fk_seccion_id' => 208, 'permisogrupo_nombre' => 'inicio_vencimientos', 'permisogrupo_valor' => 1]);
        $this->actingAs(\App\Models\User::find(9), 'web');

        $this->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('modulos', ['inicio_vencimientos'])->where('misReservas', [])->where('misCotizaciones', []));
    }

    public function test_grafico_de_reservas_por_periodo_convierte_a_usd_equivalente(): void
    {
        // Sin área: 2 reservas CL/CO (la cancelada no). USD 10 => rel 1000/1000 = 1; ARS 1000 => rel 1/1000 = 1.
        $this->get('/app/dashboard/reservas?periodo=mes')
            ->assertOk()
            ->assertJsonPath('totales.reservas', 2)
            ->assertJsonPath('totales.costo', 11)
            ->assertJsonPath('totales.renta', 2)
            ->assertJsonPath('series.0.total', 2)
            ->assertJsonPath('series.0.clave', now()->format('d/m'));

        $this->get('/app/dashboard/reservas?sistema=1&periodo=anio')
            ->assertJsonPath('totales.reservas', 1)
            ->assertJsonPath('series.0.clave', now()->format('M-Y'));
    }

    public function test_grafico_de_cobranzas(): void
    {
        $this->get('/app/dashboard/cobranzas?periodo=semana')
            ->assertOk()
            ->assertJsonPath('totales.cobranzas', 1)
            ->assertJsonPath('totales.total', 2)
            ->assertJsonPath('series.0.clave', now()->format('d/m/Y'));
    }
}
