<?php

namespace App\OpenApi;

/**
 *
 * @OA\Schema(
 *     schema="Cliente",
 *     @OA\Property(property="cliente_id", type="integer", example=1),
 *     @OA\Property(property="cliente_nombre", type="string", example="Empresa Ejemplo"),
 *     @OA\Property(property="cliente_razonsocial", type="string", example="Empresa Ejemplo S.A."),
 *     @OA\Property(property="cuit", type="string", example="20272022500"),
 *     @OA\Property(property="fk_tipoclavefiscal_id", type="integer", example="[1 => CUIT/ROOT,2 => RUC,3 => NIF,6 => DNI,4 => CIF,8 => Pasaporte,9 => CUIL]"),
 *     @OA\Property(property="fk_tipofactura_id", type="integer", example="[1 => CUIT/ROOT,2 => RUC,3 => NIF,6 => DNI,4 => CIF,8 => Pasaporte,9 => CUIL]"),
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
 *     path="/clientes/clientes",
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
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Término de búsqueda",
 *         required=false,
 *         @OA\Schema(type="string")
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
 *     path="/clientes/clientes",
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
 *             @OA\Property(property="fk_tipoclavefiscal_id", type="integer", example="[1 => CUIT/ROOT, 2 => RUC, 3 => NIF, 6 => DNI, 4 => CIF, 8 => Pasaporte, 9 => CUIL]"),
 *             @OA\Property(property="fk_tipofactura_id", type="integer", example="[1 => A, 2 => B, 3 => E]"),
 *             @OA\Property(property="fk_condicioniva_id", type="integer", example="[1 => Responsable Inscripto, 3 => Monotributista, 2 => Exento, 4 => CF]"),
 *             @OA\Property(property="fk_pais_id", type="integer", example="Tomar del endpoint de paises"),
 *             @OA\Property(property="fk_ciudad_id", type="integer", example="Tomar del endpoint de ciudades"),
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
 *     path="/clientes/clientes/{id}",
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
 *     path="/clientes/clientes/{id}",
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
 *     path="/clientes/clientes/{id}",
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
 *     path="/clientes/clientes/{clientId}/credit-limit",
 *     operationId="clienteCreditLimit",
 *     tags={"Clientes"},
 *     summary="Consultar límite de crédito",
 *     description="Obtiene información sobre el límite de crédito disponible de un cliente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="clientId",
 *         in="path",
 *         description="ID del cliente",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="value",
 *         in="query",
 *         description="Monto a verificar si está disponible en el crédito",
 *         required=false,
 *         @OA\Schema(type="number", format="float")
 *     ),
 *     @OA\Parameter(
 *         name="moneda",
 *         in="query",
 *         description="Código de moneda para expresar los valores (ej: USD, EUR). Si no se especifica, se devuelve en moneda base.",
 *         required=false,
 *         @OA\Schema(type="string", example="USD")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Información del crédito del cliente",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="CodeClientBackOffice", type="integer", example=123),
 *             @OA\Property(property="status", type="string", enum={"OK", "NO-OK"}, example="OK"),
 *             @OA\Property(property="Message", type="string", example="Autorizado."),
 *             @OA\Property(property="credito_autorizado", type="number", format="float", example=50000.00),
 *             @OA\Property(property="credito_utilizado", type="number", format="float", example=25000.00),
 *             @OA\Property(property="credito_disponible", type="number", format="float", example=25000.00),
 *             @OA\Property(property="moneda", type="string", example="ARS", description="Moneda en la que se expresan los valores")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Cliente no encontrado",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="CodeClientBackOffice", type="integer"),
 *             @OA\Property(property="status", type="string", example="NO-OK"),
 *             @OA\Property(property="Message", type="string", example="Cliente no encontrado.")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/clientes/clientes/{clientId}/remaining-credit",
 *     operationId="clienteRemainingCredit",
 *     tags={"Clientes"},
 *     summary="Consultar crédito disponible",
 *     description="Obtiene información detallada sobre el crédito disponible de un cliente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="clientId",
 *         in="path",
 *         description="ID del cliente",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Información detallada del crédito disponible",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="cliente_id", type="integer", example=123),
 *             @OA\Property(property="cliente_nombre", type="string", example="Empresa Ejemplo S.A."),
 *             @OA\Property(property="credito_habilitado", type="boolean", example=true),
 *             @OA\Property(property="limite_credito", type="number", format="float", example=50000.00),
 *             @OA\Property(property="credito_extra", type="number", format="float", example=5000.00),
 *             @OA\Property(property="credito_autorizado", type="number", format="float", example=55000.00),
 *             @OA\Property(property="credito_utilizado", type="number", format="float", example=25000.00),
 *             @OA\Property(property="credito_disponible", type="number", format="float", example=30000.00, description="Crédito disponible (siempre >= 0)"),
 *             @OA\Property(property="porcentaje_disponible", type="number", format="float", example=54.55, description="Porcentaje de crédito disponible (0-100)"),
 *             @OA\Property(property="mensaje", type="string", example="Crédito disponible")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Cliente no encontrado",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="error", type="string", example="Cliente no encontrado"),
 *             @OA\Property(property="cliente_id", type="integer", example=123)
 *         )
 *     )
 * )
 */
class ClienteDocumentation {}
