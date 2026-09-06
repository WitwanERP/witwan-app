<?php

namespace App\Http\Controllers\Web\Abm;

use App\Services\CatalogosService;

/** Administración > Usuarios > Perfiles de comisión (CI: administracion/usuariocomision, tabla rel_usuariomodelocomision). */
class UsuariocomisionController extends AbmController
{
    protected string $tabla = 'rel_usuariomodelocomision';

    protected string $pk = 'rel_usuariomodelocomision_id';

    protected string $ruta = 'admin/perfiles-comision';

    protected string $titulo = 'Perfiles de comisión';

    protected string $singular = 'Perfil de comisión';

    protected string $area = 'Administración';

    protected int $porPagina = 350;

    protected array $columnasListado = [
        ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'opciones' => 'usuarios'],
        ['campo' => 'fk_modelocomision_id', 'label' => 'Modelo', 'opciones' => 'modelos'],
    ];

    protected array $filtrosSelect = [
        ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'opciones' => 'usuarios'],
        ['campo' => 'fk_modelocomision_id', 'label' => 'Modelo', 'opciones' => 'modelos'],
    ];

    protected string $sortDefault = 'fk_modelocomision_id';

    public function __construct(private CatalogosService $catalogos) {}

    protected function campos(): array
    {
        return [
            ['campo' => 'fk_usuario_id', 'label' => 'Usuario', 'tipo' => 'select', 'required' => true, 'opciones' => 'usuarios'],
            ['campo' => 'fk_modelocomision_id', 'label' => 'Modelo', 'tipo' => 'select', 'required' => true, 'opciones' => 'modelos'],
        ];
    }

    protected function opciones(): array
    {
        return [
            'usuarios' => $this->catalogos->usuariosInternos(),
            'modelos' => $this->catalogos->modelosComision(),
        ];
    }
}
