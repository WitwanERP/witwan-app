<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CodeIgniterBridge
{
    /**
     * Handle an incoming request from CodeIgniter
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si la petición viene desde CodeIgniter
        if ($request->has('from_codeigniter') && $request->header('X-Bridge-Key')) {

            // Verificar la clave de bridge
            $expected_key = hash('sha256', $request->input('ci_user_data.licencia', '') . '_bridge_' . date('Y-m-d'));
            $provided_key = $request->header('X-Bridge-Key');

            if (!hash_equals($expected_key, $provided_key)) {
                Log::warning('Invalid bridge key from CodeIgniter', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'expected' => substr($expected_key, 0, 10) . '...',
                    'provided' => substr($provided_key, 0, 10) . '...'
                ]);

                return response()->json(['error' => 'Unauthorized bridge request'], 401);
            }

            // Agregar información del bridge al request
            $request->merge([
                'is_bridge_request' => true,
                'bridge_verified' => true
            ]);

            Log::info('CodeIgniter bridge request verified', [
                'licencia' => $request->input('ci_user_data.licencia'),
                'user_id' => $request->input('ci_user_data.usuario_id'),
                'user_email' => $request->input('ci_user_data.usuario_mail')
            ]);
        }

        return $next($request);
    }
}