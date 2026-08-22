<?php

namespace App\Services\Documentos;

use App\Helpers\SysconfigHelper;
use App\Models\Moneda;
use App\Support\Licencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado de facturas de tercero.
 *
 * Réplica de la query del listado de `factura3ero` (application/controllers/
 * administracion/factura3ero.php:198-274) y de su pie de totales (:717-806).
 *
 * Dos diferencias deliberadas con el legacy:
 *
 *  1. El legacy no pagina: `_limit = 100000000000` trae todas las filas y suma
 *     los totales en PHP sobre el resultado en memoria. Acá se pagina de verdad y
 *     los totales salen de una consulta de agregación aparte sobre el MISMO
 *     conjunto filtrado, así que los números son los mismos sin traerse 77.000
 *     filas.
 *  2. `numero` y `codigo` filtran con LIKE, como el filtro genérico del
 *     Admin_Controller (Form.php:312-327), no por igualdad exacta.
 */
class FacturaproveedorListadoService
{
    /**
     * La moneda básica y la alícuota general salen de la base (tabla `moneda` y
     * `sysconfig`). Se pueden inyectar para compilar la query sin conexión, que
     * es como se verifica el mapeo filtro -> SQL en los tests.
     */
    public function __construct(
        private ?string $monedaBasica = null,
        private ?float $coef2 = null,
        private ?int $decimales = null,
    ) {}

    /** Filtros aceptados desde la query string. */
    public const FILTROS = [
        'proveedor',
        'numero',
        'codigo',
        'proyecto',
        'tipodocumento',
        'tipomovimiento',
        'moneda',
        'fecha_desde',
        'fecha_hasta',
        'fechacarga_desde',
        'fechacarga_hasta',
        'fechacontable_desde',
        'fechacontable_hasta',
    ];

    /**
     * Columnas que se totalizan al pie, agrupadas por tipo de documento.
     * Son las 23 del `_footer` legacy, en el mismo orden.
     */
    public const CAMPOS_TOTALIZABLES = [
        'netogravado',
        'montogeneral',
        'montoespecial',
        'monto27',
        'monto25',
        'montoexento',
        'montonocomputable',
        'i21',
        'idi21',
        'iin21',
        'i105',
        'i27',
        'i25',
        'i2527',
        'retencioniva',
        'percepcioniva',
        'retencioniibb',
        'percepcioniibb',
        'retencionganancias',
        'percepcionganancias',
        'otrosimpuestos',
        'ivatur',
        'montototal',
    ];

    /**
     * ¿Hay algún filtro aplicado?
     *
     * El legacy usa `_filterbefore = true`: sin filtros no ejecuta la query ni
     * muestra la tabla (Admin_Controller.php:2141). Se mantiene, porque sin eso
     * la pantalla abriría barriendo la tabla entera.
     */
    public function hayFiltros(array $filtros): bool
    {
        foreach (self::FILTROS as $f) {
            if (isset($filtros[$f]) && $filtros[$f] !== '' && $filtros[$f] !== null) {
                return true;
            }
        }

        return false;
    }

