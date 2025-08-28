<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="Ciudad",
 *     @OA\Property(property="ciudad_id", type="integer", example=1),
 *     @OA\Property(property="ciudad_activo", type="boolean", example=true),
 *     @OA\Property(property="fk_pais_id", type="integer", example=1),
 *     @OA\Property(property="fk_ciudad_id", type="integer", example=2),
 *     @OA\Property(property="ciudad_nombre", type="string", example="Buenos Aires"),
 *     @OA\Property(property="ciudad_codigo", type="string", example="BUE"),
 *     @OA\Property(property="nombre_en", type="string", example="Buenos Aires"),
 *     @OA\Property(property="nombre_pg", type="string", example="Buenos Aires"),
 *     @OA\Property(property="ap", type="boolean", example=false),
 *     @OA\Property(property="codigo_tourico", type="string", example="T123"),
 *     @OA\Property(property="codigo_amex", type="integer", example=456),
 *     @OA\Property(property="codigo_hb", type="string", example="HB789"),
 *     @OA\Property(property="codigo_ws1", type="string", example="WS1"),
 *     @OA\Property(property="codigo_ws2", type="string", example="WS2"),
 *     @OA\Property(property="codigo_ws3", type="string", example="WS3"),
 *     @OA\Property(property="codigo_ws4", type="string", example="WS4"),
 *     @OA\Property(property="codigo_ws5", type="string", example="WS5"),
 *     @OA\Property(property="latitud", type="number", format="float", example=-34.6037),
 *     @OA\Property(property="longitud", type="number", format="float", example=-58.3816)
 * )
 *
 * @OA\Get(
 *     path="/geo/ciudades",
 *     operationId="ciudadIndex",
 *     tags={"Ciudades"},
 *     summary="Listar ciudades",
 *     description="Obtiene una lista paginada de ciudades",
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
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Lista de ciudades",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Ciudad")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/geo/ciudades",
 *     operationId="ciudadStore",
 *     tags={"Ciudades"},
 *     summary="Crear ciudad",
 *     description="Crea una ciudad",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"ciudad_nombre", "fk_pais_id"},
 *             @OA\Property(property="ciudad_nombre", type="string", example="Buenos Aires"),
 *             @OA\Property(property="fk_pais_id", type="integer", example=1),
 *             @OA\Property(property="ciudad_activo", type="boolean", example=true),
 *             @OA\Property(property="ciudad_codigo", type="string", example="BUE"),
 *             @OA\Property(property="nombre_en", type="string", example="Buenos Aires"),
 *             @OA\Property(property="nombre_pg", type="string", example="Buenos Aires"),
 *             @OA\Property(property="ap", type="boolean", example=false),
 *             @OA\Property(property="codigo_tourico", type="string", example="T123"),
 *             @OA\Property(property="codigo_amex", type="integer", example=456),
 *             @OA\Property(property="codigo_hb", type="string", example="HB789"),
 *             @OA\Property(property="codigo_ws1", type="string", example="WS1"),
 *             @OA\Property(property="codigo_ws2", type="string", example="WS2"),
 *             @OA\Property(property="codigo_ws3", type="string", example="WS3"),
 *             @OA\Property(property="codigo_ws4", type="string", example="WS4"),
 *             @OA\Property(property="codigo_ws5", type="string", example="WS5"),
 *             @OA\Property(property="latitud", type="number", format="float", example=-34.6037),
 *             @OA\Property(property="longitud", type="number", format="float", example=-58.3816)
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Ciudad creada exitosamente",
 *         @OA\JsonContent(ref="#/components/schemas/Ciudad")
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
 *     path="/geo/ciudades/{id}",
 *     operationId="ciudadShow",
 *     tags={"Ciudades"},
 *     summary="Mostrar ciudad",
 *     description="Obtiene los datos de una ciudad por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID de la ciudad",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Datos de la ciudad",
 *         @OA\JsonContent(ref="#/components/schemas/Ciudad")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Ciudad no encontrada",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Put(
 *     path="/geo/ciudades/{id}",
 *     operationId="ciudadUpdate",
 *     tags={"Ciudades"},
 *     summary="Actualizar ciudad",
 *     description="Actualiza los datos de una ciudad existente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID de la ciudad",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="ciudad_nombre", type="string", example="Ciudad Actualizada"),
 *             @OA\Property(property="ciudad_activo", type="boolean", example=true),
 *             @OA\Property(property="ciudad_codigo", type="string", example="BUE"),
 *             @OA\Property(property="nombre_en", type="string", example="Buenos Aires"),
 *             @OA\Property(property="nombre_pg", type="string", example="Buenos Aires"),
 *             @OA\Property(property="ap", type="boolean", example=false),
 *             @OA\Property(property="codigo_tourico", type="string", example="T123"),
 *             @OA\Property(property="codigo_amex", type="integer", example=456),
 *             @OA\Property(property="codigo_hb", type="string", example="HB789"),
 *             @OA\Property(property="codigo_ws1", type="string", example="WS1"),
 *             @OA\Property(property="codigo_ws2", type="string", example="WS2"),
 *             @OA\Property(property="codigo_ws3", type="string", example="WS3"),
 *             @OA\Property(property="codigo_ws4", type="string", example="WS4"),
 *             @OA\Property(property="codigo_ws5", type="string", example="WS5"),
 *             @OA\Property(property="latitud", type="number", format="float", example=-34.6037),
 *             @OA\Property(property="longitud", type="number", format="float", example=-58.3816)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Ciudad actualizada",
 *         @OA\JsonContent(ref="#/components/schemas/Ciudad")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Ciudad no encontrada",
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
 *     path="/geo/ciudades/{id}",
 *     operationId="ciudadDestroy",
 *     tags={"Ciudades"},
 *     summary="Eliminar ciudad",
 *     description="Elimina una ciudad por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID de la ciudad",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="Ciudad eliminada exitosamente"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Ciudad no encontrada",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 */
class CiudadDocumentation {}
