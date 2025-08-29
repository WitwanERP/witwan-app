<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="Pais",
 *     @OA\Property(property="pais_id", type="integer", example=1),
 *     @OA\Property(property="pais_nombre", type="string", example="Argentina"),
 *     @OA\Property(property="pais_codigo", type="string", example="AR"),
 *     @OA\Property(property="pais_iso3", type="string", example="ARG"),
 *     @OA\Property(property="pais_numerico", type="string", example="032"),
 *     @OA\Property(property="pais_gentilicio", type="string", example="Argentino/a")
 * )
 *
 * @OA\Get(
 *     path="/geo/paises",
 *     operationId="paisIndex",
 *     tags={"Paises"},
 *     summary="Listar países",
 *     description="Obtiene una lista paginada de países",
 *     security={{"bearerAuth":{}}},
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
 *     )
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista de países",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Pais")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/geo/paises",
 *     operationId="paisStore",
 *     tags={"Paises"},
 *     summary="Crear país",
 *     description="Crea un país",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"pais_nombre", "pais_codigo"},
 *             @OA\Property(property="pais_nombre", type="string", example="Argentina"),
 *             @OA\Property(property="pais_codigo", type="string", example="AR"),
 *             @OA\Property(property="pais_iso3", type="string", example="ARG"),
 *             @OA\Property(property="pais_numerico", type="string", example="032"),
 *             @OA\Property(property="pais_gentilicio", type="string", example="Argentino/a")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="País creado exitosamente",
 *         @OA\JsonContent(ref="#/components/schemas/Pais")
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
 *     path="/geo/paises/{id}",
 *     operationId="paisShow",
 *     tags={"Paises"},
 *     summary="Mostrar país",
 *     description="Obtiene los datos de un país por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del país",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Datos del país",
 *         @OA\JsonContent(ref="#/components/schemas/Pais")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="País no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Put(
 *     path="/geo/paises/{id}",
 *     operationId="paisUpdate",
 *     tags={"Paises"},
 *     summary="Actualizar país",
 *     description="Actualiza los datos de un país existente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del país",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="pais_nombre", type="string", example="Argentina Actualizada"),
 *             @OA\Property(property="pais_codigo", type="string", example="AR"),
 *             @OA\Property(property="pais_iso3", type="string", example="ARG"),
 *             @OA\Property(property="pais_numerico", type="string", example="032"),
 *             @OA\Property(property="pais_gentilicio", type="string", example="Argentino/a")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="País actualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Pais")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="País no encontrado",
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
 *     path="/geo/paises/{id}",
 *     operationId="paisDestroy",
 *     tags={"Paises"},
 *     summary="Eliminar país",
 *     description="Elimina un país por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del país",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="País eliminado exitosamente"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="País no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/geo/paises/search",
 *     operationId="paisSearch",
 *     tags={"Paises"},
 *     summary="Buscar países",
 *     description="Busca países por nombre o código",
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
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Pais")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 */
class PaisDocumentation {}
