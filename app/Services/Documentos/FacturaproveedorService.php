<?php

namespace App\Services\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Helpers\SysconfigHelper;
use App\Models\Facturaproveedor;
use App\Models\Plancuenta;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Services\TablaLegacyService;
use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta, edición, baja y consulta de facturas de tercero.
 *
 * Orquesta el caso de uso completo de `factura3ero::save()`
 * (application/controllers/administracion/factura3ero.php:856-1407) delegando
 * cada parte: el cálculo en FacturaproveedorCalculo, la imputación de servicios
 * en FacturaproveedorOcupacionService, el reparto entre bases en
 * FacturaproveedorSplitService y la contabilidad en FacturaproveedorAsientoService.
 *
 * Diferencia con el legacy: todo el alta corre dentro de una transacción. La
 * tabla es MyISAM, así que en el CI un fallo a mitad de camino dejaba la factura
 * sin asiento (o con medio asiento) sin forma de deshacerlo.
 */
class FacturaproveedorService
{
    /** Campos que `save_after_edit` permite modificar (factura3ero.php:1551-1578). */
    private const CAMPOS_EDITABLES = [
        'fk_plancuenta_id',
        'fk_proyecto_id',
        'fk_itemgasto_id',
        'facturaproveedor_nro',
    ];

    public function __construct(
        private FacturaproveedorOcupacionService $ocupaciones,
        private FacturaproveedorSplitService $split,
        private FacturaproveedorAsientoService $asientos,
        private TablaLegacyService $tablas,
    ) {}

    /**
     * Da de alta la factura completa y devuelve su id.
     *
     * @throws FacturaproveedorException
     */
    public function crear(array $datos, int $usuarioId): int
    {
        $datos = $this->normalizar($datos);
        $tipoMovimiento = (string) $datos['tipomovimiento'];
        $esNotaCredito = $datos['facturaproveedor_tipodocumento'] === 'Nota de Credito';

        // Gasto y Boleta generan su propio servicio contable: no admiten que el
        // usuario impute servicios de reserva.
        $ocupaciones = $datos['ocupacion'] ?? [];
        if (in_array($tipoMovimiento, ['Gasto', 'Boleta'], true) && ! empty($ocupaciones)) {
            throw FacturaproveedorException::noAdmiteOcupaciones($tipoMovimiento);
        }

        if ($this->existeDuplicado($datos['facturaproveedor_nro'], (int) $datos['fk_proveedor_id'], $datos['facturaproveedor_tipodocumento'])) {
            throw FacturaproveedorException::duplicada($datos['facturaproveedor_nro']);
        }

        $calculo = FacturaproveedorCalculo::paraLicenciaActual();
        $montos = $calculo->calcular($datos, $datos['adicionales'] ?? []);

        $this->validarImputacion($datos);

        return DB::transaction(function () use ($datos, $montos, $calculo, $usuarioId, $tipoMovimiento, $esNotaCredito, $ocupaciones) {
            $facturaId = $this->insertarCabecera($datos, $montos, $usuarioId);

            // Las facturas que no imputan servicios reales cuelgan de un servicio
            // administrativo sintético.
            if ($tipoMovimiento !== 'Servicio') {
                $this->ocupaciones->crearServicioAdministrativo($datos, $montos, $facturaId, $esNotaCredito);
            }

            if (! empty($ocupaciones)) {
                $this->ocupaciones->imputar($facturaId, $ocupaciones);
            }

            foreach ($this->split->plan($datos, $montos, $facturaId, $usuarioId) as $tramo) {
                $tramo = $tramo->conSigno($esNotaCredito);
                $this->asientos->generar($tramo->conexion, $datos, $montos, $calculo, $tramo->facturaId, $tramo->proporcion, $usuarioId);
            }

            return $facturaId;
        });
    }

