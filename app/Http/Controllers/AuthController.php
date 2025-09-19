<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Verificar si es una petición desde CodeIgniter Bridge
        if ($request->has('from_codeigniter') && $request->input('bridge_verified')) {
            return $this->handleCodeIgniterBridge($request);
        }

        $validator = Validator::make($request->all(), [
            'usuario_mail' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Usar los campos personalizados de tu tabla
        $credentials = [
            'usuario_mail' => $request->usuario_mail,
            'password' => $request->password
        ];

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $token_custom = Str::random(50); // Generar un token personalizado
        User::where('usuario_mail', $request->usuario_mail)
            ->update(['usuario_apikey' => $token_custom]); // Actualizar el último inicio de sesión

        return $this->respondWithToken($token,$token_custom);
    }

    /**
     * Handle authentication from CodeIgniter Bridge
     */
    private function handleCodeIgniterBridge(Request $request)
    {
        $ci_user_data = $request->input('ci_user_data');

        if (!$ci_user_data || !isset($ci_user_data['usuario_mail'])) {
            return response()->json(['error' => 'Datos de usuario inválidos desde CodeIgniter'], 400);
        }

        // Buscar o crear el usuario basado en los datos de CodeIgniter
        $user = User::where('usuario_mail', $ci_user_data['usuario_mail'])->first();

        if (!$user) {
            // Si el usuario no existe, crearlo con los datos de CodeIgniter
            $user = User::create([
                'usuario_mail' => $ci_user_data['usuario_mail'],
                'usuario_nombre' => $ci_user_data['usuario_nombre'] ?? '',
                'usuario_clave' => Hash::make('bridge_temp_' . Str::random(32)), // Password temporal
                'ci_user_id' => $ci_user_data['usuario_id'] ?? null,
                'licencia_id' => $ci_user_data['cliente_id'] ?? null,
                'licencia_base' => $ci_user_data['licencia'] ?? null
            ]);
        } else {
            // Actualizar información del usuario desde CodeIgniter
            $user->update([
                'usuario_nombre' => $ci_user_data['usuario_nombre'] ?? $user->usuario_nombre,
                'ci_user_id' => $ci_user_data['usuario_id'] ?? $user->ci_user_id,
                'licencia_id' => $ci_user_data['cliente_id'] ?? $user->licencia_id,
                'licencia_base' => $ci_user_data['licencia'] ?? $user->licencia_base
            ]);
        }

        // Generar token JWT para este usuario
        $token = JWTAuth::fromUser($user);
        $token_custom = Str::random(50);

        // Actualizar el token personalizado
        $user->update(['usuario_apikey' => $token_custom]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'token' => $token, // Para compatibilidad con CodeIgniter
            'token_custom' => $token_custom,
            'expires_at' => now()->addMinutes(config('jwt.ttl', 60))->toISOString(),
            'user' => $user->makeHidden(['usuario_clave', 'usuario_password', 'password', 'usuario_apikey']),
            'bridge_source' => 'codeigniter'
        ]);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'usuario_mail' => 'required|string|email|max:100|unique:usuario,usuario_mail',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'usuario_mail' => $request->usuario_mail,
            'usuario_clave' => $request->password // El mutator se encargará del hash
        ]);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user
        ], 201);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expirado'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token ausente'], 401);
        }

        return response()->json(compact('user'));
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada exitosamente']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Error al cerrar sesión'], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return $this->respondWithToken($token);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expirado'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Error al refrescar token'], 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token,$token_custom)
    {

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'sso_token' => $token_custom,
            'user' => JWTAuth::user()->makeHidden(['usuario_clave', 'usuario_password','password','usuario_apikey'])
        ]);
    }
}
