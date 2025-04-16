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
 *     path="/geo/pais",
 *     operationId="paisIndex",
 *     tags={"Configuración - Países"},
 *     summary="Listar todos los países",
 *     description="Obtiene una lista de todos los países disponibles",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/Pais")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/paises/{id}",
 *     operationId="paisShow",
 *     tags={"Configuración - Países"},
 *     summary="Obtener un país por ID",
 *     description="Muestra los detalles de un país específico",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         description="ID del país",
 *         required=true,
 *         in="path",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(ref="#/components/schemas/Pais")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="País no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="País no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/paises/search",
 *     operationId="paisSearch",
 *     tags={"Configuración - Países"},
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
class PaisDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
}
