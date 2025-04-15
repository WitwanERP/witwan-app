<?php

namespace App\OpenApi;

/**
 * @OA\Post(
 *     path="/auth/login",
 *     tags={"Autenticación"},
 *     summary="Iniciar sesión y obtener token JWT",
 *     description="Devuelve un token JWT para su uso en peticiones autenticadas",
 *     operationId="authLogin",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"usuario_mail","password"},
 *             @OA\Property(property="usuario_mail", type="string", example="usuario@ejemplo.com"),
 *             @OA\Property(property="password", type="string", format="password", example="contraseña")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             @OA\Property(property="access_token", type="string"),
 *             @OA\Property(property="token_type", type="string", example="bearer"),
 *             @OA\Property(property="expires_in", type="integer", example=3600)
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Credenciales inválidas",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="string", example="Unauthorized")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/auth/logout",
 *     tags={"Autenticación"},
 *     summary="Cerrar sesión (invalidar token)",
 *     description="Invalida el token JWT actual",
 *     operationId="authLogout",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Successfully logged out")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/auth/refresh",
 *     tags={"Autenticación"},
 *     summary="Refrescar token JWT",
 *     description="Obtiene un nuevo token JWT usando el token actual",
 *     operationId="authRefresh",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             @OA\Property(property="access_token", type="string"),
 *             @OA\Property(property="token_type", type="string", example="bearer"),
 *             @OA\Property(property="expires_in", type="integer", example=3600)
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/auth/me",
 *     tags={"Autenticación"},
 *     summary="Obtener información del usuario autenticado",
 *     description="Devuelve los datos del usuario actual",
 *     operationId="authMe",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Operación exitosa",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="usuario_id", type="integer"),
 *             @OA\Property(property="usuario_mail", type="string")
 *         )
 *     )
 * )
 */
class AuthDocumentation
{
    // Esta clase solo contiene anotaciones para la documentación
}
