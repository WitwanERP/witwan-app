<?php

namespace App\Http\Controllers\Web\Config;

use App\Http\Controllers\Web\Reportes\ReporteController;
use Illuminate\Support\Facades\DB;

/**
 * Configuración > Usuarios > Solicitudes (CI: configuracion/solicitud):
 * pedidos de alta de agencia/usuario desde la web. Listado en modo lectura;
 * aprobar (crea cliente + usuario) y rechazar siguen en el legacy.
 */
class SolicitudesController extends ReporteController
{
    protected string $titulo = 'Solicitudes de alta';

    protected string $ruta = 'config/solicitudes';

    protected string $area = 'Configuración';

    protected string $grupo = 'Usuarios';

    protected ?string $agruparPor = null;

    protected bool $requiereFiltros = false;

    protected function filtros(): array
    {
        return [
            ['campo' => 'status', 'label' => 'Status', 'tipo' => 'select', 'default' => 'PENDIENTE', 'opciones' => [['value' => 'PENDIENTE', 'label' => 'Pendiente'], ['value' => 'APROBADA', 'label' => 'Aprobada'], ['value' => 'RECHAZADA', 'label' => 'Rechazada'], ['value' => 'TODAS', 'label' => 'Todas']]],
            ['campo' => 'fecha', 'label' => 'Fecha', 'tipo' => 'rango'],
            ['campo' => 'texto', 'label' => 'Nombre / apellido / email / empresa', 'tipo' => 'text'],
        ];
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id', 'label' => 'ID'],
            ['campo' => 'fecha', 'label' => 'Fecha'],
            ['campo' => 'status', 'label' => 'Status'],
            ['campo' => 'nombre', 'label' => 'Nombre'],
            ['campo' => 'apellido', 'label' => 'Apellido'],
            ['campo' => 'email', 'label' => 'Email'],
            ['campo' => 'telefono', 'label' => 'Teléfono'],
            ['campo' => 'empresa', 'label' => 'Empresa'],
            ['campo' => 'razon_social', 'label' => 'Razón social'],
            ['campo' => 'clave_fiscal', 'label' => 'Clave fiscal'],
            ['campo' => 'ciudad', 'label' => 'Ciudad'],
            ['campo' => 'acciones', 'label' => 'Acciones', 'tipo' => 'acciones'],
        ];
    }

    protected function consultar(array $f): array
    {
        $q = DB::table('solicitud')->orderByDesc('solicitud_fecha');
        if ($f['status'] !== '' && $f['status'] !== 'TODAS') {
            $q->where('solicitud_status', $f['status']);
        }
        if ($f['texto'] !== '') {
            $t = "%{$f['texto']}%";
            $q->where(fn ($w) => $w->where('solicitud_nombre', 'LIKE', $t)->orWhere('solicitud_apellido', 'LIKE', $t)->orWhere('solicitud_email', 'LIKE', $t)->orWhere('solicitud_empresa', 'LIKE', $t));
        }
        $this->rango($q, 'solicitud_fecha', $f, 'fecha', true);

        return $q->get()->map(fn ($s) => [
            'id' => (int) $s->solicitud_id,
            'fecha' => $this->dmy((string) $s->solicitud_fecha),
            'status' => (string) $s->solicitud_status,
            'nombre' => (string) $s->solicitud_nombre,
            'apellido' => (string) $s->solicitud_apellido,
            'email' => (string) $s->solicitud_email,
            'telefono' => trim((string) $s->solicitud_telefono.' '.(string) $s->solicitud_celular),
            'empresa' => (string) $s->solicitud_empresa,
            'razon_social' => (string) $s->solicitud_rz,
            'clave_fiscal' => (string) $s->solicitud_clavefiscal,
            'ciudad' => (string) $s->solicitud_ciudad,
            'acciones' => (string) $s->solicitud_status === 'PENDIENTE'
                ? [['label' => 'Ver / editar', 'href' => "/configuracion/solicitud/edit/{$s->solicitud_id}"], ['label' => 'Aprobar', 'href' => "/configuracion/solicitud/aprobar/{$s->solicitud_id}"], ['label' => 'Rechazar', 'href' => "/configuracion/solicitud/rechazar/{$s->solicitud_id}", 'peligro' => true]]
                : [['label' => 'Ver', 'href' => "/configuracion/solicitud/edit/{$s->solicitud_id}"]],
        ])->all();
    }
}
