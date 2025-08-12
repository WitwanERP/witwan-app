<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Cliente;
use App\Models\Pedido;

class AssistantController extends Controller
{
    public function interpret(Request $request)
    {
        $userInput = $request->input('query');

        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sos un asistente que ayuda a interpretar comandos para un sistema de gestión de pedidos. Siempre respondé en JSON con la acción a ejecutar.'
                ],
                [
                    'role' => 'user',
                    'content' => $userInput,
                ],
            ],
            'temperature' => 0.2,
        ]);

        $content = $response->choices[0]->message['content'];
        $parsed = json_decode($content, true);

        if (!$parsed || !isset($parsed['action'])) {
            return response()->json(['error' => 'No se pudo interpretar la acción.'], 422);
        }

        return $this->handleAction($parsed);
    }

    private function handleAction(array $parsed)
    {
        if ($parsed['action'] === 'get_orders') {
            $cliente = Cliente::where('nombre', 'LIKE', '%' . $parsed['client_name'] . '%')->first();

            if (!$cliente) {
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }

            $pedidos = Pedido::where('cliente_id', $cliente->id)->get();

            return response()->json($pedidos);
        }

        return response()->json(['error' => 'Acción no reconocida'], 400);
    }
}
