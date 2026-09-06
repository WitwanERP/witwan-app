<?php

namespace Tests\Feature\Reportes;

use App\Helpers\SysconfigHelper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaEsquemaConfiguracion;
use Tests\TestCase;

class ReservasAFacturarTest extends TestCase
{
    use CreaEsquemaConfiguracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConfiguracion();
        $this->autenticarPow();
        SysconfigHelper::olvidar('facturapracial');
        SysconfigHelper::olvidar('factura_vertodos');

        DB::table('sistema')->insert(['sistema_id' => 2, 'sistema_nombre' => 'Mayorista', 'sistema_codigo' => 'MA']);
        DB::table('cliente')->insert([
            ['cliente_id' => 3, 'cliente_nombre' => 'AGENCIA SOL', 'cliente_razonsocial' => 'Sol', 'cuit' => '1', 'facturacion_periodo' => 0],
            ['cliente_id' => 4, 'cliente_nombre' => 'FACTURAR A', 'cliente_razonsocial' => 'FA', 'cuit' => '2', 'facturacion_periodo' => 0],
        ]);
        $hoy = now()->toDateString();
        // 21: CL, vence en 3 días, sin factura -> aparece por defecto con FACTURAR.
        // 22: CL, vence en 3 días, facturado (servicio con factura) -> aparece con la factura, sin FACTURAR.
        // 23: CO -> no aparece (sólo CL). 24: CL pero vence en 30 días -> no aparece sin filtros.
        DB::table('reserva')->insert([
            ['reserva_id' => 21, 'tipocodigo' => 'MA', 'codigo' => '100', 'fk_cliente_id' => 3, 'facturar_a' => 4, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CL', 'fecha_vencimiento' => now()->addDays(3)->toDateString(), 'fecha_alta' => $hoy, 'fk_moneda_id' => 'USD', 'total' => 300, 'cobrado' => 100, 'renta' => 50, 'titular_nombre' => 'Ana', 'titular_apellido' => 'Pérez'],
            ['reserva_id' => 22, 'tipocodigo' => 'MA', 'codigo' => '101', 'fk_cliente_id' => 3, 'facturar_a' => 0, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CL', 'fecha_vencimiento' => now()->addDays(3)->toDateString(), 'fecha_alta' => $hoy, 'fk_moneda_id' => 'USD', 'total' => 500, 'cobrado' => 500, 'renta' => 80, 'titular_nombre' => 'Luis', 'titular_apellido' => 'Gómez'],
            ['reserva_id' => 23, 'tipocodigo' => 'MA', 'codigo' => '102', 'fk_cliente_id' => 3, 'facturar_a' => 0, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CO', 'fecha_vencimiento' => now()->addDays(3)->toDateString(), 'fecha_alta' => $hoy, 'fk_moneda_id' => 'USD', 'total' => 10, 'cobrado' => 0, 'renta' => 1, 'titular_nombre' => 'X', 'titular_apellido' => 'Y'],
            ['reserva_id' => 24, 'tipocodigo' => 'MA', 'codigo' => '103', 'fk_cliente_id' => 3, 'facturar_a' => 0, 'fk_usuario_id' => 7, 'agente' => 7, 'fk_sistema_id' => 2, 'fk_filestatus_id' => 'CL', 'fecha_vencimiento' => now()->addDays(30)->toDateString(), 'fecha_alta' => $hoy, 'fk_moneda_id' => 'USD', 'total' => 10, 'cobrado' => 0, 'renta' => 1, 'titular_nombre' => 'X', 'titular_apellido' => 'Z'],
        ]);
        foreach ([21 => 11, 22 => 12, 23 => 13, 24 => 14] as $rid => $sid) {
            DB::table('servicio')->insert(['servicio_id' => $sid, 'servicio_nombre' => "S{$sid}", 'fk_reserva_id' => $rid, 'fk_proveedor_id' => 5, 'fk_tipoproducto_id' => 'HTL', 'status' => 'CL', 'vigencia_ini' => '2026-12-01']);
        }
        DB::table('factura')->insert(['factura_id' => 40, 'factura_nro' => '0001-40', 'statusfactura' => 'EM', 'factura_fecha' => "{$hoy} 10:00:00", 'factura_tipo' => 'A', 'fk_cliente_id' => 3, 'fk_file_id' => 22, 'fk_moneda_id' => 'USD']);
        DB::table('rel_serviciofactura')->insert(['fk_servicio_id' => 12, 'fk_factura_id' => 40, 'tipodocumento' => 1]);
    }

    public function test_sin_filtros_lista_files_cl_que_vencen_en_7_dias_con_factura_o_boton_facturar(): void
    {
        $this->get('/app/admin/reportes/reservas-a-facturar')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Reportes/Listado')
            ->has('grupos.0.filas', 2)
            ->where('grupos.0.filas.0.codigo', 'MA-101')
            ->where('grupos.0.filas.0.facturas', '0001-40')
            ->where('grupos.0.filas.0.acciones.0.label', '0001-40')
            ->has('grupos.0.filas.0.acciones', 1)
            ->where('grupos.0.filas.1.codigo', 'MA-100')
            ->where('grupos.0.filas.1.facturar_a', 'FACTURAR A')
            ->where('grupos.0.filas.1.cliente_reserva', 'AGENCIA SOL')
            ->where('grupos.0.filas.1.saldo', 200)
            ->where('grupos.0.filas.1.acciones.0.label', 'FACTURAR')
            ->where('grupos.0.totales.venta', 800)
        );
    }

    public function test_filtros_facturado_codigo_y_saldo(): void
    {
        $this->get('/app/admin/reportes/reservas-a-facturar?facturado=0')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)->where('grupos.0.filas.0.codigo', 'MA-100'));
        $this->get('/app/admin/reportes/reservas-a-facturar?facturado=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)->where('grupos.0.filas.0.codigo', 'MA-101'));
        // Con filtro de código ya no aplica el rango de vencimiento: aparece el 103.
        $this->get('/app/admin/reportes/reservas-a-facturar?codigo=103')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)->where('grupos.0.filas.0.codigo', 'MA-103'));
        $this->get('/app/admin/reportes/reservas-a-facturar?codigo=100*101&reportesaldo=3')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)->where('grupos.0.filas.0.codigo', 'MA-101'));
        $this->get('/app/admin/reportes/reservas-a-facturar?fk_cliente_id=4')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)->where('grupos.0.filas.0.codigo', 'MA-100'));
    }

    public function test_factura_vertodos_y_facturacion_acumulada(): void
    {
        DB::table('sysconfig')->insert(['sysconfig_key' => 'factura_vertodos', 'sysconfig_value' => '1']);
        $this->get('/app/admin/reportes/reservas-a-facturar')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 2)
            ->where('grupos.0.filas.0.codigo', 'MA-103')
            ->where('grupos.0.filas.1.codigo', 'MA-100'));

        // Acumulada: sin cliente no consulta; con cliente por período lista servicios sin facturar.
        $this->get('/app/admin/reportes/facturacion-acumulada')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('config.titulo', 'Facturación acumulada')->where('config.consultado', false));
        DB::table('cliente')->where('cliente_id', 3)->update(['facturacion_periodo' => 1, 'tipofacturacion' => 1]);
        // Cliente 3 factura los files 22 (ya facturado: excluido) y 24 (servicio sin factura). El 21 se factura al cliente 4.
        $this->get('/app/admin/reportes/facturacion-acumulada?fk_cliente_id=3')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('grupos.0.filas', 1)
            ->where('grupos.0.filas.0.codigo', 'MA-103'));
    }
}
