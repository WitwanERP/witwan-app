<?php

namespace App\Services\Contable;

use App\Exceptions\Contable\AsientoException;
use App\Services\AuditoriaService;
use App\Services\CotizacionService;
use App\Support\Contable\PeriodoContable;
use App\Support\Contable\TipoAsiento;
use Illuminate\Support\Facades\DB;

/**
 * Alta, consulta, edición y anulación de los asientos de administración.
 *
 * Unifica `administracion/asientocontable.php`, `asientocta.php` y `fondos.php`
 * del CI, que son el mismo módulo tres veces. Las diferencias reales entre los
 * tres están declaradas en config/asientos.php y resueltas por TipoAsiento; el
 * armado de las filas de `movimiento` vive en PlanAsiento, que no toca la base.
 *
 * Nota sobre transacciones: `ordenadmin` y `movimiento` son MyISAM en el esquema
 * legacy, así que un `DB::transaction()` no aporta atomicidad real. El orden de
 * escritura está elegido para que un corte a mitad deje datos recuperables
 * (primero la cabecera, después los movimientos) y el alta valida TODO antes de
 * escribir la primera fila, que es la única defensa que se puede dar acá.
 */
class AsientoService
{
    public function __construct(
        private PlanAsiento $plan,
        private PeriodoContable $periodo,
        private CotizacionService $cotizaciones,
        private AuditoriaService $auditoria,
    ) {}

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** Cabecera + movimientos de un asiento, o null si no existe / no es de ese tipo. */
    public function paraVer(TipoAsiento $tipo, int $id): ?array
    {
        $orden = $this->cabecera($tipo, $id);

        if ($orden === null) {
            return null;
        }

        $movimientos = $this->movimientos($id);

        $debe = round($movimientos->where('deha', 'D')->sum('monto'), 2);
        $haber = round($movimientos->where('deha', 'H')->sum('monto'), 2);

        return [
            'asiento' => [
                'id' => (int) $orden->ordenadmin_id,
                'numero' => (string) $orden->nropago,
                'fecha' => self::soloFecha($orden->fecha),
                'moneda' => (string) $orden->fk_moneda_id,
                'cotizacion' => (float) $orden->cotizacion,
                'monto' => (float) $orden->monto,
                'status' => (string) $orden->status,
                'estado' => (string) (config('asientos.estados')[$orden->status] ?? $orden->status),
                'anulado' => $orden->status === 'AN',
                'observaciones' => (string) $orden->observaciones,
                'usuario' => trim((string) $orden->usuario_nombre),
                'proyecto' => $orden->proyecto_nombre !== null ? trim((string) $orden->proyecto_nombre) : null,
                'fk_proyecto_id' => (int) $orden->fk_proyecto_id,
            ],
            'movimientos' => $movimientos->values()->all(),
            'totales' => [
                'debe' => $debe,
                'haber' => $haber,
                // Un asiento anterior a este port puede estar descuadrado: el
                // legacy no lo validaba del lado del servidor. Se muestra en vez
                // de esconderlo.
                'balancea' => abs($debe - $haber) < 0.01,
            ],
            // El período puede haberse cerrado después de grabar el asiento: en
            // ese caso ya no se puede ni editar ni anular, y conviene decirlo
            // antes de que el usuario apriete el botón.
            'periodoAbierto' => $orden->fecha ? $this->periodo->estaAbierto(self::soloFecha($orden->fecha) ?? '') : false,
        ];
    }

    /** Datos para el formulario de edición (mismo payload que paraVer). */
    public function paraEditar(TipoAsiento $tipo, int $id): ?array
    {
        return $this->paraVer($tipo, $id);
    }