    /**
     * Edición acotada, igual que el legacy: cuenta contable, proyecto, item de
     * gasto, imputación y número.
     *
     * A diferencia del CI, acá SÍ se rehace el asiento. En `factura3ero` cambiar
     * la cuenta contable dejaba el movimiento con la cuenta anterior; el hermano
     * de carga múltiple ya lo recontabilizaba (factura3erom.php:801-825) y se
     * toma ese comportamiento como el correcto.
     */
    public function actualizar(int $id, array $datos, int $usuarioId): void
    {
        $factura = Facturaproveedor::findOrFail($id);

        if ($this->periodoCerrado($factura->fechacontable)) {
            throw FacturaproveedorException::periodoCerrado();
        }

        $cambios = [];
        foreach (self::CAMPOS_EDITABLES as $campo) {
            if (array_key_exists($campo, $datos)) {
                $cambios[$campo] = in_array($campo, ['fk_plancuenta_id', 'fk_proyecto_id', 'fk_itemgasto_id'], true)
                    ? (int) $datos[$campo]
                    : $datos[$campo];
            }
        }

        if (array_key_exists('areaimputacion', $datos)) {
            $this->validarImputacion($datos);
            $cambios['imputacion'] = $this->imputacionJson($datos);
        }

        if ($cambios === []) {
            return;
        }

        DB::transaction(function () use ($factura, $cambios, $id, $usuarioId) {
            $this->tablas->actualizar('facturaproveedor', 'facturaproveedor_id', $id, $cambios);

            $actualizada = $factura->fresh();
            $datosAsiento = $this->datosDesdeFila($actualizada);

            $calculo = FacturaproveedorCalculo::paraLicenciaActual();
            $montos = $calculo->calcular($datosAsiento);

            $esNotaCredito = $actualizada->facturaproveedor_tipodocumento === 'Nota de Credito';
            $this->asientos->regenerar($datosAsiento, $montos, $calculo, $id, $esNotaCredito ? -1.0 : 1.0, $usuarioId);
        });
    }

    /**
     * Baja física, como el legacy: la tabla no tiene columna de estado y la
     * comparte el CI, que sigue borrando de verdad.
     */
    public function eliminar(int $id): void
    {
        $factura = Facturaproveedor::findOrFail($id);

        if ($this->periodoCerrado($factura->fechacontable)) {
            throw FacturaproveedorException::periodoCerrado();
        }

        // Servicio y BSP quedan exentos del control de pago (factura3ero.php:1533).
        if (! in_array($factura->tipomovimiento, ['Servicio', 'BSP'], true) && $this->tieneOrdenDePagoActiva($id)) {
            throw FacturaproveedorException::yaPagada();
        }

        DB::transaction(function () use ($id) {
            $this->asientos->eliminarDe($id);

            DB::table('rel_facturaproveedorocupacion')->where('fk_facturaproveedor_id', $id)->delete();
            DB::table('facturaproveedor')->where('facturaproveedor_id', $id)->delete();
        });
    }

    /** Guarda el nombre del adjunto ya almacenado. */
    public function guardarAdjunto(int $id, string $nombre): void
    {
        $this->tablas->actualizar('facturaproveedor', 'facturaproveedor_id', $id, ['archivo' => $nombre]);
    }

    /** Cabecera para el formulario de edición, con los JSON ya decodificados. */
    public function paraEditar(int $id): ?array
    {
        $fila = $this->tablas->paraEditar('facturaproveedor', 'facturaproveedor_id', $id);

        if ($fila === null) {
            return null;
        }

        $fila['areaimputacion'] = $this->decodificarImputacion($fila['imputacion'] ?? null);
        $fila['adicionales'] = json_decode((string) ($fila['adicionales'] ?? ''), true) ?: [];

        return $fila;
    }

    /** Datos de la vista de detalle / impresión (factura3ero::imprimir()). */
    public function paraVer(int $id): ?array
    {
        $factura = Facturaproveedor::with(['proveedor', 'plancuenta', 'proyecto', 'usuario'])->find($id);

        if ($factura === null) {
            return null;
        }

        return [
            'factura' => $factura->toArray(),
            'proveedor' => $factura->proveedor?->only(['proveedor_id', 'proveedor_nombre', 'razonsocial', 'cuit', 'direccion']),
            'cuentaContable' => $factura->plancuenta?->plancuenta_nombre,
            'proyecto' => $factura->proyecto?->proyecto_nombre,
            'usuario' => trim(($factura->usuario?->usuario_nombre ?? '').' '.($factura->usuario?->usuario_apellido ?? '')),
            'servicios' => $this->ocupaciones->serviciosDeFactura($id),
            'imputacion' => $this->decodificarImputacion($factura->imputacion),
            'adicionales' => json_decode((string) $factura->adicionales, true) ?: [],
        ];
    }

