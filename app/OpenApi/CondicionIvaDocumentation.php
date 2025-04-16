<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="CondicionIva",
 *     @OA\Property(property="condicioniva_id", type="integer", example=1),
 *     @OA\Property(property="condicioniva_nombre", type="string", example="Responsable Inscripto"),
 *     @OA\Property(property="condicioniva_descripcion", type="string", example="Contribuyente inscripto en el régimen general"),
 *     @OA\Property(property="condicioniva_codigo", type="string", example="RI"),
 *     @OA\Property(property="condicioniva_activo", type="boolean", example=true)
 * )
 *
 * @OA\Get(
 *     path="/condiciones-iva",
 *     operationId="condicionIvaIndex",
 *     tags={"Configuración - Condiciones de IVA"},
 *     summary="Listar todas las condiciones de IVA",
 *     description="Obtiene una lista de todas las condiciones de IVA disponibles",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/CondicionIva")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/condiciones-iva/{id}",
 *     operationId="condicionIvaShow",
 *     tags={"Configuración - Condiciones de IVA"},
 *     summary="Obtener una condición de IVA por ID",
 *     description="Muestra los detalles de una condición de IVA específica",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         description="ID de la condición de IVA",
 *         required=true,
 *         in="path",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(ref="#/components/schemas/CondicionIva")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Condición de IVA no encontrada",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Condición de IVA no encontrada")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/condiciones-iva/search",
 *     operationId="condicionIvaSearch",
 *     tags={"Configuración - Condiciones de IVA"},
 *     summary="Buscar condiciones de IVA",
 *     description="Busca condiciones de IVA por nombre",
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
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CondicionIva")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 */
class CondicionIvaDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
}
