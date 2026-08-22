<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\Permisos;
use App\Support\Secciones;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Gates de permisos con modo de despliegue.
 *
 * El CI no chequeaba permisos en el alta/edición de facturas de tercero, así que
 * hay roles operando sin tenerlos cargados. El modo observación existe para
 * medir el impacto antes de cortarles el acceso.
 */
class PermisosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('tenant', (object) ['base' => 'licencia_test', 'pais' => 'AR', 'licencia' => 1]);
    }

    public function test_sin_usuario_autenticado_no_hay_permiso(): void
    {
        $this->assertFalse(Permisos::tiene(Secciones::FACTURA_TERCERO, 'alta'));
    }

    public function test_el_superadmin_tiene_todos_los_permisos(): void
    {
        $this->autenticar('POW');

        $this->assertTrue(Permisos::tiene(Secciones::FACTURA_TERCERO, 'alta'));
        $this->assertTrue(Permisos::tiene(Secciones::FACTURA_TERCERO, 'borrado'));
    }

    public function test_en_modo_estricto_la_falta_de_permiso_corta_con_403(): void
    {
        config(['facturaproveedor.permisos_estrictos' => true]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No tiene permiso para alta en Facturas de Terceros.');

        Permisos::exigir(Secciones::FACTURA_TERCERO, 'alta', 'Facturas de Terceros');
    }

    /**
     * En observación la acción sigue adelante: es el modo con el que se
     * despliega, porque en el tenant relevado 5 de 9 roles con acceso a la
     * sección no tienen 'alta' cargado y hoy cargan facturas igual.
     */
    public function test_en_modo_observacion_la_accion_continua(): void
    {
        config(['facturaproveedor.permisos_estrictos' => false]);

        Permisos::exigir(Secciones::FACTURA_TERCERO, 'alta', 'Facturas de Terceros');

        $this->assertTrue(true, 'No debe lanzar excepción en modo observación');
    }

    public function test_el_superadmin_pasa_el_gate_en_modo_estricto(): void
    {
        config(['facturaproveedor.permisos_estrictos' => true]);
        $this->autenticar('POW');

        Permisos::exigir(Secciones::FACTURA_TERCERO, 'alta', 'Facturas de Terceros');

        $this->assertTrue(true);
    }

    private function autenticar(string $rol): void
    {
        $usuario = new User;
        $usuario->usuario_id = 1;
        $usuario->fk_tipousuario_id = $rol;

        Auth::setUser($usuario);
    }
}
