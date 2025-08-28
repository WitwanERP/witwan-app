<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="Witwan API",
 *     version="1.0",
 *     description="Documentación de la API Witwan"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Ingrese el token JWT en formato: Bearer {token}"
 * )
 * @OA\Post(
 *     path="/auth/login",
 *     operationId="login",
 *     tags={"Auth"},
 *     summary="Login de usuario",
 *     description="Permite al usuario autenticarse y obtener un token JWT",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(property="email", type="string", example="usuario@ejemplo.com"),
 *             @OA\Property(property="password", type="string", example="tu_password")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login exitoso, retorna el token JWT",
 *         @OA\JsonContent(
 *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
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
 */
class ApiDocumentation {}
