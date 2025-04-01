<?php

namespace App\Http\Controllers\Empresas;

use App\Models\Empresas\Cliente;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="API de Clientes",
 *     version="1.0",
 *     description="API para la gestión de clientes"
 * )
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\Schema(
 *     schema="Cliente",
 *     required={"cliente_nombre", "cliente_razonsocial"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="cliente_nombre", type="string", example="Empresa XYZ"),
 *     @OA\Property(property="cliente_razonsocial", type="string", example="Empresa XYZ S.A."),
 *     @OA\Property(property="limite_credito", type="number", format="float", example=10000.00),
 *     @OA\Property(property="credito_habilitado", type="boolean", example=true),
 *     @OA\Property(property="credito_utilizado", type="number", format="float", example=5000.00),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ClienteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/clientes",
     *     operationId="clienteIndex",
     *     tags={"Clientes"},
     *     summary="Obtener lista de clientes",
     *     description="Retorna lista de clientes paginada",
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Cantidad de registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Cliente")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 100);
        $clientes = Cliente::with(['pais'])
            ->paginate($perPage);

        return response()->json($clientes);
    }

    /**
     * @OA\Post(
     *     path="/clientes",
     *     operationId="clienteStore",
     *     tags={"Clientes"},
     *     summary="Crear nuevo cliente",
     *     description="Almacena un nuevo cliente y retorna los datos",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cliente_nombre", "cliente_razonsocial"},
     *             @OA\Property(property="cliente_nombre", type="string", example="Empresa XYZ"),
     *             @OA\Property(property="cliente_razonsocial", type="string", example="Empresa XYZ S.A."),
     *             @OA\Property(property="limite_credito", type="number", format="float", example=10000),
     *             @OA\Property(property="credito_habilitado", type="boolean", example=true),
     *             @OA\Property(property="credito_utilizado", type="number", format="float", example=2500)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cliente creado exitosamente",
     *         @OA\JsonContent(ref="#/components/schemas/Cliente")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_nombre' => 'required|string|max:250',
            'cliente_razonsocial' => 'required|string|max:250',
            'limite_credito' => 'numeric',
            'credito_habilitado' => 'boolean',
            'credito_utilizado' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cliente = Cliente::create($request->all());
        return response()->json($cliente, 201);
    }

    /**
     * @OA\Get(
     *     path="/clientes/{id}",
     *     operationId="clienteShow",
     *     tags={"Clientes"},
     *     summary="Mostrar información de un cliente",
     *     description="Retorna los datos de un cliente específico",
     *     @OA\Parameter(
     *         name="id",
     *         description="ID del cliente",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(ref="#/components/schemas/Cliente")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registro no encontrado")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            return response()->json($cliente);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/clientes/{id}",
     *     operationId="clienteUpdate",
     *     tags={"Clientes"},
     *     summary="Actualizar cliente existente",
     *     description="Actualiza los datos de un cliente específico y retorna los datos actualizados",
     *     @OA\Parameter(
     *         name="id",
     *         description="ID del cliente",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="cliente_nombre", type="string", example="Empresa XYZ Actualizada"),
     *             @OA\Property(property="cliente_razonsocial", type="string", example="Empresa XYZ S.A. Actualizada"),
     *             @OA\Property(property="limite_credito", type="number", format="float", example=15000),
     *             @OA\Property(property="credito_habilitado", type="boolean", example=true),
     *             @OA\Property(property="credito_utilizado", type="number", format="float", example=3000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(ref="#/components/schemas/Cliente")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cliente no encontrada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'cliente_nombre' => 'string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $cliente->update($request->all());
            return response()->json($cliente);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente no encontrada'], 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/clientes/{id}",
     *     operationId="clienteDestroy",
     *     tags={"Clientes"},
     *     summary="Eliminar cliente",
     *     description="Elimina un cliente específico",
     *     @OA\Parameter(
     *         name="id",
     *         description="ID del cliente",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Cliente eliminado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cliente no encontrada")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $cliente->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente no encontrada'], 404);
        }
    }

    /**
     * @OA\Get(
     *     path="/clientes/search",
     *     operationId="clienteSearch",
     *     tags={"Clientes"},
     *     summary="Buscar clientes",
     *     description="Busca clientes por nombre",
     *     @OA\Parameter(
     *         name="nombre",
     *         in="query",
     *         description="Nombre a buscar",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Cantidad de registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Cliente")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        $query = Cliente::query();

        if ($request->has('nombre')) {
            $query->where('region_nombre', 'like', '%' . $request->nombre . '%');
        }

        return response()->json($query->paginate($request->get('per_page', 100)));
    }
}