    /** Catálogos y reglas que necesita el formulario (factura3ero::create()). */
    public function opcionesFormulario(): array
    {
        $calculo = FacturaproveedorCalculo::paraLicenciaActual();
        $pais = Licencia::pais() ?: 'AR';

        return [
            'plancuenta' => Plancuenta::where('cuentagasto', 1)
                ->where('plancuenta_titulo', 0)
                ->orderByRaw('TRIM(plancuenta_nombre)')
                ->pluck('plancuenta_nombre', 'plancuenta_id'),
            'proyectos' => Proyecto::orderByRaw('TRIM(proyecto_nombre)')->pluck('proyecto_nombre', 'proyecto_id'),
            'conceptos' => $this->conceptosAdicionales(),
            'itemsGasto' => $this->itemsGasto(),
            'tiposMovimiento' => $this->tiposMovimiento($pais),
            'tiposDocumento' => (array) config('facturaproveedor.tipos_documento'),
            'tiposFactura' => (array) (config("facturaproveedor.tipos_factura.{$pais}") ?? config('facturaproveedor.tipos_factura.default')),
            'mascaraNumero' => config("facturaproveedor.mascara_numero.{$pais}"),
            'calculo' => $calculo->tasas(),
            'imputacion' => $this->configImputacion(),
            'adjunto' => $this->adjuntoHabilitado(),
            // El comprobante electrónico vs. papel sólo se distingue en Chile
            // (factura3ro.php:129-136).
            'electronicaVisible' => $pais === 'CL',
            'itemgastoObligatorio' => in_array(Licencia::base(), (array) config('facturaproveedor.itemgasto_obligatorio', []), true),
        ];
    }

