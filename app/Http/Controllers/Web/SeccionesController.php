<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista TEMPORAL de desarrollo: todas las pantallas GET registradas bajo /app,
 * agrupadas por módulo, para navegarlas sin depender de la botonera del CI.
 * Se arma desde el router en cada request (no hay lista que mantener): las
 * rutas con parámetros acotados por alternación (área, sistema, tipo…) se
 * expanden en un link por valor; las que exigen un id se listan sin link.
 * Quitar la ruta `/app/secciones` de routes/web.php cuando deje de servir.
 */
class SeccionesController extends Controller
{
    /** Endpoints auxiliares (JSON, exports, autocompletes) que no son pantallas. */
    private const EXCLUIR = '~(/export$|/exportar$|/_probe$|/nueva/(clientes|cliente|proveedores|ciudades|productos)|/reservas/[^/]+/clientes$|/asientos/[^/]+/(clientes|cotizacion|cuentas)$|/resumen/|/cotizar$|/secciones$)~';

    private const MAX_COMBINACIONES = 60;

    public function index(): Response
    {
        $grupos = [];
        foreach (Route::getRoutes() as $ruta) {
            /** @var Ruta $ruta */
            $uri = $ruta->uri();
            if (! in_array('GET', $ruta->methods(), true) || ($uri !== 'app' && ! str_starts_with($uri, 'app/')) || preg_match(self::EXCLUIR, '/'.$uri)) {
                continue;
            }
            $segmentos = explode('/', $uri);
            $grupo = $segmentos[1] ?? 'inicio';
            $accion = $ruta->getActionName();
            $item = [
                'uri' => '/'.$uri,
                'nombre' => (string) ($ruta->getName() ?? ''),
                'controlador' => str_contains($accion, '\\') ? substr($accion, strrpos($accion, '\\') + 1) : 'Closure',
                'links' => $this->expandir($uri, $ruta->wheres),
            ];
            $grupos[$grupo][] = $item;
        }
        ksort($grupos);
        foreach ($grupos as &$items) {
            usort($items, fn ($a, $b) => strcmp($a['uri'], $b['uri']));
        }
        unset($items);

        return Inertia::render('Sistema/Secciones', [
            'grupos' => $grupos,
            'total' => array_sum(array_map('count', $grupos)),
            'rutasMigradas' => (array) config('menu.rutas_migradas', []),
        ]);
    }

    /**
     * Links concretos para una URI: sin parámetros, uno; con parámetros acotados
     * por alternación (`a|b|c`), el producto cartesiano (tope MAX_COMBINACIONES);
     * con algún parámetro libre (id), ninguno.
     *
     * @return list<array{href:string,label:string}>
     */
    private function expandir(string $uri, array $wheres): array
    {
        if (! preg_match_all('/\{(\w+)\??\}/', $uri, $m)) {
            return [['href' => '/'.$uri, 'label' => '/'.$uri]];
        }
        $valores = [];
        foreach ($m[1] as $param) {
            $patron = (string) ($wheres[$param] ?? '');
            if ($patron === '' || ! preg_match('/^[A-Za-z0-9_|-]+$/', $patron)) {
                return [];
            }
            $valores[$param] = explode('|', $patron);
        }
        $combos = [[]];
        foreach ($valores as $param => $lista) {
            $nuevo = [];
            foreach ($combos as $c) {
                foreach ($lista as $v) {
                    $nuevo[] = $c + [$param => $v];
                    if (count($nuevo) > self::MAX_COMBINACIONES) {
                        break 2;
                    }
                }
            }
            $combos = $nuevo;
        }
        $out = [];
        foreach ($combos as $c) {
            $href = $uri;
            foreach ($c as $param => $v) {
                $href = preg_replace('/\{'.$param.'\??\}/', $v, $href);
            }
            $out[] = ['href' => '/'.$href, 'label' => implode(' · ', $c)];
        }

        return $out;
    }
}
