<?php

namespace Tests\Feature\Reservas;

use App\Models\Reserva;
use App\Services\Reservas\ReservaFiltroBuilder;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifica el mapeo filtro -> SQL de ReservaFiltroBuilder sin ejecutar contra la
 * BD (compila el query con toSql/getBindings). Fiel a reserva_model::listar().
 */
class ReservaFiltroBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tenant falso para Licencia::base()/flag().
        app()->instance('tenant', (object) ['base' => 'witwan_rays', 'pais' => 'AR', 'licencia' => 1]);

        // Cache en memoria (el entorno de test no tiene acceso a la BD/cache real)
        // y seed de fileauditado para que el chequeo no consulte sysconfig.
        Cache::swap(new Repository(new ArrayStore));
        Cache::put('sysconfig.fileauditado', '0', 60);
    }

    private function sqlCon(array $filtros): array
    {
        $query = Reserva::query()
            ->leftJoin('servicio', 'servicio.fk_reserva_id', '=', 'reserva.reserva_id')
            ->leftJoin('cliente', 'reserva.fk_cliente_id', '=', 'cliente.cliente_id');

        (new ReservaFiltroBuilder)->aplicar($query, $filtros);

        // Normaliza comillas/backticks para ser agnóstico al grammar (mysql/sqlite).
        $sql = str_replace(['`', '"'], '', strtolower($query->toSql()));

        return ['sql' => $sql, 'bind' => $query->getBindings()];
    }

    public function test_condiciones_base(): void
    {
        ['sql' => $sql] = $this->sqlCon(['status' => ['CO']]);

        $this->assertStringContainsString('reserva.fk_agrupado_id = ?', $sql);
        $this->assertStringContainsString('reserva.reserva_id != ?', $sql);
    }

    public function test_codigo_multiple_con_asterisco(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon(['status' => ['CO'], 'codigo' => '123*456']);

        $this->assertStringContainsString('reserva.codigo in (?, ?)', $sql);
        $this->assertContains('123', $bind);
        $this->assertContains('456', $bind);
    }

    public function test_status_where_in(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon(['status' => ['CO', 'RQ']]);

        $this->assertStringContainsString('reserva.fk_filestatus_id in (?, ?)', $sql);
        $this->assertContains('CO', $bind);
        $this->assertContains('RQ', $bind);
    }

    public function test_canceladas_filtra_c_a_y_joinea_historialfile(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon([
            'status' => ['CO', 'RQ'], 'tipofecha' => 'canceladas', 'from' => '2026-01-01', 'to' => '2026-06-30',
        ]);

        $this->assertStringContainsString('reserva.fk_filestatus_id in (?)', $sql);
        $this->assertContains('CA', $bind);
        $this->assertStringContainsString('historialfile', $sql);
        $this->assertContains('reserva.fk_filestatus_id', $bind);
    }

    public function test_proveedor_excluye_cancelados(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon(['status' => ['CO'], 'proveedor' => 7]);

        $this->assertStringContainsString('servicio.fk_proveedor_id = ?', $sql);
        $this->assertStringContainsString('servicio.status != ?', $sql);
        $this->assertContains('CA', $bind);
    }

    public function test_titular_joinea_servicio_nomina(): void
    {
        ['sql' => $sql] = $this->sqlCon(['status' => ['CO'], 'titular' => 'perez']);

        $this->assertStringContainsString('servicio_nomina', $sql);
        $this->assertStringContainsString('reserva.titular_apellido like ?', $sql);
    }

    public function test_fecha_checkin_usa_reserva_inicio(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon([
            'status' => ['CO'], 'tipofecha' => 'checkin', 'from' => '2026-01-01', 'to' => '2026-12-31',
        ]);

        $this->assertStringContainsString('reserva.inicio >= ?', $sql);
        $this->assertStringContainsString('reserva.inicio <= ?', $sql);
        $this->assertContains('2026-01-01', $bind);
    }

    public function test_fecha_default_usa_fecha_alta(): void
    {
        ['sql' => $sql] = $this->sqlCon(['status' => ['CO'], 'from' => '2026-01-01']);

        $this->assertStringContainsString('reserva.fecha_alta >= ?', $sql);
    }

    public function test_solopagos_y_soloovencidas(): void
    {
        ['sql' => $sql] = $this->sqlCon(['status' => ['CO'], 'solopagos' => '1', 'soloovencidas' => '1']);

        $this->assertStringContainsString('reserva.cobrado != ?', $sql);
        $this->assertStringContainsString('reserva.fecha_vencimiento <= curdate()', $sql);
    }

    public function test_sistema_area(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon(['status' => ['CO'], 'rsv' => 2]);

        $this->assertStringContainsString('reserva.fk_sistemaaplicacion_id = ?', $sql);
        $this->assertContains(2, $bind);
    }

    public function test_tipoproducto_sobre_servicio(): void
    {
        ['sql' => $sql, 'bind' => $bind] = $this->sqlCon(['status' => ['CO'], 'tipoproducto' => 'HOT']);

        $this->assertStringContainsString('servicio.fk_tipoproducto_id = ?', $sql);
        $this->assertContains('HOT', $bind);
    }
}
