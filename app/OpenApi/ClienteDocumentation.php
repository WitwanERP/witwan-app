<?php

namespace App\OpenApi;

/**
 *
 *  * @OA\Info(
 *     title="Witwan API",
 *     version="1.0",
 *     description="Documentación de la API Witwan"
 * )
 * @OA\Schema(
 *     schema="Cliente",
 *     @OA\Property(property="cliente_id", type="integer", example=1),
 *     @OA\Property(property="cliente_nombre", type="string", example="Empresa Ejemplo"),
 *     @OA\Property(property="cliente_razonsocial", type="string", example="Empresa Ejemplo S.A."),
 *     @OA\Property(property="cuit", type="string", example="20272022500"),
 *     @OA\Property(property="fk_tipoclavefiscal_id", type="integer", example=1),
 *     @OA\Property(property="fk_tipofactura_id", type="integer", example=1),
 *     @OA\Property(property="fk_condicioniva_id", type="integer", example=1),
 *     @OA\Property(property="fk_pais_id", type="integer", example=1),
 *     @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *     @OA\Property(property="cliente_direccionfiscal", type="string", example="Av. Corrientes 1234"),
 *     @OA\Property(property="cliente_email", type="string", example="contacto@empresa.com"),
 *     @OA\Property(property="fechacarga", type="string", format="date-time"),
 *     @OA\Property(property="um", type="string", format="date-time"),
 *     @OA\Property(property="fk_usuario_id", type="integer", example=1005)
 * )
 *
 * @OA\Get(
 *     path="/clientes",
 *     security={{"bearerAuth":{}}},
 *     operationId="clienteIndex",
 *     tags={"Clientes"},
 *     summary="Listar clientes",
 *     description="Obtiene una lista paginada de clientes",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Cantidad de registros por página",
 *         required=false,
 *         @OA\Schema(type="integer", default=100)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Lista de clientes",
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
 *     summary="Crear cliente",
 *     description="Crea un nuevo cliente",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"cliente_nombre", "cliente_razonsocial", "cuit", "fk_tipoclavefiscal_id", "fk_tipofactura_id", "fk_condicioniva_id", "fk_pais_id", "fk_ciudad_id", "cliente_direccionfiscal", "cliente_email"},
 *             @OA\Property(property="cliente_nombre", type="string", example="Empresa Ejemplo"),
 *             @OA\Property(property="cliente_razonsocial", type="string", example="Empresa Ejemplo S.A."),
 *             @OA\Property(property="cuit", type="string", example="20272022500"),
 *             @OA\Property(property="fk_tipoclavefiscal_id", type="integer", example=1),
 *             @OA\Property(property="fk_tipofactura_id", type="integer", example=1),
 *             @OA\Property(property="fk_condicioniva_id", type="integer", example=1),
 *             @OA\Property(property="fk_pais_id", type="integer", example=1),
 *             @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *             @OA\Property(property="cliente_direccionfiscal", type="string", example="Av. Corrientes 1234"),
 *             @OA\Property(property="cliente_email", type="string", example="contacto@empresa.com")
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
 *
 * @OA\Get(
 *     path="/clientes/{id}",
 *     operationId="clienteShow",
 *     tags={"Clientes"},
 *     summary="Mostrar cliente",
 *     description="Obtiene los datos de un cliente por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del cliente",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Datos del cliente",
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
 *     path="/clientes/{id}",
 *     operationId="clienteUpdate",
 *     tags={"Clientes"},
 *     summary="Actualizar cliente",
 *     description="Actualiza los datos de un cliente existente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del cliente",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="cliente_nombre", type="string", example="Empresa Ejemplo Actualizada"),
 *             @OA\Property(property="cliente_razonsocial", type="string", example="Empresa Ejemplo S.A. Actualizada"),
 *             @OA\Property(property="cuit", type="string", example="20272022500"),
 *             @OA\Property(property="cliente_email", type="string", example="nuevo@empresa.com")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cliente actualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Cliente")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Cliente no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
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
 *     path="/clientes/{id}",
 *     operationId="clienteDestroy",
 *     tags={"Clientes"},
 *     summary="Eliminar cliente",
 *     description="Elimina un cliente por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del cliente",
 *         required=true,
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
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/clientes/search",
 *     operationId="clienteSearch",
 *     tags={"Clientes"},
 *     summary="Buscar clientes",
 *     description="Busca clientes por término en nombre, razón social o CUIT",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Término de búsqueda",
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
class ClienteDocumentation {}
