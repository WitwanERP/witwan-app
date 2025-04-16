<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="Ciudad",
 *     @OA\Property(property="ciudad_id", type="integer", example=1),
 *     @OA\Property(property="ciudad_nombre", type="string", example="Buenos Aires"),
 *     @OA\Property(property="fk_pais_id", type="integer", example=1),
 *     @OA\Property(property="ciudad_codigo", type="string", example="BSAS"),
 *     @OA\Property(property="ciudad_codigopostal", type="string", example="C1001"),
 *     @OA\Property(
 *         property="pais",
 *         ref="#/components/schemas/Pais"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/geo/ciudad",
 *     operationId="ciudadIndex",
 *     tags={"Configuración - Ciudades"},
 *     summary="Listar todas las ciudades",
 *     description="Obtiene una lista de todas las ciudades disponibles",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Cantidad de registros por página",
 *         required=false,
 *         @OA\Schema(type="integer", default=100)
 *     ),
 *     @OA\Parameter(
 *         name="pais_id",
 *         in="query",
 *         description="Filtrar por país",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Ciudad")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/ciudades/{id}",
 *     operationId="ciudadShow",
 *     tags={"Configuración - Ciudades"},
 *     summary="Obtener una ciudad por ID",
 *     description="Muestra los detalles de una ciudad específica",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         description="ID de la ciudad",
 *         required=true,
 *         in="path",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(ref="#/components/schemas/Ciudad")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Ciudad no encontrada",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Ciudad no encontrada")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/ciudades/search",
 *     operationId="ciudadSearch",
 *     tags={"Configuración - Ciudades"},
 *     summary="Buscar ciudades",
 *     description="Busca ciudades por nombre",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Término de búsqueda",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="pais_id",
 *         in="query",
 *         description="Filtrar por país",
 *         required=false,
 *         @OA\Schema(type="integer")
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
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Ciudad")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/ciudades/by-pais/{pais_id}",
 *     operationId="ciudadByPais",
 *     tags={"Configuración - Ciudades"},
 *     summary="Obtener ciudades por país",
 *     description="Obtiene todas las ciudades de un país específico",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="pais_id",
 *         description="ID del país",
 *         required=true,
 *         in="path",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/Ciudad")
 *         )
 *     )
 * )
 */
class CiudadDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
}