    /** Página de resultados, ya mapeada al DTO que consume el front. */
    public function listar(array $filtros, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('facturaproveedor.per_page', 50);

        return $this->query($filtros)
            ->orderByDesc('facturaproveedor.fecha')
            ->orderByDesc('facturaproveedor.facturaproveedor_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($fila) => $this->fila($fila));
    }

    /**
     * Página sin mapear al DTO, con los nombres de columna crudos.
     *
     * La API JSON expone esas claves desde antes (montoexento, i21, netogravado…)
     * y hay consumidores atados a ellas, así que el refactor no puede cambiarlas.
     * El front nuevo usa listar(), que sí devuelve el DTO camelCase.
     */
    public function listarCrudo(array $filtros, ?int $perPage = null): LengthAwarePaginator
    {
        return $this->query($filtros)
            ->orderByDesc('facturaproveedor.fecha')
            ->orderByDesc('facturaproveedor.facturaproveedor_id')
            ->paginate($perPage ?? 100)
            ->withQueryString();
    }

    /** Todas las filas del conjunto filtrado, sin paginar (export). */
    public function todos(array $filtros): Collection
    {
        return $this->query($filtros)
            ->orderByDesc('facturaproveedor.fecha')
            ->get()
            ->map(fn ($fila) => $this->fila($fila));
    }

    /**
     * Totales por tipo de documento, más el total general.
     *
     * El GROUP BY por factura tiene que ocurrir ANTES del SUM: los joins a
     * `rel_facturaproveedorocupacion` y `servicio` multiplican filas (una factura
     * con 3 servicios aparece 3 veces), así que sumar sobre el join crudo infla
     * los totales. Por eso la agregación va sobre una subconsulta derivada.
     */
    public function totales(array $filtros): array
    {
        $sub = $this->query($filtros);

        $sumas = collect(self::CAMPOS_TOTALIZABLES)
            ->map(fn ($c) => "ROUND(SUM(f.{$c}), 2) AS {$c}")
            ->implode(', ');

        $porTipo = DB::query()
            ->fromSub($sub, 'f')
            ->groupBy('f.facturaproveedor_tipodocumento')
            ->selectRaw("f.facturaproveedor_tipodocumento AS tipodocumento, COUNT(*) AS cantidad, {$sumas}")
            ->get();

        $general = ['tipodocumento' => 'TOTAL', 'cantidad' => 0];
        foreach (self::CAMPOS_TOTALIZABLES as $campo) {
            $general[$campo] = 0.0;
        }

        $grupos = [];
        foreach ($porTipo as $fila) {
            $grupo = ['tipodocumento' => $fila->tipodocumento, 'cantidad' => (int) $fila->cantidad];
            $general['cantidad'] += (int) $fila->cantidad;

            foreach (self::CAMPOS_TOTALIZABLES as $campo) {
                $valor = (float) $fila->$campo;
                $grupo[$campo] = $valor;
                $general[$campo] += $valor;
            }

            $grupos[] = $grupo;
        }

        foreach (self::CAMPOS_TOTALIZABLES as $campo) {
            $general[$campo] = round($general[$campo], 2);
        }

        return ['porTipo' => $grupos, 'general' => $general];
    }

    /**
     * Query con joins, filtros, columnas derivadas y agrupación por factura.
     * Pública porque la comparten listado, totales y export.
     */
    public function query(array $filtros): Builder
    {
        return $this->baseQuery($filtros)
            ->groupBy('facturaproveedor.facturaproveedor_id')
            ->selectRaw($this->expresiones());
    }

    /**
     * Completa los rangos de fecha que llegan a medias.
     *
     * El front ya deja los dos extremos coherentes y a la vista, pero a esta
     * query también se llega por URL (un link guardado, el export), así que se
     * vuelve a asegurar acá.
     *
     * Un "desde" sin "hasta" se cierra en hoy, igual que el legacy
     * (Form.php:1120 usa format_date(''), que devuelve la fecha actual). Un
     * "hasta" sin "desde" se aplica como cota superior abierta: el legacy en ese
     * caso descartaba el filtro entero y devolvía todo, que es justamente el
     * resultado engañoso que se quiere evitar.
     */
    private function normalizarRangos(array $filtros): array
    {
        foreach (['fecha', 'fechacarga', 'fechacontable'] as $campo) {
            $desde = $filtros[$campo.'_desde'] ?? null;
            $hasta = $filtros[$campo.'_hasta'] ?? null;

            if (! blank($desde) && blank($hasta)) {
                $filtros[$campo.'_hasta'] = Carbon::today()->format('Y-m-d');
            }
        }

        return $filtros;
    }

    /** Joins y WHERE, sin proyección: lo compartido entre listado, totales y export. */
    private function baseQuery(array $filtros): Builder
    {
        $filtros = $this->normalizarRangos($filtros);

        $q = DB::table('facturaproveedor')
            ->join('proveedor', 'proveedor.proveedor_id', '=', 'facturaproveedor.fk_proveedor_id')
            ->leftJoin('usuario', 'usuario.usuario_id', '=', 'facturaproveedor.fk_usuario_id')
            ->leftJoin('plancuenta', 'plancuenta.plancuenta_id', '=', 'facturaproveedor.fk_plancuenta_id')
            ->leftJoin('rel_facturaproveedorocupacion', 'rel_facturaproveedorocupacion.fk_facturaproveedor_id', '=', 'facturaproveedor.facturaproveedor_id')
            ->leftJoin('servicio', 'servicio.servicio_id', '=', 'rel_facturaproveedorocupacion.fk_ocupacion_id')
            ->leftJoin('reserva', 'reserva.reserva_id', '=', 'servicio.fk_reserva_id');

        $v = fn (string $k) => isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null ? $filtros[$k] : null;

        if ($p = $v('proveedor')) {
            $q->where('facturaproveedor.fk_proveedor_id', (int) $p);
        }
        if ($n = $v('numero')) {
            $q->where('facturaproveedor.facturaproveedor_nro', 'like', '%'.$n.'%');
        }
        if ($c = $v('codigo')) {
            $q->where('reserva.codigo', 'like', '%'.$c.'%');
        }
        if ($p = $v('proyecto')) {
            $q->where('facturaproveedor.fk_proyecto_id', (int) $p);
        }
        if ($t = $v('tipodocumento')) {
            $q->where('facturaproveedor.facturaproveedor_tipodocumento', $t);
        }
        if ($t = $v('tipomovimiento')) {
            $q->where('facturaproveedor.tipomovimiento', $t);
        }
        if ($m = $v('moneda')) {
            $q->where('facturaproveedor.fk_moneda_id', $m);
        }

        foreach (['fecha' => 'fecha', 'fechacarga' => 'fechacarga', 'fechacontable' => 'fechacontable'] as $filtro => $columna) {
            if ($d = $v($filtro.'_desde')) {
                $q->whereDate("facturaproveedor.{$columna}", '>=', $this->fecha($d));
            }
            if ($h = $v($filtro.'_hasta')) {
                $q->whereDate("facturaproveedor.{$columna}", '<=', $this->fecha($h));
            }
        }

        return $q;
    }

    /**
     * Columnas derivadas del listado.
     *
     * Se calculan en SQL, no en PHP, porque los totales tienen que sumar
     * exactamente las mismas expresiones: duplicarlas en PHP garantizaría que el
     * pie y las filas se separen con el tiempo.
     */
    private function expresiones(): string
    {
        $moneda = $this->monedaBasicaSql();
        $coef2 = $this->coef2();
        $dec = $this->decimales();

        // Cotización si la moneda no es la básica, y signo negativo para las
        // notas de crédito (factura3ero.php:198-274).
        $ctz = "IF(facturaproveedor.fk_moneda_id != {$moneda}, facturaproveedor.cotizacion, 1)";
        $sig = "IF(facturaproveedor.facturaproveedor_tipodocumento = 'Nota de Credito', -1, 1)";

        $conv = fn (string $expr, int $decimales = 2) => "ROUND(({$expr}) * {$ctz} * {$sig}, {$decimales})";

        $columnas = [
            'facturaproveedor.facturaproveedor_id',
            'facturaproveedor.facturaproveedor_tipodocumento',
            'facturaproveedor.facturaproveedor_tipofactura',
            'facturaproveedor.facturaproveedor_nro',
            'facturaproveedor.fk_proveedor_id',
            'facturaproveedor.fk_plancuenta_id',
            'facturaproveedor.fk_proyecto_id',
            'facturaproveedor.fk_moneda_id',
            'facturaproveedor.fecha',
            'facturaproveedor.fechacontable',
            'facturaproveedor.fechacarga',
            'facturaproveedor.vencimiento',
            'facturaproveedor.cotizacion',
            'facturaproveedor.descripcion',
            'facturaproveedor.tipomovimiento',
            'reserva.codigo',
            'proveedor.cuit',
            'plancuenta.plancuenta_nombre',
            "CONCAT(IF(facturaproveedor.facturaproveedor_tipodocumento='Factura','FC',IF(facturaproveedor.facturaproveedor_tipodocumento='Nota de Credito','NC','')),' ',facturaproveedor.facturaproveedor_nro) AS numero",
            'SUBSTRING(proveedor.razonsocial, 1, 60) AS proveedor_nombre',
            "CONCAT(usuario.usuario_nombre,' ',usuario.usuario_apellido) AS usuario_nombre",

            // Bases imponibles.
            $conv('facturaproveedor.montogeneral + facturaproveedor.montoespecial + facturaproveedor.monto27 + facturaproveedor.monto25').' AS netogravado',
            $conv('facturaproveedor.montoexento').' AS montoexento',
            $conv('facturaproveedor.montonocomputable').' AS montonocomputable',
            $conv('facturaproveedor.montoespecial').' AS montoespecial',
            $conv('facturaproveedor.montogeneral').' AS montogeneral',
            $conv('facturaproveedor.monto27').' AS monto27',
            $conv('facturaproveedor.monto25').' AS monto25',

            // IVA discriminado por alícuota.
            $conv("facturaproveedor.montogeneral * {$coef2}", $dec).' AS i21',
            $conv('facturaproveedor.montoespecial * 0.105').' AS i105',
            $conv('facturaproveedor.monto27 * 0.27').' AS i27',
            $conv('facturaproveedor.monto25 * 0.025').' AS i25',
            '('.$conv('facturaproveedor.monto25 * 0.025').' + '.$conv('facturaproveedor.monto27 * 0.27').') AS i2527',

            // El subdiario separa el IVA general según sea gasto o no
            // (factura3ero.php:98-100).
            "IF(facturaproveedor.tipomovimiento = 'Gasto', ".$conv("facturaproveedor.montogeneral * {$coef2}", $dec).', 0) AS iin21',
            "IF(facturaproveedor.tipomovimiento != 'Gasto', ".$conv("facturaproveedor.montogeneral * {$coef2}", $dec).', 0) AS idi21',

            // Impuestos, retenciones y percepciones.
            $conv('facturaproveedor.retencioniva').' AS retencioniva',
            $conv('facturaproveedor.percepcioniva').' AS percepcioniva',
            $conv('facturaproveedor.retencioniibb').' AS retencioniibb',
            $conv('facturaproveedor.percepcioniibb').' AS percepcioniibb',
            $conv('facturaproveedor.retencionganancias').' AS retencionganancias',
            $conv('facturaproveedor.percepcionganancias').' AS percepcionganancias',
            $conv('facturaproveedor.otrosimpuestos').' AS otrosimpuestos',
            $conv('facturaproveedor.ivatur').' AS ivatur',
            $conv('facturaproveedor.montototal').' AS montototal',
        ];

        return implode(",\n", $columnas);
    }

    /** DTO camelCase que viaja a Inertia. */
    private function fila(object $f): array
    {
        return [
            'id' => (int) $f->facturaproveedor_id,
            'tipoDocumento' => $f->facturaproveedor_tipodocumento,
            'tipoFactura' => $f->facturaproveedor_tipofactura,
            'numero' => trim((string) $f->numero),
            'numeroCrudo' => $f->facturaproveedor_nro,
            'proveedorId' => (int) $f->fk_proveedor_id,
            'proveedorNombre' => $f->proveedor_nombre,
            'cuit' => $f->cuit,
            'fecha' => $this->iso($f->fecha),
            'fechaContable' => $this->iso($f->fechacontable),
            'fechaCarga' => $this->iso($f->fechacarga),
            'vencimiento' => $this->iso($f->vencimiento),
            'moneda' => $f->fk_moneda_id,
            'cotizacion' => (float) $f->cotizacion,
            'tipoMovimiento' => $f->tipomovimiento,
            'proyectoId' => (int) $f->fk_proyecto_id,
            'cuentaContable' => $f->plancuenta_nombre,
            'codigoReserva' => $f->codigo,
            'usuario' => trim((string) $f->usuario_nombre),
            'descripcion' => $f->descripcion,
            'montos' => [
                'exento' => (float) $f->montoexento,
                'noComputable' => (float) $f->montonocomputable,
                'especial' => (float) $f->montoespecial,
                'general' => (float) $f->montogeneral,
                'monto27' => (float) $f->monto27,
                'monto25' => (float) $f->monto25,
                'netoGravado' => (float) $f->netogravado,
            ],
            'ivas' => [
                'i21' => (float) $f->i21,
                'i105' => (float) $f->i105,
                'i27' => (float) $f->i27,
                'i25' => (float) $f->i25,
                'i2527' => (float) $f->i2527,
                'iin21' => (float) $f->iin21,
                'idi21' => (float) $f->idi21,
                'ivaTur' => (float) $f->ivatur,
            ],
            'retper' => [
                'retencionIva' => (float) $f->retencioniva,
                'percepcionIva' => (float) $f->percepcioniva,
                'retencionIibb' => (float) $f->retencioniibb,
                'percepcionIibb' => (float) $f->percepcioniibb,
                'retencionGanancias' => (float) $f->retencionganancias,
                'percepcionGanancias' => (float) $f->percepcionganancias,
                'otrosImpuestos' => (float) $f->otrosimpuestos,
            ],
            'total' => (float) $f->montototal,
        ];
    }

    /** Las fechas legacy admiten '0000-00-00', que Carbon no puede parsear. */
    private function iso(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '' || str_starts_with($fecha, '0000')) {
            return null;
        }

        return substr($fecha, 0, 10);
    }

