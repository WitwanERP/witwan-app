<?php

namespace App\Services\Tarifarios;

use App\Exceptions\Productos\TarifarioException;
use Illuminate\Support\Facades\DB;

/**
 * Tarifarios (listas de precios por sistema) y sus adjuntos.
 * Port de tarifario.php::_insert_record() (:335-449) sin el DELETE+reinsert de
 * comisiones (ver TarifarioComisionService).
 */
class TarifarioService
{
    public function __construct(private TarifarioComisionService $comisiones) {}

    /** @return list<array> */
    public function listar(int $sistemaId): array
    {
        return DB::table('tarifario')
            ->leftJoin('tarifariocomision as g', function ($j) {
                $j->on('g.fk_tarifario_id', '=', 'tarifario.tarifario_id')
                    ->whereIn('g.fk_submodulo_id', ['0', ''])
                    ->where('g.fk_pais_id', 0)->where('g.fk_ciudad_id', 0)->where('g.fk_producto_id', 0)->where('g.origen', '');
            })
            ->where('tarifario.fk_sistema_id', $sistemaId)
            ->orderBy('tarifario.orden')
            ->orderBy('tarifario.tarifario_nombre')
            ->get(['tarifario.*', 'g.divisor_markup', 'g.porcentaje_comision'])
            ->map(fn ($t) => (array) $t)
            ->all();
    }

    public function cargar(int $id): ?array
    {
        $t = DB::table('tarifario')->where('tarifario_id', $id)->first();
        if ($t === null) {
            return null;
        }

        $fila = (array) $t;
        $fila['comisiones'] = $this->comisiones->listar($id);
        $fila['archivos'] = DB::table('tarifarioarchivo')->where('fk_tarifario_id', $id)->get()->map(fn ($a) => (array) $a)->all();

        return $fila;
    }

    /**
     * @param  array{tarifario_nombre:string, fk_moneda_id:string, cotizacion?:float, interno?:int, orden?:int, comisiones?:list<array>}  $datos
     */
    public function guardar(int $sistemaId, array $datos, ?int $id = null): int
    {
        $errores = [];
        if (trim((string) ($datos['tarifario_nombre'] ?? '')) === '') {
            $errores['tarifario_nombre'] = 'El nombre es obligatorio.';
        }
        if (trim((string) ($datos['fk_moneda_id'] ?? '')) === '') {
            $errores['fk_moneda_id'] = 'La moneda es obligatoria.';
        }
        if ($errores !== []) {
            throw TarifarioException::porCampos($errores);
        }

        return DB::transaction(function () use ($sistemaId, $datos, $id) {
            $fila = [
                'tarifario_nombre' => trim((string) $datos['tarifario_nombre']),
                'fk_moneda_id' => strtoupper(trim((string) $datos['fk_moneda_id'])),
                'cotizacion' => round((float) ($datos['cotizacion'] ?? 0), 5),
            ];
            if (array_key_exists('interno', $datos)) {
                $fila['interno'] = (int) (bool) $datos['interno'];
            }
            if (array_key_exists('orden', $datos)) {
                $fila['orden'] = (int) $datos['orden'];
            }

            if ($id === null) {
                $fila += ['fk_sistema_id' => $sistemaId, 'crol' => 'R', 'orden' => 0, 'archivo' => '', 'interno' => 0];
                $id = (int) DB::table('tarifario')->insertGetId($fila);
            } else {
                if (DB::table('tarifario')->where('tarifario_id', $id)->where('fk_sistema_id', $sistemaId)->doesntExist()) {
                    throw new TarifarioException("El tarifario {$id} no existe en este sistema.");
                }
                DB::table('tarifario')->where('tarifario_id', $id)->update($fila);
            }

            if (array_key_exists('comisiones', $datos)) {
                $this->comisiones->sincronizar($id, (array) $datos['comisiones']);
            }

            return $id;
        });
    }

    public function agregarArchivo(int $id, string $archivo, string $descripcion = ''): int
    {
        return (int) DB::table('tarifarioarchivo')->insertGetId([
            'fk_tarifario_id' => $id,
            'tarifarioarchivo_archivo' => $archivo,
            'tarifarioarchivo_descripcion' => $descripcion,
        ]);
    }

    public function eliminar(int $id): void
    {
        if (DB::table('tarifa')->where('fk_tarifario_id', $id)->exists()) {
            throw new TarifarioException('El tarifario tiene tarifas de venta manual cargadas: no se puede eliminar.');
        }

        DB::transaction(function () use ($id) {
            DB::table('tarifariocomision')->where('fk_tarifario_id', $id)->delete();
            DB::table('tarifarioarchivo')->where('fk_tarifario_id', $id)->delete();
            DB::table('tarifario')->where('tarifario_id', $id)->delete();
        });
    }
}
