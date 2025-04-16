<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="TipoFactura",
 *     @OA\Property(property="tipofactura_id", type="integer", example=1),
 *     @OA\Property(property="tipofactura_nombre", type="string", example="Factura A"),
 *     @OA\Property(property="tipofactura_letra", type="string", example="A"),
 *     @OA\Property(property="tipofactura_descripcion", type="string", example="Factura para responsables inscriptos"),
 *     @OA\Property(property="tipofactura_comprobante_id", type="integer", example=1),
 * )
 *
 * @OA\Get(
 *     path="/tipo-factura",
 *     operationId="tipoFacturaIndex",
 *     tags={"Configuración - Tipos de Factura"},
 *     summary="Listar todos los tipos de factura",
 *     description="Obtiene una lista de todos los tipos de factura disponibles",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/TipoFactura")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/tipo-factura/{id}",
 *     operationId="tipoFacturaShow",
 *     tags={"Configuración - Tipos de Factura"},
 *     summary="Obtener un tipo de factura por ID",
 *     description="Muestra los detalles de un tipo de factura específico",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         description="ID del tipo de factura",
 *         required=true,
 *         in="path",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(ref="#/components/schemas/TipoFactura")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Tipo de factura no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Tipo de factura no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/tipo-factura/search",
 *     operationId="tipoFacturaSearch",
 *     tags={"Configuración - Tipos de Factura"},
 *     summary="Buscar tipos de factura",
 *     description="Busca tipos de factura por nombre",
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
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TipoFactura")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 */
class TipoFacturaDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
}
