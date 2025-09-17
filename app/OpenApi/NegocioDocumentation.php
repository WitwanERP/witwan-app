<?php

namespace App\OpenApi;

/**
 *
 * @OA\Schema(
 *     schema="Negocio",
 *     @OA\Property(property="negocio_id", type="integer", example=1),
 *     @OA\Property(property="negocio_nombre", type="string", example="Hotel Paradise"),
 *     @OA\Property(property="negocio_vencimiento", type="string", format="date-time", example="2024-12-31 23:59:59"),
 *     @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *     @OA\Property(property="negocio_descripcion", type="string", example="Hotel de lujo con vista al mar"),
 *     @OA\Property(property="fk_sistema_id", type="integer", example=1),
 *     @OA\Property(property="ciudad", type="object", description="Relación con ciudad"),
 *     @OA\Property(property="sistema", type="object", description="Relación con sistema")
 * )
 *
 * @OA\Get(
 *     path="/reservas/negocios",
 *     security={{"bearerAuth":{}}},
 *     operationId="negocioIndex",
 *     tags={"Negocios"},
 *     summary="Listar negocios",
 *     description="Obtiene una lista paginada de negocios",
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Cantidad de registros por página",
 *         required=false,
 *         @OA\Schema(type="integer", default=100)
 *     ),
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Término de búsqueda",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Lista de negocios",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Negocio")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/reservas/negocios",
 *     operationId="negocioStore",
 *     tags={"Negocios"},
 *     summary="Crear negocio",
 *     description="Crea un nuevo negocio",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"negocio_nombre"},
 *             @OA\Property(property="negocio_nombre", type="string", example="Hotel Paradise"),
 *             @OA\Property(property="negocio_vencimiento", type="string", format="date-time", example="2024-12-31 23:59:59"),
 *             @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *             @OA\Property(property="fk_sistema_id", type="integer", example=1),
 *             @OA\Property(property="negocio_descripcion", type="string", example="Hotel de lujo con vista al mar")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Negocio creado exitosamente",
 *         @OA\JsonContent(ref="#/components/schemas/Negocio")
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Error de validación",
 *         @OA\JsonContent(
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Permiso denegado",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Permiso denegado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/reservas/negocios/{id}",
 *     operationId="negocioShow",
 *     tags={"Negocios"},
 *     summary="Mostrar negocio",
 *     description="Obtiene los datos de un negocio por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del negocio",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Datos del negocio",
 *         @OA\JsonContent(ref="#/components/schemas/Negocio")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Negocio no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Put(
 *     path="/reservas/negocios/{id}",
 *     operationId="negocioUpdate",
 *     tags={"Negocios"},
 *     summary="Actualizar negocio",
 *     description="Actualiza los datos de un negocio existente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del negocio",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="negocio_nombre", type="string", example="Hotel Paradise Renovado"),
 *             @OA\Property(property="negocio_vencimiento", type="string", format="date-time", example="2025-12-31 23:59:59"),
 *             @OA\Property(property="fk_ciudad_id", type="integer", example=2),
 *             @OA\Property(property="fk_sistema_id", type="integer", example=1),
 *             @OA\Property(property="negocio_descripcion", type="string", example="Hotel renovado con nuevas instalaciones")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Negocio actualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Negocio")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Negocio no encontrado",
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
 *     path="/reservas/negocios/{id}",
 *     operationId="negocioDestroy",
 *     tags={"Negocios"},
 *     summary="Eliminar negocio",
 *     description="Elimina un negocio por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del negocio",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="Negocio eliminado exitosamente"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Negocio no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/reservas/negocios/search",
 *     operationId="negocioSearch",
 *     tags={"Negocios"},
 *     summary="Búsqueda avanzada de negocios",
 *     description="Busca negocios con filtros específicos",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Cantidad de registros por página",
 *         required=false,
 *         @OA\Schema(type="integer", default=100)
 *     ),
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Término de búsqueda general",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         description="Búsqueda por nombre del negocio",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="vencimiento",
 *         in="query",
 *         description="Fecha de vencimiento desde (dd/mm/yyyy)",
 *         required=false,
 *         @OA\Schema(type="string", format="date", example="31/12/2024")
 *     ),
 *     @OA\Parameter(
 *         name="vencimiento_to",
 *         in="query",
 *         description="Fecha de vencimiento hasta (dd/mm/yyyy)",
 *         required=false,
 *         @OA\Schema(type="string", format="date", example="31/12/2025")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Resultados de búsqueda",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Negocio")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 */
class NegocioDocumentation {}