<?php

namespace App\Http\Controllers\Admin\Documentos;

use App\Exceptions\Documentos\FacturaproveedorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documentos\FacturaproveedorRequest;
use App\Models\Facturaproveedor;
use App\Services\Documentos\FacturaproveedorListadoService;
use App\Services\Documentos\FacturaproveedorOcupacionService;
use App\Services\Documentos\FacturaproveedorService;
use App\Support\Permisos;
use App\Support\Secciones;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Facturas de Terceros — API JSON.
 *
 * Replica el controlador CodeIgniter `administracion/factura3ero.php`. Toda la
 * lógica vive en App\Services\Documentos, compartida con el front Inertia
 * (Web\Documentos\FacturaproveedorController): antes estaba embebida acá, en 900
 * líneas que no se podían reutilizar ni testear por separado.
 *
 * El contrato HTTP no cambió con esa extracción: mismas rutas, mismos códigos y
 * mismas claves en las respuestas (ver app/OpenApi/FacturaproveedorDocumentation.php).
 *
 * Las ramas de licencias `mutual` y `towerXXX` quedan fuera de alcance por estar
 * en desuso.
 */
class FacturaproveedorController extends Controller
{
    public function __construct(
        private FacturaproveedorService $facturas,
        private FacturaproveedorListadoService $listado,
    ) {}

    /** Listado con los montos calculados (neto gravado, IVA por alícuota, total). */
    public function index(Request $request)
    {
        $filtros = $request->only(FacturaproveedorListadoService::FILTROS);

        return response()->json(
            $this->listado->listarCrudo($filtros, (int) $request->get('per_page', 100))
        );
    }

    /**
     * Alta completa: cabecera, servicio contable asociado, imputación de
     * servicios y asiento contable con sus movimientos.
     */
    public function store(FacturaproveedorRequest $request)
    {
        if ($fallo = $this->sinPermiso('alta', 'crear facturas de terceros')) {
            return $fallo;
        }

        try {
            $id = $this->facturas->crear($request->validated(), Auth::id() ?? 1);
        } catch (FacturaproveedorException $e) {
            return response()->json(['message' => $e->getMessage()], $e->codigoHttp());
        }

        return response()->json(
            Facturaproveedor::with(['proveedor', 'plancuenta'])->find($id),
            201
        );
    }

    public function show($id)
    {
        try {
            return response()->json(
                Facturaproveedor::with(['proveedor', 'plancuenta', 'proyecto', 'usuario'])->findOrFail($id)
            );
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Actualización acotada: cuenta contable, proyecto, item de gasto,
     * imputación y número. Replica `factura3ero::save_after_edit()`, con una
     * diferencia: acá el asiento se vuelve a generar.
     */
    public function update(FacturaproveedorRequest $request, $id)
    {
        if ($fallo = $this->sinPermiso('edicion', 'editar facturas de terceros')) {
            return $fallo;
        }

        try {
            $this->facturas->actualizar((int) $id, $request->validated(), Auth::id() ?? 1);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        } catch (FacturaproveedorException $e) {
            return response()->json(['message' => $e->getMessage()], $e->codigoHttp());
        }

        return response()->json(Facturaproveedor::find($id));
    }

    /** Baja física, validando período contable y que no esté pagada. */
    public function destroy($id)
    {
        if ($fallo = $this->sinPermiso('borrado', 'eliminar facturas de terceros')) {
            return $fallo;
        }

        try {
            $this->facturas->eliminar((int) $id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        } catch (FacturaproveedorException $e) {
            return response()->json(['message' => $e->getMessage()], $e->codigoHttp());
        }

        return response()->json(null, 204);
    }

    /**
     * Datos para impresión: la factura con su proveedor/plan de cuenta y los
     * servicios asociados. Las claves `data`/`datasvc` se mantienen por
     * compatibilidad con los consumidores actuales.
     */
    public function imprimir($id, FacturaproveedorOcupacionService $ocupaciones)
    {
        $factura = Facturaproveedor::with(['proveedor', 'plancuenta'])->find($id);

        if ($factura === null) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        return response()->json([
            'data' => $factura,
            'datasvc' => $ocupaciones->serviciosDeFactura((int) $id),
        ]);
    }

    /** Verifica que no exista una factura con el mismo nro/proveedor/tipo. */
    public function control(Request $request)
    {
        return response()->json([
            'ok' => ! $this->facturas->existeDuplicado(
                (string) $request->get('nro'),
                (int) $request->get('proveedor'),
                (string) $request->get('tipo'),
            ),
        ]);
    }

    /** Datos auxiliares del formulario de alta. */
    public function create()
    {
        $opciones = $this->facturas->opcionesFormulario();

        return response()->json([
            // `proveedores` se mantiene por compatibilidad; el front nuevo usa el
            // autocomplete, porque mandar el padrón entero son megabytes.
            'proveedores' => $this->facturas->buscarProveedores(null, 500),
            'plancuenta' => $opciones['plancuenta'],
            'proyectos' => $opciones['proyectos'],
            'conceptos' => array_column($opciones['conceptos'], 'clave'),
        ]);
    }

    /**
     * Devuelve la respuesta 403 si falta el permiso, o null si puede seguir.
     * Respeta el modo de despliegue de App\Support\Permisos.
     */
    private function sinPermiso(string $accion, string $descripcion)
    {
        if (Permisos::tiene(Secciones::FACTURA_TERCERO, $accion)) {
            return null;
        }

        if (! config('facturaproveedor.permisos_estrictos', false)) {
            return null;
        }

        return response()->json(['message' => "No tiene permiso para {$descripcion}"], 403);
    }
}
