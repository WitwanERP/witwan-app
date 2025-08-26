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
 */
class ApiDocumentation {}
