<?php

namespace App\Http\Controllers\Web\Operaciones;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pantallas de Operaciones (autorizar, guardia, tráfico): el
 * área de la URL se traduce al fk_sistema_id del legacy y se comparten los
 * catálogos que los tres controllers del CI armaban en su constructor.
 */
abstract class OperacionesController extends Controller
{
    /** Mismo switch que los controllers de operaciones/*.php del CI. */
    public const AREAS = [
        'receptivo' => 1,
        'mayorista' => 2,
        'minorista' => 3,
        'consolidador' => 4,
        'nacional' => 7,
        'corporativo' => 7,
    ];

    public static function patronDeArea(): string
    {
        return implode('|', array_keys(self::AREAS));
    }

    protected function sistema(string $area): int
    {
        return self::AREAS[$area] ?? abort(404);
    }

    /** Proveedores habilitados con algún servicio (como el legacy). */
    protected function proveedoresConServicios(): array
    {
        return DB::table('proveedor as p')
            ->join('servicio as s', 's.fk_proveedor_id', '=', 'p.proveedor_id')
            ->whereIn('p.habilita', ['Y', '1'])
            ->groupBy('p.proveedor_id', 'p.proveedor_nombre')
            ->orderBy('p.proveedor_nombre')
            ->get(['p.proveedor_id', 'p.proveedor_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->proveedor_id, 'label' => $r->proveedor_nombre])
            ->all();
    }

    /** Clientes con reservas. */
    protected function clientesConReservas(): array
    {
        return DB::table('cliente as c')
            ->join('reserva as r', 'r.fk_cliente_id', '=', 'c.cliente_id')
            ->groupBy('c.cliente_id', 'c.cliente_nombre')
            ->orderBy('c.cliente_nombre')
            ->get(['c.cliente_id', 'c.cliente_nombre'])
            ->map(fn ($r) => ['value' => (int) $r->cliente_id, 'label' => $r->cliente_nombre])
            ->all();
    }

    protected function guias(): array
    {
        return DB::table('guia')->orderBy('guia_apellido')->get(['guia_id', 'guia_nombre', 'guia_apellido'])
            ->map(fn ($g) => ['value' => (int) $g->guia_id, 'label' => trim("{$g->guia_apellido} {$g->guia_nombre}")])
            ->all();
    }

    protected function vendedores(): array
    {
        return DB::table('usuario')->where('usuario_interno', 'Y')->where('habilitar', 'Y')->orderBy('usuario_apellido')
            ->get(['usuario_id', 'usuario_nombre', 'usuario_apellido'])
            ->map(fn ($u) => ['value' => (int) $u->usuario_id, 'label' => trim("{$u->usuario_apellido} {$u->usuario_nombre}")])
            ->all();
    }

    protected function dmy(?string $fecha): string
    {
        if ($fecha === null || $fecha === '' || str_starts_with($fecha, '0000')) {
            return '';
        }

        return substr($fecha, 8, 2).'/'.substr($fecha, 5, 2).'/'.substr($fecha, 0, 4);
    }

    /** ISO o '' (acepta dd/mm/yyyy de los links viejos). */
    protected function iso(mixed $valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
    }

    /** Comentarios del legacy pueden venir en base64 (a veces varias veces). */
    protected function decodificar(?string $texto): string
    {
        $texto = (string) $texto;
        for ($i = 0; $i < 3; $i++) {
            $dec = base64_decode($texto, true);
            if ($dec === false || $texto === '' || base64_encode($dec) !== $texto || ! mb_check_encoding($dec, 'UTF-8')) {
                break;
            }
            $texto = $dec;
        }

        return trim($texto);
    }
}
