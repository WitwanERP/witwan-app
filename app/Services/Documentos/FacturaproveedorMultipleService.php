<?php

namespace App\Services\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Exceptions\Documentos\FacturaproveedorExceptionEnFila;
use Illuminate\Support\Facades\DB;

/**
 * Carga múltiple de facturas de tercero.
 *
 * Port de `Factura3erom` (application/controllers/administracion/factura3erom.php),
 * el controller hermano de 826 líneas que carga N documentos con una cabecera
 * compartida (proveedor, tipo de gasto, cuenta contable, moneda, cotización,
 * proyecto, imputación y observaciones) y una fila por documento.
 *
 * No duplica la lógica de alta: arma los datos de cada fila y delega en
 * FacturaproveedorService::crear(), que ya resuelve cálculo, servicio contable,
 * imputación de servicios, split y asiento.
 *
 * Diferencia con el legacy: todas las filas van en una sola transacción. En el CI
 * cada iteración insertaba por su cuenta, así que un error en la fila 7 dejaba
 * seis facturas cargadas y ninguna forma de saber cuáles.
 */
class FacturaproveedorMultipleService
{
    /** Campos de la cabecera que se replican en cada documento. */
    private const CAMPOS_CABECERA = [
        'fk_proveedor_id',
        'tipomovimiento',
        'fk_plancuenta_id',
        'fk_moneda_id',
        'cotizacion',
        'fk_proyecto_id',
        'fk_itemgasto_id',
        'areaimputacion',
        'subareaimputacion',
        'descripcion',
        'observaciones',
    ];

    public function __construct(private FacturaproveedorService $facturas) {}

    /**
     * Da de alta todos los documentos. Devuelve los ids creados, en orden.
     *
     * @param  array  $cabecera  Datos compartidos.
     * @param  list<array>  $documentos  Una entrada por factura.
     * @return list<int>
     *
     * @throws FacturaproveedorException con el número de fila que falló.
     */
    public function crear(array $cabecera, array $documentos, int $usuarioId): array
    {
        if ($documentos === []) {
            throw FacturaproveedorException::totalCero();
        }

        $comunes = array_intersect_key($cabecera, array_flip(self::CAMPOS_CABECERA));

        return DB::transaction(function () use ($comunes, $documentos, $usuarioId) {
            $ids = [];

            foreach ($documentos as $i => $documento) {
                try {
                    $ids[] = $this->facturas->crear(array_merge($comunes, $documento), $usuarioId);
                } catch (FacturaproveedorException $e) {
                    // Sin el número de fila el usuario no sabe cuál de los N
                    // documentos corregir.
                    throw new FacturaproveedorExceptionEnFila($i + 1, $e);
                }
            }

            return $ids;
        });
    }
}
