<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="WITWAN API",
 *     version="1.0",
 *     description="API para la gestión externa de la empresa",
 * )
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
@OA\Schema(
 *     schema="Cliente",
 *     required={"cliente_nombre", "cliente_razonsocial", "cuit"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="cliente_nombre", type="string", example="Empresa XYZ"),
 *     @OA\Property(property="cliente_razonsocial", type="string", example="Empresa XYZ S.A."),
 *     @OA\Property(property="cuit", type="string", example="30712345678"),
 *     @OA\Property(property="limite_credito", type="number", format="float", example=10000.00),
 *     @OA\Property(property="credito_habilitado", type="boolean", example=true),
 *     @OA\Property(property="credito_utilizado", type="number", format="float", example=5000.00),
 *     @OA\Property(property="cliente_direccionfiscal", type="string", example="Av. Corrientes 1234"),
 *     @OA\Property(property="cliente_codigopostal", type="string", example="C1043AAZ"),
 *     @OA\Property(property="cliente_email", type="string", example="contacto@empresa.com"),
 *     @OA\Property(property="cliente_email2", type="string", example="ventas@empresa.com"),
 *     @OA\Property(property="cliente_emailadmin", type="string", example="admin@empresa.com"),
 *     @OA\Property(property="cliente_fax", type="string", example="011-4567-8900"),
 *     @OA\Property(property="cliente_legajo", type="string", example="CL-001"),
 *     @OA\Property(property="cliente_ciudad", type="string", example="Buenos Aires"),
 *     @OA\Property(property="cliente_provincia", type="string", example="CABA"),
 *     @OA\Property(property="cliente_telefono", type="string", example="011-4567-8901"),
 *     @OA\Property(property="cliente_celular", type="string", example="11-2345-6789"),
 *     @OA\Property(property="cliente_contacto", type="string", example="Juan Pérez"),
 *     @OA\Property(property="cliente_observaciones", type="string", example="Cliente preferencial"),
 *     @OA\Property(property="fk_idioma_id", type="integer", example=1),
 *     @OA\Property(property="fk_pais_id", type="integer", example=1)
 * )
 *
 * @OA\Get(
 *     path="/cliente",
 *     operationId="clienteIndex",
 *     tags={"Clientes"},
 *     summary="Obtener lista de clientes",
 *     description="Retorna lista de clientes paginada con filtros opcionales",
 * security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Cantidad de registros por página",
 *         required=false,
 *         @OA\Schema(type="integer", default=100)
 *     ),
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         description="Filtrar por nombre del cliente",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="razon_social",
 *         in="query",
 *         description="Filtrar por razón social",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="cuit",
 *         in="query",
 *         description="Filtrar por CUIT",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="ciudad",
 *         in="query",
 *         description="Filtrar por ciudad",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="pais_id",
 *         in="query",
 *         description="Filtrar por ID de país",
 *         required=false,
 *         @OA\Schema(type="integer")
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
 *
 * @OA\Post(
 *     path="/clientes",
 *     operationId="clienteStore",
 *     tags={"Clientes"},
 *     summary="Crear nuevo cliente",
 *     description="Almacena un nuevo cliente y retorna los datos",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"cliente_nombre", "cliente_razonsocial", "cuit"},
 *             @OA\Property(property="cliente_nombre", type="string", example="Empresa XYZ"),
 *             @OA\Property(property="cliente_razonsocial", type="string", example="Empresa XYZ S.A."),
 *             @OA\Property(property="cuit", type="string", example="30712345678"),
 *             @OA\Property(property="limite_credito", type="number", format="float", example=10000),
 *             @OA\Property(property="credito_habilitado", type="boolean", example=true),
 *             @OA\Property(property="cliente_direccionfiscal", type="string", example="Av. Corrientes 1234"),
 *             @OA\Property(property="cliente_codigopostal", type="string", example="C1043AAZ"),
 *             @OA\Property(property="cliente_email", type="string", example="contacto@empresa.com"),
 *             @OA\Property(property="cliente_email2", type="string", example="ventas@empresa.com"),
 *             @OA\Property(property="cliente_emailadmin", type="string", example="admin@empresa.com"),
 *             @OA\Property(property="cliente_fax", type="string", example="011-4567-8900"),
 *             @OA\Property(property="cliente_legajo", type="string", example="CL-001"),
 *             @OA\Property(property="fk_idioma_id", type="integer", example=1),
 *             @OA\Property(property="cliente_ciudad", type="string", example="Buenos Aires"),
 *             @OA\Property(property="cliente_provincia", type="string", example="CABA"),
 *             @OA\Property(property="fk_pais_id", type="integer", example=1),
 *             @OA\Property(property="cliente_telefono", type="string", example="011-4567-8901"),
 *             @OA\Property(property="cliente_celular", type="string", example="11-2345-6789"),
 *             @OA\Property(property="cliente_contacto", type="string", example="Juan Pérez"),
 *             @OA\Property(property="cliente_observaciones", type="string", example="Cliente preferencial")
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
 *             @OA\Property(property="errors", type="object", example={"cuit": {"Ya existe un cliente con este CUIT"}})
 *         )
 *     )
 * )
 *
 *
 * @OA\Get(
 *     path="/cliente/{id}",
 *     operationId="clienteShow",
 *     tags={"Clientes"},
 *     summary="Mostrar información de un cliente",
 *     description="Retorna los datos de un cliente específico",
 * security={{"bearerAuth":{}}},
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
 *
 * @OA\Put(
 *     path="/cliente/{id}",
 *     operationId="clienteUpdate",
 *     tags={"Clientes"},
 *     summary="Actualizar cliente existente",
 *     description="Actualiza los datos de un cliente específico y retorna los datos actualizados",
 * security={{"bearerAuth":{}}},
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
 *
 * @OA\Delete(
 *     path="/cliente/{id}",
 *     operationId="clienteDestroy",
 *     tags={"Clientes"},
 *     summary="Eliminar un cliente",
 *     description="Elimina un cliente específico",
 * security={{"bearerAuth":{}}},
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
 *
 * @OA\Get(
 *     path="/cliente/search",
 *     operationId="clienteSearch",
 *     tags={"Clientes"},
 *     summary="Buscar clientes",
 *     description="Busca clientes por término en nombre o razón social",
 * security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Término de búsqueda",
 *         required=true,
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
class ClienteDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
    // No tiene funcionalidad real
}