    private function fecha(string $valor): string
    {
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $valor)) {
            return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
        }

        return substr($valor, 0, 10);
    }

    /**
     * Moneda básica como literal SQL. Se interpola en vez de ir por binding
     * porque la expresión se repite 25 veces y arrastrar 25 parámetros
     * posicionales (como hacía el port original) es una fuente de bugs silenciosa.
     * El valor es un `moneda_id` de 3 caracteres; igual se sanea.
     */
    private function monedaBasicaSql(): string
    {
        $moneda = $this->monedaBasica
            ?? (Moneda::query()->where('moneda_basica', 'Y')->value('moneda_id') ?: 'ARS');

        return "'".preg_replace('/[^A-Za-z0-9]/', '', (string) $moneda)."'";
    }

    private function coef2(): float
    {
        if ($this->coef2 !== null) {
            return $this->coef2;
        }

        $tasa = SysconfigHelper::get('tasageneral');

        if ($tasa === null || $tasa === '') {
            $pais = Licencia::pais() ?: 'AR';

            return (float) (config("facturaproveedor.alicuota_general.{$pais}") ?? 0.21);
        }

        $tasa = (float) $tasa;

        return $tasa < 1 ? $tasa : $tasa / 100;
    }

    private function decimales(): int
    {
        if ($this->decimales !== null) {
            return $this->decimales;
        }

        $pais = Licencia::pais() ?: 'AR';

        return (int) (config("facturaproveedor.decimales_iva.{$pais}") ?? 2);
    }
}