    /** Autocomplete de proveedores habilitados con CUIT (factura3ero.php:1437). */
    public function buscarProveedores(?string $q, int $limite = 30): array
    {
        $query = Proveedor::query()
            ->whereIn('habilita', ['Y', '1'])
            ->where('cuit', '<>', '')
            ->orderByRaw('TRIM(proveedor_nombre)')
            ->limit($limite);

        if ($q !== null && $q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('proveedor_nombre', 'like', '%'.$q.'%')
                    ->orWhere('razonsocial', 'like', '%'.$q.'%')
                    ->orWhere('cuit', 'like', '%'.$q.'%');
            });
        }

        return $query->get(['proveedor_id', 'proveedor_nombre', 'iata', 'cuit'])
            ->map(fn ($p) => [
                'id' => (int) $p->proveedor_id,
                'label' => trim($p->proveedor_nombre).' - ('.$p->cuit.')',
                'cuenta' => $p->iata,
            ])
            ->all();
    }

    public function existeDuplicado(string $nro, int $proveedorId, string $tipoDocumento): bool
    {
        return Facturaproveedor::query()
            ->where('facturaproveedor_nro', trim($nro))
            ->where('fk_proveedor_id', $proveedorId)
            ->where('facturaproveedor_tipodocumento', trim($tipoDocumento))
            ->exists();
    }

    /** ¿La fecha contable cae en un período ya cerrado? */
    public function periodoCerrado($fechaContable): bool
    {
        if (! $fechaContable) {
            return false;
        }

        $fecha = $fechaContable instanceof Carbon ? $fechaContable->toDateString() : substr((string) $fechaContable, 0, 10);

        if (str_starts_with($fecha, '0000')) {
            return false;
        }

        return DB::table('cierrecaja')->whereDate('cierrecaja_fecha', '>=', $fecha)->exists();
    }

    /** ¿Tiene una orden de pago no anulada? (factura3ero.php:1535-1538) */
    public function tieneOrdenDePagoActiva(int $id): bool
    {
        return DB::table('rel_ordenadminocupacion as roo')
            ->join('rel_facturaproveedorocupacion as rfo', 'roo.fk_ocupacion_id', '=', 'rfo.fk_ocupacion_id')
            ->join('ordenadmin as o', 'o.ordenadmin_id', '=', 'roo.fk_ordenadmin_id')
            ->where('rfo.fk_facturaproveedor_id', $id)
            ->where('o.status', '<>', 'AN')
            ->exists();
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function insertarCabecera(array $datos, MontosFactura $montos, int $usuarioId): int
    {
        $fila = array_merge($montos->columnasFactura(), [
            'facturaproveedor_nro' => $datos['facturaproveedor_nro'],
            'facturaproveedor_tipodocumento' => $datos['facturaproveedor_tipodocumento'],
            'facturaproveedor_tipofactura' => $datos['facturaproveedor_tipofactura'] ?? '',
            'fk_proveedor_id' => (int) $datos['fk_proveedor_id'],
            'fk_proyecto_id' => (int) ($datos['fk_proyecto_id'] ?? 0),
            'fk_plancuenta_id' => (int) ($datos['fk_plancuenta_id'] ?? 0),
            'fk_itemgasto_id' => (int) ($datos['fk_itemgasto_id'] ?? 0),
            'fk_moneda_id' => $datos['fk_moneda_id'],
            'cotizacion' => (float) ($datos['cotizacion'] ?? 1),
            'fecha' => $datos['fecha'],
            'fechacontable' => $datos['fechacontable'],
            'vencimiento' => $datos['vencimiento'] ?? null,
            'fechacarga' => Carbon::today()->toDateString(),
            // El formulario lo llama "observaciones"; la columna es `descripcion`.
            'descripcion' => $datos['descripcion'] ?? '',
            'tipomovimiento' => $datos['tipomovimiento'],
            'electronica' => (string) ($datos['electronica'] ?? ''),
            'archivo' => $datos['archivo'] ?? '',
            'imputacion' => $this->imputacionJson($datos),
            'adicionales' => ! empty($datos['adicionales']) ? json_encode($datos['adicionales']) : '',
            'fk_usuario_id' => $usuarioId,
        ]);

        // Vía TablaLegacyService porque en esta tabla TODAS las columnas son NOT
        // NULL sin default: un insert parcial falla.
        return $this->tablas->insertar('facturaproveedor', $fila);
    }

    /** Normaliza fechas y deja `fechacontable` siempre resuelta. */
    private function normalizar(array $datos): array
    {
        foreach (['fecha', 'fechacontable', 'vencimiento'] as $campo) {
            if (! empty($datos[$campo])) {
                $datos[$campo] = $this->fecha($datos[$campo]);
            }
        }

        if (empty($datos['fechacontable'])) {
            $datos['fechacontable'] = $datos['fecha'];
        }

        if (isset($datos['observaciones']) && ! isset($datos['descripcion'])) {
            $datos['descripcion'] = $datos['observaciones'];
        }

        return $datos;
    }

    /** Acepta ISO (lo que manda el front nuevo) y dd/mm/YYYY (compatibilidad API). */
    private function fecha(string $valor): string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return substr($valor, 0, 10);
    }

    /**
     * La imputación por área debe sumar 100 (100 ó 200 en Chile). El legacy lo
     * validaba sólo en el navegador (scriptfactura3ro.js:318-323), así que
     * cualquier cliente que no fuera el formulario podía guardar cualquier cosa.
     *
     * Pública para poder validarla antes de abrir la transacción.
     */
    public function validarImputacion(array $datos): void
    {
        $areas = $datos['areaimputacion'] ?? null;

        if (! is_array($areas) || $areas === []) {
            return;
        }

        $suma = round(array_sum(array_map(fn ($v) => (float) $v, $areas)), 2);

        if ($suma == 0.0) {
            return;
        }

        $pais = Licencia::pais() ?: 'AR';
        $validas = (array) (config("facturaproveedor.imputacion_suma_valida.{$pais}") ?? [100]);

        if (! in_array($suma, array_map(fn ($v) => (float) $v, $validas), true)) {
            throw FacturaproveedorException::imputacionInvalida($suma, $validas);
        }
    }

    private function imputacionJson(array $datos): string
    {
        if (isset($datos['subareaimputacion']) && is_array($datos['subareaimputacion'])) {
            return json_encode([
                'areaimputacion' => $datos['areaimputacion'] ?? null,
                'subareaimputacion' => $datos['subareaimputacion'],
            ]);
        }

        return json_encode($datos['areaimputacion'] ?? '');
    }

    /** El campo guarda o el mapa de áreas o {areaimputacion, subareaimputacion}. */
    private function decodificarImputacion(?string $json): array
    {
        $decodificado = json_decode((string) $json, true);

        if (! is_array($decodificado)) {
            return [];
        }

        return $decodificado['areaimputacion'] ?? $decodificado;
    }

    /** Reconstruye el array de entrada a partir de una fila ya guardada. */
    private function datosDesdeFila(Facturaproveedor $f): array
    {
        return [
            'facturaproveedor_nro' => $f->facturaproveedor_nro,
            'facturaproveedor_tipodocumento' => $f->facturaproveedor_tipodocumento,
            'fk_proveedor_id' => $f->fk_proveedor_id,
            'fk_plancuenta_id' => $f->fk_plancuenta_id,
            'fk_moneda_id' => $f->fk_moneda_id,
            'cotizacion' => $f->cotizacion,
            'fecha' => substr((string) $f->fecha, 0, 10),
            'fechacontable' => substr((string) $f->fechacontable, 0, 10),
            'tipomovimiento' => $f->tipomovimiento,
            'exento' => $f->montoexento,
            'nocomputable' => $f->montonocomputable,
            'especial' => $f->montoespecial,
            'general' => $f->montogeneral,
            'monto27' => $f->monto27,
            'monto25' => $f->monto25,
            'ivatotal' => $f->ivatotal,
            'ivatur' => $f->ivatur,
            'retencioniva' => $f->retencioniva,
            'retencioniibb' => $f->retencioniibb,
            'percepcioniva' => $f->percepcioniva,
            'percepcioniibb' => $f->percepcioniibb,
            'retencionganancias' => $f->retencionganancias,
            'percepcionganancias' => $f->percepcionganancias,
            'otrosimpuestos' => $f->otrosimpuestos,
        ];
    }

    /** Conceptos configurables por licencia (sysconfig.adicionales_fc3). */
    private function conceptosAdicionales(): array
    {
        $json = SysconfigHelper::get('adicionales_fc3');
        $decodificado = $json ? json_decode($json, true) : null;

        if (! is_array($decodificado)) {
            return [];
        }

        return array_map(
            fn ($clave) => ['clave' => $clave, 'label' => is_string($decodificado[$clave] ?? null) ? $decodificado[$clave] : $clave],
            array_keys($decodificado)
        );
    }

    /**
     * Items de gasto activos. Sólo se usan en las licencias que los exigen, pero
     * el catálogo se manda siempre: son pocas filas.
     */
    private function itemsGasto(): array
    {
        if (! Schema::hasTable('itemgasto')) {
            return [];
        }

        return DB::table('itemgasto')
            ->where('itemgasto_activo', 1)
            ->orderByRaw('TRIM(itemgasto_nombre)')
            ->pluck('itemgasto_nombre', 'itemgasto_id')
            ->all();
    }

    private function tiposMovimiento(string $pais): array
    {
        $cfg = (array) config('facturaproveedor.tipos_movimiento', []);

        $tipos = (array) ($cfg['por_pais'][$pais] ?? $cfg['por_pais']['default'] ?? []);

        if (Licencia::esFamiliaSecontur()) {
            $tipos += (array) ($cfg['extra_familia_secontur'] ?? []);
        }

        return $tipos + (array) ($cfg['comunes'] ?? []);
    }

    /** Áreas de imputación según la licencia (factura3ro.php:321-388). */
    private function configImputacion(): array
    {
        $cfg = (array) config('facturaproveedor.imputacion', []);
        $base = Licencia::base();

        if (isset($cfg[$base])) {
            $def = $cfg[$base];
        } elseif (Licencia::esFamiliaSecontur() && config('facturaproveedor.imputacion_oculta_familia_secontur')) {
            // La familia secontur (salvo witwan_secontur, que tiene su propio
            // bloque) no muestra imputación.
            return ['visible' => false, 'areas' => []];
        } else {
            $def = $cfg['default'] ?? [];
        }

        $areas = match ($def['origen'] ?? 'fijas') {
            'tabla' => DB::table('centrocosto')->orderBy('centrocosto_nombre')->pluck('centrocosto_nombre', 'centrocosto_id')->all(),
            'sistemas' => $this->areasPorSistema($def),
            default => (array) ($def['areas'] ?? []),
        };

        return [
            'visible' => $areas !== [],
            'titulo' => $def['titulo'] ?? 'IMPUTACION (en %)',
            'areas' => $areas,
            'subareas' => in_array($base, (array) config('facturaproveedor.subareas_imputacion', []), true),
        ];
    }

    /**
     * Áreas 1..2 con el nombre del sistema cuando existe, y 3..5 con etiquetas
     * fijas. El legacy oculta con CSS las que no tienen sistema; acá directamente
     * no se envían.
     */
    private function areasPorSistema(array $def): array
    {
        $fijas = (array) ($def['fijas'] ?? []);
        $fallbacks = (array) ($def['fallbacks'] ?? []);

        $sistemas = DB::table('sistema')->pluck('sistema_nombre', 'sistema_id')->all();

        $areas = [];
        foreach ($fallbacks as $id => $fallback) {
            if (isset($fijas[$id])) {
                $areas[$id] = $fijas[$id];

                continue;
            }

            if (isset($sistemas[$id])) {
                $areas[$id] = $sistemas[$id];
            }
        }

        return $areas;
    }

    private function adjuntoHabilitado(): bool
    {
        if (in_array(Licencia::base(), (array) config('facturaproveedor.adjunto_licencias', []), true)) {
            return true;
        }

        return (bool) config('facturaproveedor.adjunto_familia_secontur') && Licencia::esFamiliaSecontur();
    }
}
