<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Catálogos chicos del tenant en formato de opciones para los selects de los
 * ABMs (['value'=>..,'label'=>..,'padre'=>..?]). Réplica de los `options.sql`
 * que cada controller del CI escribía a mano.
 *
 * Se memoizan por request: un mismo form suele pedir el mismo catálogo para
 * el filtro del listado y para el campo.
 */
class CatalogosService
{
    /** @var array<string,list<array>> */
    private array $memo = [];

    /** Tipos de producto (tabla submodulo: 'HOT' => Hoteles). */
    public function submodulos(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('submodulo')
            ->orderBy('tipoproducto_nombre')
            ->get(['tipoproducto_id', 'tipoproducto_nombre'])
            ->map(fn ($r) => ['value' => $r->tipoproducto_id, 'label' => $r->tipoproducto_nombre])
            ->all();
    }

    public function paises(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('pais')
            ->orderBy('pais_nombre')
            ->get(['pais_id', 'pais_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->pais_id, 'label' => $r->pais_nombre])
            ->all();
    }

    /** Ciudades principales (fk_ciudad_id = 0) con su país como 'padre' para selects dependientes. */
    public function ciudades(int $limite = 5000): array
    {
        return $this->memo[__FUNCTION__.$limite] ??= DB::table('ciudad')
            ->where('fk_ciudad_id', 0)
            ->orderBy('ciudad_nombre')
            ->limit($limite)
            ->get(['ciudad_id', 'ciudad_nombre', 'fk_pais_id'])
            ->map(fn ($r) => ['value' => (int) $r->ciudad_id, 'label' => $r->ciudad_nombre, 'padre' => (int) $r->fk_pais_id])
            ->all();
    }

    public function monedas(bool $soloNoBasica = false): array
    {
        $q = DB::table('moneda')->orderBy('orden')->orderBy('moneda_id');
        if ($soloNoBasica) {
            $q->where('moneda_basica', '<>', 'Y');
        }

        return $this->memo[__FUNCTION__.(int) $soloNoBasica] ??= $q->get(['moneda_id', 'moneda_nombre'])
            ->map(fn ($r) => ['value' => $r->moneda_id, 'label' => "{$r->moneda_id} - {$r->moneda_nombre}"])
            ->all();
    }

    /** Plan de cuentas completo, con código entre paréntesis como en el CI. */
    public function planCuentas(bool $conCodigo = true): array
    {
        return $this->memo[__FUNCTION__.(int) $conCodigo] ??= DB::table('plancuenta')
            ->orderBy('plancuenta_nombre')
            ->get(['plancuenta_id', 'plancuenta_nombre', 'plancuenta_codigo'])
            ->map(fn ($r) => [
                'value' => (int) $r->plancuenta_id,
                'label' => $conCodigo ? "{$r->plancuenta_nombre} ({$r->plancuenta_codigo})" : $r->plancuenta_nombre,
            ])
            ->all();
    }

    public function proveedores(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('proveedor')
            ->where('eliminar', '<>', 'Y')
            ->orderBy('proveedor_nombre')
            ->get(['proveedor_id', 'proveedor_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->proveedor_id, 'label' => $r->proveedor_nombre])
            ->all();
    }

    /** Usuarios internos (usuario_interno Y/1) como "Apellido Nombre". */
    public function usuariosInternos(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('usuario')
            ->whereIn('usuario_interno', ['Y', '1'])
            ->orderBy('usuario_apellido')
            ->get(['usuario_id', 'usuario_nombre', 'usuario_apellido'])
            ->map(fn ($r) => ['value' => (int) $r->usuario_id, 'label' => trim("{$r->usuario_apellido} {$r->usuario_nombre}")])
            ->all();
    }

    public function modelosComision(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('modelocomision')
            ->orderBy('modelocomision_nombre')
            ->get(['modelocomision_id', 'modelocomision_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->modelocomision_id, 'label' => $r->modelocomision_nombre])
            ->all();
    }

    public function modosIvaVenta(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('modoivaventa')
            ->orderBy('modoivaventa_id')
            ->get(['modoivaventa_id', 'modoivaventa_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->modoivaventa_id, 'label' => $r->modoivaventa_nombre])
            ->all();
    }

    /** Productos habilitados y no eliminados (como el select de tabla de IVA del CI). */
    public function productos(int $limite = 5000): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('producto')
            ->where('producto_nombre', '<>', '')
            ->where('habilitar', 1)
            ->where('eliminar', 0)
            ->orderBy('producto_nombre')
            ->limit($limite)
            ->get(['producto_id', 'producto_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->producto_id, 'label' => $r->producto_nombre])
            ->all();
    }

    public function tiposUsuario(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('tipousuario')
            ->orderBy('tipousuario_nombre')
            ->get(['tipousuario_id', 'tipousuario_nombre'])
            ->map(fn ($r) => ['value' => $r->tipousuario_id, 'label' => $r->tipousuario_nombre])
            ->all();
    }

    public function idiomas(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('idioma')
            ->orderBy('orden')
            ->get(['idioma_id', 'idioma_nombre'])
            ->map(fn ($r) => ['value' => $r->idioma_id, 'label' => $r->idioma_nombre])
            ->all();
    }

    public function clientes(int $limite = 5000): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('cliente')
            ->orderBy('cliente_nombre')
            ->limit($limite)
            ->get(['cliente_id', 'cliente_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->cliente_id, 'label' => $r->cliente_nombre])
            ->all();
    }

    public function regiones(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('region')
            ->orderBy('region_nombre')
            ->get(['region_id', 'region_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->region_id, 'label' => $r->region_nombre])
            ->all();
    }

    /**
     * Prefijos de reserva de la licencia (réplica de Modelocomision.php:12-24 del
     * CI): sistema_codigo de cada sistema más los códigos extra de texto_extra3.
     */
    public function prefijosReserva(): array
    {
        return $this->memo[__FUNCTION__] ??= (function () {
            $out = [['value' => '', 'label' => 'N/A']];
            foreach (DB::table('sistema')->get(['sistema_codigo', 'texto_extra3']) as $s) {
                $codigos = array_merge([(string) $s->sistema_codigo], explode(',', (string) $s->texto_extra3));
                foreach ($codigos as $c) {
                    $c = trim($c);
                    if ($c !== '' && ! in_array($c, array_column($out, 'value'), true)) {
                        $out[] = ['value' => $c, 'label' => $c];
                    }
                }
            }

            return $out;
        })();
    }

    /** Tipos de código de file usados (DISTINCT reserva.tipocodigo, como Modelofee.php). */
    public function tiposCodigo(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('reserva')
            ->distinct()
            ->orderBy('tipocodigo')
            ->pluck('tipocodigo')
            ->filter(fn ($t) => (string) $t !== '')
            ->map(fn ($t) => ['value' => $t, 'label' => $t])
            ->values()
            ->all();
    }

    /** Sistemas del tenant (id => nombre), para encabezados de la matriz de permisos. */
    public function sistemas(): array
    {
        return $this->memo[__FUNCTION__] ??= DB::table('sistema')
            ->orderBy('item_order')
            ->get(['sistema_id', 'sistema_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->sistema_id, 'label' => $r->sistema_nombre])
            ->all();
    }
}
