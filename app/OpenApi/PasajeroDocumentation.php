<?php

namespace App\OpenApi;

/**
 *
 * @OA\Schema(
 *     schema="Pasajero",
 *     @OA\Property(property="pasajero_id", type="integer", example=1),
 *     @OA\Property(property="pasajero_nombre", type="string", example="Juan"),
 *     @OA\Property(property="pasajero_apellido", type="string", example="Pérez"),
 *     @OA\Property(property="pasajero_apodo", type="string", example="Juanito"),
 *     @OA\Property(property="pasajero_nacionalidad", type="string", example="Argentina"),
 *     @OA\Property(property="pasajero_nacimiento", type="string", format="date", example="1990-05-15"),
 *     @OA\Property(property="pasajero_sexo", type="string", enum={"M", "F"}, example="M"),
 *     @OA\Property(property="pasajero_email", type="string", format="email", example="juan.perez@email.com"),
 *     @OA\Property(property="fk_cliente_id", type="integer", example=1),
 *     @OA\Property(property="tipodoc", type="string", example="DNI"),
 *     @OA\Property(property="nrodoc", type="string", example="12345678"),
 *     @OA\Property(property="fk_pais_id", type="integer", example=1),
 *     @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *     @OA\Property(property="pasajero_direccionfiscal", type="string", example="Av. Corrientes 1234"),
 *     @OA\Property(property="observaciones", type="string", example="Observaciones del pasajero"),
 *     @OA\Property(property="ultimo_mail", type="string", format="date-time")
 * )
 *
 * @OA\Get(
 *     path="/pasajeros/pasajeros",
 *     security={{"bearerAuth":{}}},
 *     operationId="pasajeroIndex",
 *     tags={"Pasajeros"},
 *     summary="Listar pasajeros",
 *     description="Obtiene una lista paginada de pasajeros",
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
 *         description="Lista de pasajeros",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Pasajero")),
 *             @OA\Property(property="links", type="object"),
 *             @OA\Property(property="meta", type="object")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/pasajeros/pasajeros",
 *     operationId="pasajeroStore",
 *     tags={"Pasajeros"},
 *     summary="Crear pasajero",
 *     description="Crea un nuevo pasajero",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"pasajero_nombre", "pasajero_apellido", "pasajero_email", "fk_cliente_id"},
 *             @OA\Property(property="pasajero_nombre", type="string", example="Juan"),
 *             @OA\Property(property="pasajero_apellido", type="string", example="Pérez"),
 *             @OA\Property(property="pasajero_email", type="string", example="juan.perez@email.com"),
 *             @OA\Property(property="fk_cliente_id", type="integer", example=1),
 *             @OA\Property(property="pasajero_nacionalidad", type="string", example="Argentina"),
 *             @OA\Property(property="pasajero_nacimiento", type="string", format="date", example="1990-05-15"),
 *             @OA\Property(property="pasajero_sexo", type="string", enum={"M", "F"}, example="M"),
 *             @OA\Property(property="tipodoc", type="string", example="DNI"),
 *             @OA\Property(property="nrodoc", type="string", example="12345678"),
 *             @OA\Property(property="fk_pais_id", type="integer", example=1),
 *             @OA\Property(property="fk_ciudad_id", type="integer", example=1),
 *             @OA\Property(property="observaciones", type="string", example="Observaciones del pasajero")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Pasajero creado exitosamente",
 *         @OA\JsonContent(ref="#/components/schemas/Pasajero")
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
 *     path="/pasajeros/pasajeros/{id}",
 *     operationId="pasajeroShow",
 *     tags={"Pasajeros"},
 *     summary="Mostrar pasajero",
 *     description="Obtiene los datos de un pasajero por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del pasajero",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Datos del pasajero",
 *         @OA\JsonContent(ref="#/components/schemas/Pasajero")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Pasajero no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 *
 * @OA\Put(
 *     path="/pasajeros/pasajeros/{id}",
 *     operationId="pasajeroUpdate",
 *     tags={"Pasajeros"},
 *     summary="Actualizar pasajero",
 *     description="Actualiza los datos de un pasajero existente",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del pasajero",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="pasajero_nombre", type="string", example="Juan Carlos"),
 *             @OA\Property(property="pasajero_apellido", type="string", example="Pérez López"),
 *             @OA\Property(property="pasajero_email", type="string", example="nuevo@email.com"),
 *             @OA\Property(property="fk_cliente_id", type="integer", example=1),
 *             @OA\Property(property="pasajero_nacionalidad", type="string", example="Argentina"),
 *             @OA\Property(property="observaciones", type="string", example="Nuevas observaciones")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Pasajero actualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Pasajero")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Pasajero no encontrado",
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
 *     path="/pasajeros/pasajeros/{id}",
 *     operationId="pasajeroDestroy",
 *     tags={"Pasajeros"},
 *     summary="Eliminar pasajero",
 *     description="Elimina un pasajero por ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID del pasajero",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=204,
 *         description="Pasajero eliminado exitosamente"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Pasajero no encontrado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Registro no encontrado")
 *         )
 *     )
 * )
 */
class PasajeroDocumentation {}