    private function cabecera(TipoAsiento $tipo, int $id): ?object
    {
        return DB::table('ordenadmin')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'ordenadmin.fk_usuario_id')
            ->leftJoin('proyecto', 'proyecto.proyecto_id', '=', 'ordenadmin.fk_proyecto_id')
            ->where('ordenadmin.ordenadmin_id', $id)
            ->where('ordenadmin.tipo', $tipo->codigo())
            ->select([
                'ordenadmin.*',
                'proyecto.proyecto_nombre',
                DB::raw("TRIM(CONCAT(COALESCE(usuario.usuario_nombre,''),' ',COALESCE(usuario.usuario_apellido,''))) AS usuario_nombre"),
            ])
            ->first();
    }

    /**
     * Movimientos del asiento con la cuenta, el cliente, el proveedor y el file
     * resueltos.
     *
     * El legacy joinea `plancuenta ON (plancuenta_id = fk_plancuenta_id OR
     * plancuenta_id = cuenta_debito)` (orden_model.php:105-106) porque las líneas
     * de egreso de fondos dejan `fk_plancuenta_id` en 0. Acá se hace lo mismo
     * eligiendo la columna con un IF, y con LEFT JOIN: con el INNER del legacy,
     * un movimiento cuya cuenta se borró del plan desaparecía del detalle y el
     * asiento se veía incompleto sin ninguna señal.
     */
    private function movimientos(int $ordenId): \Illuminate\Support\Collection
    {
        return DB::table('movimiento')
            ->leftJoin('plancuenta', 'plancuenta.plancuenta_id', '=', DB::raw('IF(movimiento.fk_plancuenta_id != 0, movimiento.fk_plancuenta_id, movimiento.cuenta_debito)'))
            ->leftJoin('cliente', 'cliente.cliente_id', '=', 'movimiento.fk_cliente_id')
            ->leftJoin('proveedor', 'proveedor.proveedor_id', '=', 'movimiento.fk_proveedor_id')
            ->leftJoin('reserva', 'reserva.reserva_id', '=', 'movimiento.fk_file_id')
            ->where('movimiento.fk_ordenadmin_id', $ordenId)
            ->orderBy('movimiento.movimiento_id')
            ->select([
                'movimiento.movimiento_id',
                'movimiento.fk_plancuenta_id',
                'movimiento.cuenta_debito',
                'movimiento.cuenta_credito',
                'movimiento.deha',
                'movimiento.tipo',
                'movimiento.monto',
                'movimiento.descripcion',
                'movimiento.banco',
                'movimiento.operacion',
                'movimiento.fecha',
                'movimiento.fecha_acreditacion',
                'movimiento.fk_cliente_id',
                'movimiento.fk_proveedor_id',
                'movimiento.fk_file_id',
                'movimiento.statusmovimiento',
                'movimiento.statusdocumento',
                'movimiento.utilizado',
                'plancuenta.plancuenta_nombre',
                'plancuenta.plancuenta_codigo',
                'cliente.cliente_nombre',
                'proveedor.proveedor_nombre',
                DB::raw("CONCAT(COALESCE(reserva.tipocodigo,''),'-',COALESCE(reserva.codigo,'')) AS file_codigo"),
            ])
            ->get()
            ->map(fn ($m) => [
                'id' => (int) $m->movimiento_id,
                'cuentaId' => (int) ($m->fk_plancuenta_id ?: $m->cuenta_debito),
                'cuenta' => trim((string) $m->plancuenta_nombre),
                'cuentaCodigo' => trim((string) $m->plancuenta_codigo),
                'deha' => (string) $m->deha,
                'tipo' => (string) $m->tipo,
                'debe' => $m->deha === 'D' ? (float) $m->monto : null,
                'haber' => $m->deha === 'H' ? (float) $m->monto : null,
                'monto' => (float) $m->monto,
                'descripcion' => (string) $m->descripcion,
                'banco' => (string) $m->banco,
                'operacion' => (string) $m->operacion,
                'fecha' => self::soloFecha($m->fecha),
                'fechaAcreditacion' => self::soloFecha($m->fecha_acreditacion),
                'clienteId' => (int) $m->fk_cliente_id,
                'cliente' => trim((string) $m->cliente_nombre),
                'proveedorId' => (int) $m->fk_proveedor_id,
                'proveedor' => trim((string) $m->proveedor_nombre),
                'fileId' => (int) $m->fk_file_id,
                'file' => trim((string) $m->file_codigo, '-'),
                'fueraDeArqueo' => $m->statusmovimiento === 'AR',
                'vigente' => (int) $m->statusdocumento === 1,
                'utilizado' => (int) $m->utilizado === 1,
            ]);
    }

    // ------------------------------------------------------------------
    // Alta
    // ------------------------------------------------------------------

    /**
     * Graba el asiento y devuelve el id de `ordenadmin`.
     *
     * @throws AsientoException si el período está cerrado, si no balancea o si
     *                          alguna línea está mal cargada. Todo se valida ANTES de escribir.
     */
    public function crear(TipoAsiento $tipo, array $datos, int $usuarioId): int
    {
        $datos['fecha'] = PeriodoContable::normalizar((string) $datos['fecha']);
        $datos['fk_usuario_id'] = $usuarioId;
        $datos['cotizacion'] = $this->cotizacionDe($tipo, $datos);

        $this->periodo->exigirAbierto($datos['fecha']);

        $plan = $this->plan->construir($tipo, $datos);

        $ordenId = $this->insertarCabecera($tipo, $datos, $plan['monto'], $usuarioId);

        foreach ($plan['asientos'] as $asiento) {
            $asientoId = $this->insertarAsientoContable($datos['fecha'], $asiento['movimientos']);

            foreach ($asiento['movimientos'] as $mov) {
                DB::table('movimiento')->insert($this->filaMovimiento($mov, $asientoId, $ordenId));
            }
        }

        $this->auditoria->registrar('ordenadmin', $ordenId, 'ALTA_'.strtoupper(str_replace('-', '_', $tipo->slug)), '', [
            'tipo' => $tipo->codigo(),
            'fecha' => $datos['fecha'],
            'moneda' => $datos['fk_moneda_id'],
            'monto' => $plan['monto'],
            'lineas' => count($plan['asientos']) === 1
                ? count($plan['asientos'][0]['movimientos'])
                : count($plan['asientos']),
        ], $usuarioId);

        return $ordenId;
    }

    /**
     * Cotización con la que se valúan los movimientos.
     *
     * En el legacy no es la misma en los tres módulos:
     *
     *  - contable y cuenta corriente toman el campo `cotizacion` del formulario,
     *    que viene hardcodeado en 1 (formasientocontable.php:60). Se respeta:
     *    cambiarlo revaluaría en silencio todo asiento en moneda extranjera. El
     *    front muestra la cotización sugerida al lado del campo para que la
     *    decisión sea del usuario y esté a la vista.
     *  - fondos usa `cotizarmoneda(hoy, moneda)` (fondos.php:229), es decir la
     *    cotización de relación del día, no la de la fecha del asiento.
     */
    private function cotizacionDe(TipoAsiento $tipo, array $datos): float
    {
        if ($tipo->esDebeHaber()) {
            return (float) ($datos['cotizacion'] ?? 1);
        }

        return $this->cotizaciones->aLaVenta((string) $datos['fk_moneda_id']);
    }

    private function insertarCabecera(TipoAsiento $tipo, array $datos, float $monto, int $usuarioId): int
    {
        return (int) DB::table('ordenadmin')->insertGetId([
            'tipo' => $tipo->codigo(),
            'fecha' => $datos['fecha'],
            // Hardcodeado en el legacy en los tres módulos: es el proveedor
            // "interno" con el que el CI marca los asientos propios.
            'fk_proveedor_id' => 1,
            'fk_proyecto_id' => $tipo->usaProyecto() ? (int) ($datos['fk_proyecto_id'] ?? 0) : 0,
            'nropago' => (string) $this->proximoNumero($tipo),
            'fk_moneda_id' => (string) $datos['fk_moneda_id'],
            // El legacy deja `ordenadmin.cotizacion` en 0 y sólo la guarda en
            // cada movimiento; se completa acá para que la cabecera diga con qué
            // se valuó, que es lo que el detalle necesita mostrar.
            'cotizacion' => (float) $datos['cotizacion'],
            'monto' => $monto,
            'fk_usuario_id' => $usuarioId,
            'observaciones' => (string) ($datos['observaciones'] ?? ''),
        ], 'ordenadmin_id');
    }

    /**
     * Próximo `nropago` para el tipo.
     *
     * Réplica de `MAX(CAST(nropago AS UNSIGNED)) + 1` (asientocontable.php:145).
     * Es la misma condición de carrera que en el CI: dos altas simultáneas del
     * mismo tipo pueden sacar el mismo número. No se puede resolver con un
     * `lockForUpdate` porque `ordenadmin` es MyISAM (sin transacciones) y la
     * columna es un varchar sin unique. Queda igual que en producción hoy; el
     * arreglo de fondo es un índice único o una secuencia, y es un cambio de
     * esquema que excede este port.
     */
    private function proximoNumero(TipoAsiento $tipo): int
    {
        $max = DB::table('ordenadmin')
            ->where('tipo', $tipo->codigo())
            ->selectRaw('MAX(CAST(nropago AS UNSIGNED)) AS nro')
            ->value('nro');

        return (int) $max + 1;
    }

    /**
     * Cabecera del asiento contable.
     *
     * El legacy hace `INSERT INTO asientocontable SET asientocontable_id=NULL`, o
     * sea que deja fecha en '0000-00-00' y debe/haber en 0. Acá se completan: no
     * cambia ningún resultado (nadie leía esas columnas para estos asientos) y
     * hace que la tabla sirva para cuadrar.
     */
    private function insertarAsientoContable(string $fecha, array $movimientos): int
    {
        $suma = fn (string $deha) => round(array_sum(array_map(
            fn ($m) => $m['deha'] === $deha ? (float) $m['monto'] : 0.0,
            $movimientos,
        )), 2);

        return (int) DB::table('asientocontable')->insertGetId([
            'asientocontable_fecha' => $fecha,
            'debe' => $suma('D'),
            'haber' => $suma('H'),
        ], 'asientocontable_id');
    }

    /**
     * Completa la fila de `movimiento` con todas las columnas NOT NULL.
     *
     * El legacy se apoya en los defaults implícitos de MySQL en modo no estricto
     * (por eso la conexión del tenant corre con `strict=false`). Se explicitan
     * para que el alta no dependa del sql_mode del servidor.
     */
    private function filaMovimiento(array $mov, int $asientoId, int $ordenId): array
    {
        return array_merge([
            'statusmovimiento' => 'OK',
            'statusdocumento' => 1,
            'afecta_cobranza' => 1,
            'montofinal' => 0,
            'banco' => '',
            'nrodocumento' => '',
            'operacion' => '',
            'descripcion' => '',
            'fk_cliente_id' => 0,
            'fk_proveedor_id' => 0,
            'fk_file_id' => 0,
            'fk_factura_id' => 0,
            'fk_notacredito_id' => 0,
            'fk_notadebito_id' => 0,
            'fk_facturaproveedor_id' => 0,
            'fk_recibo_id' => 0,
            'fk_movimiento_id' => 0,
            'fk_itemgasto_id' => 0,
            'utilizado' => 0,
            'porcentajeadministracion' => 0,
            'porcentajereceptivo' => 0,
            'porcentajemayorista' => 0,
            'porcentajeminorista' => 0,
            'porcentajeconsolidador' => 0,
            'filtro_cliente' => 0,
            'filtro_proveedor' => 0,
            'filtro_documento' => '',
            'filtro_file' => 0,
            'filtro_servicio' => 0,
            'auxiliar' => 0,
        ], $mov, [
            'fk_asientocontable_id' => $asientoId,
            'fk_ordenadmin_id' => $ordenId,
        ]);
    }

    // ------------------------------------------------------------------
    // Edición
    // ------------------------------------------------------------------

    /**
     * Edición acotada: la cabecera y los datos descriptivos de cada movimiento.
     *
     * NO se pueden tocar importes ni cuentas, igual que en el legacy
     * (asientocta::guardaredit y fondos::update sólo actualizan banco,
     * descripción, operación, fechas e imputaciones). Cambiar un importe después
     * de contabilizado descuadraría el asiento sin dejar rastro; para eso está
     * anular y volver a cargar.
     *
     * Diferencia con el legacy: el asiento contable (tipo 'A') no tenía edición
     * propia — su formulario posteaba a `contables/guardar`, que es OTRO módulo
     * con otra grilla y hacía un REPLACE completo. Acá los tres tipos comparten
     * esta edición acotada, que es la que tiene sentido para los tres.
     *
     * @param  array  $datos  cabecera + 'movimientos' => [id => campos]
     */
    public function actualizar(TipoAsiento $tipo, int $id, array $datos, int $usuarioId): void
    {
        $orden = $this->cabecera($tipo, $id);

        if ($orden === null) {
            throw AsientoException::noEncontrado($id);
        }

        $fecha = PeriodoContable::normalizar((string) ($datos['fecha'] ?? $orden->fecha));

        // Se controla el período contra las dos fechas: la que tenía y la nueva.
        // Mover un asiento hacia (o desde) un período cerrado es tan inválido
        // como grabarlo ahí, y el legacy sólo miraba la nueva.
        $this->periodo->exigirAbierto($fecha);
        $this->periodo->exigirAbierto(self::soloFecha($orden->fecha) ?? $fecha);

        $cabecera = ['fecha' => $fecha, 'observaciones' => (string) ($datos['observaciones'] ?? '')];

        if ($tipo->usaProyecto()) {
            $cabecera['fk_proyecto_id'] = (int) ($datos['fk_proyecto_id'] ?? 0);
        }

        DB::table('ordenadmin')->where('ordenadmin_id', $id)->update($cabecera);

        // Los ids se toman de los movimientos del asiento, no del payload: así
        // un id ajeno en el POST no puede editar el movimiento de otro asiento.
        $propios = DB::table('movimiento')->where('fk_ordenadmin_id', $id)->pluck('movimiento_id')->all();

        foreach ((array) ($datos['movimientos'] ?? []) as $mov) {
            $movId = (int) ($mov['id'] ?? 0);

            if (! in_array($movId, $propios, true)) {
                continue;
            }

            $campos = [
                'descripcion' => (string) ($mov['descripcion'] ?? ''),
                'banco' => (string) ($mov['banco'] ?? ''),
                'operacion' => (string) ($mov['operacion'] ?? ''),
                'fecha' => $fecha,
                'fecha_acreditacion' => blank($mov['fechaAcreditacion'] ?? null)
                    ? $fecha
                    : PeriodoContable::normalizar((string) $mov['fechaAcreditacion']),
            ];

            if ($tipo->imputa('cliente')) {
                $campos['fk_cliente_id'] = (int) ($mov['clienteId'] ?? 0);
            }
            if ($tipo->imputa('proveedor')) {
                $campos['fk_proveedor_id'] = (int) ($mov['proveedorId'] ?? 0);
            }
            if ($tipo->imputa('file')) {
                $campos['fk_file_id'] = (int) ($mov['fileId'] ?? 0);
            }

            DB::table('movimiento')->where('movimiento_id', $movId)->update($campos);
        }

        $this->auditoria->registrar('ordenadmin', $id, 'EDITAR', [
            'fecha' => self::soloFecha($orden->fecha),
            'observaciones' => (string) $orden->observaciones,
        ], $cabecera, $usuarioId);
    }

    // ------------------------------------------------------------------
    // Anulación
    // ------------------------------------------------------------------

    /**
     * Anula el asiento.
     *
     * Réplica de los tres `anular()` del CI, con sus diferencias:
     * el contable sólo marca, cuenta corriente además libera los movimientos que
     * había consumido, y fondos libera y BORRA los movimientos.
     *
     * @throws AsientoException
     */
    public function anular(TipoAsiento $tipo, int $id, int $usuarioId): void
    {
        $orden = $this->cabecera($tipo, $id);

        if ($orden === null) {
            throw AsientoException::noEncontrado($id);
        }
        if ($orden->status === 'AN') {
            // El legacy no lo chequeaba: reanular volvía a correr todo el
            // proceso, y en fondos borraba movimientos por segunda vez.
            throw AsientoException::yaAnulado();
        }

        $this->periodo->exigirAbierto(self::soloFecha($orden->fecha) ?? '');

        if ($tipo->controlaConciliacion() && $this->tieneMovimientoConciliado($id)) {
            throw AsientoException::conciliado();
        }

        if ($tipo->liberaUtilizadoAlAnular()) {
            $this->liberarMovimientosConsumidos($id);
        }

        DB::table('ordenadmin')->where('ordenadmin_id', $id)->update(['status' => 'AN']);

        if ($tipo->borraMovimientosAlAnular()) {
            // fondos.php:279 los borra. Antes de perderlos se vuelcan a
            // auditoría, que es lo que el legacy no hacía: hoy, anulado un
            // movimiento de fondos, no queda rastro de qué decía.
            $borrados = DB::table('movimiento')->where('fk_ordenadmin_id', $id)->get();

            $this->auditoria->registrar('movimiento', $id, 'BORRAR_POR_ANULACION',
                $borrados->map(fn ($m) => (array) $m)->all(), '', $usuarioId);

            DB::table('movimiento')->where('fk_ordenadmin_id', $id)->delete();
        } else {
            DB::table('movimiento')->where('fk_ordenadmin_id', $id)->update(['statusdocumento' => 0]);
        }

        $this->auditoria->registrar('ordenadmin', $id, 'ANULAR', ['status' => (string) $orden->status], ['status' => 'AN'], $usuarioId);
    }

    /** asientocontable.php:239-243 — algún movimiento del asiento figura en `conciliacion`. */
    private function tieneMovimientoConciliado(int $ordenId): bool
    {
        return DB::table('movimiento')
            ->where('fk_ordenadmin_id', $ordenId)
            ->whereIn('movimiento_id', fn ($q) => $q->select('fk_movimiento_id')->from('conciliacion')->distinct())
            ->exists();
    }

    /**
     * Devuelve a "no utilizado" los movimientos que este asiento había consumido
     * (asientocta.php:246-249, fondos.php:274-277).
     */
    private function liberarMovimientosConsumidos(int $ordenId): void
    {
        $consumidos = DB::table('movimiento')
            ->where('fk_ordenadmin_id', $ordenId)
            ->where('fk_movimiento_id', '!=', 0)
            ->pluck('fk_movimiento_id')
            ->all();

        if ($consumidos !== []) {
            DB::table('movimiento')->whereIn('movimiento_id', $consumidos)->update(['utilizado' => 0]);
        }
    }

    // ------------------------------------------------------------------
    // Opciones y buscadores del formulario
    // ------------------------------------------------------------------

    /**
     * Cuentas imputables del plan.
     *
     * Mismo filtro que los tres controllers: hojas (`plancuenta_titulo = 0`) con
     * padre y marcadas como generales (`plancuenta_g = 1`). Ordenadas por código
     * y no por nombre —como hace `asiento.php:14`— porque es el orden con el que
     * el contador lee el plan.
     */
    public function cuentas(?string $busqueda = null, int $limite = 30): array
    {
        $q = DB::table('plancuenta')
            ->where('plancuenta_titulo', 0)
            ->where('fk_plancuenta_id', '!=', 0)
            ->where('plancuenta_g', 1);

        if (! blank($busqueda)) {
            $q->where(function ($w) use ($busqueda) {
                $w->where('plancuenta_nombre', 'like', '%'.$busqueda.'%')
                    ->orWhere('plancuenta_codigo', 'like', $busqueda.'%');
            });
        }

        return $q->orderBy('plancuenta_codigo')
            ->limit($limite)
            ->get(['plancuenta_id', 'plancuenta_codigo', 'plancuenta_nombre'])
            ->map(fn ($c) => [
                'id' => (int) $c->plancuenta_id,
                'codigo' => trim((string) $c->plancuenta_codigo),
                'nombre' => trim((string) $c->plancuenta_nombre),
                'label' => trim(trim((string) $c->plancuenta_codigo).' — '.trim((string) $c->plancuenta_nombre), ' —'),
            ])
            ->all();
    }

    /** Autocomplete de clientes para la grilla de cuenta corriente. */
    public function buscarClientes(?string $busqueda, int $limite = 15): array
    {
        if (blank($busqueda)) {
            return [];
        }

        return DB::table('cliente')
            ->where('cliente_nombre', 'like', '%'.$busqueda.'%')
            ->orderBy('cliente_nombre')
            ->limit($limite)
            ->get(['cliente_id', 'cliente_nombre'])
            ->map(fn ($c) => ['id' => (int) $c->cliente_id, 'label' => trim((string) $c->cliente_nombre)])
            ->all();
    }

    /** Autocomplete de proveedores. */
    public function buscarProveedores(?string $busqueda, int $limite = 15): array
    {
        if (blank($busqueda)) {
            return [];
        }

        return DB::table('proveedor')
            ->where(function ($w) use ($busqueda) {
                $w->where('proveedor_nombre', 'like', '%'.$busqueda.'%')
                    ->orWhere('razonsocial', 'like', '%'.$busqueda.'%');
            })
            ->orderBy('proveedor_nombre')
            ->limit($limite)
            ->get(['proveedor_id', 'proveedor_nombre'])
            ->map(fn ($p) => ['id' => (int) $p->proveedor_id, 'label' => trim((string) $p->proveedor_nombre)])
            ->all();
    }

    /** Autocomplete de files (reservas) por código. */
    public function buscarFiles(?string $busqueda, int $limite = 15): array
    {
        if (blank($busqueda)) {
            return [];
        }

        return DB::table('reserva')
            ->where('codigo', 'like', $busqueda.'%')
            ->orderByDesc('reserva_id')
            ->limit($limite)
            ->get(['reserva_id', 'tipocodigo', 'codigo', 'titular_apellido', 'titular_nombre'])
            ->map(fn ($r) => [
                'id' => (int) $r->reserva_id,
                'label' => trim($r->tipocodigo.'-'.$r->codigo.' '.trim($r->titular_apellido.', '.$r->titular_nombre, ' ,')),
            ])
            ->all();
    }

    private static function soloFecha(mixed $valor): ?string
    {
        $texto = (string) $valor;

        return $texto === '' || str_starts_with($texto, '0000') ? null : substr($texto, 0, 10);
    }
}
