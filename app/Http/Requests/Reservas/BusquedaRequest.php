<?php

namespace App\Http\Requests\Reservas;

use App\Services\Reservas\BusquedaProductosService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /app/reservas/{área}/nueva/buscar: parámetros comunes de búsqueda más
 * los obligatorios del tipo (config/reservas_busqueda.php → requiere) y las
 * reglas propias del buscador.
 */
class BusquedaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipos = array_keys((array) config('reservas_busqueda.tipos', []));

        return [
            'tipo' => 'required|string|in:'.implode(',', $tipos),
            'cliente_id' => 'required|integer|min:1',
            'residente' => 'nullable|in:R,N',
            'from' => 'required|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'ciudad' => 'nullable|integer|min:0',
            'origen' => 'nullable|integer|min:0',
            'destino' => 'nullable|integer|min:0',
            'nombre' => 'nullable|string|max:100',
            'producto_id' => 'nullable|integer|min:0',
            'producto_ids' => 'nullable|array|max:50',
            'producto_ids.*' => 'integer|min:1',
            'stars' => 'nullable|array|max:5',
            'stars.*' => 'integer|between:1,5',
            'habitaciones' => 'nullable|array|max:6',
            'habitaciones.*.ad' => 'required|integer|between:1,9',
            'habitaciones.*.mn' => 'nullable|array|max:5',
            'habitaciones.*.mn.*' => 'integer|between:0,17',
            'ad' => 'nullable|integer|between:0,50',
            'mn' => 'nullable|array|max:20',
            'mn.*' => 'integer|between:0,17',
            'mayores70' => 'nullable|integer|between:0,50',
        ];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            $tipo = (string) $this->input('tipo', '');
            if ($tipo === '' || $v->errors()->has('tipo')) {
                return;
            }
            $servicio = app(BusquedaProductosService::class);
            foreach ($servicio->reglas($tipo) as $campo => $regla) {
                $valor = $this->input($campo);
                if (str_contains($regla, 'required') && ($valor === null || $valor === '' || $valor === 0 || $valor === '0' || $valor === [])) {
                    $v->errors()->add($campo, "El campo {$campo} es obligatorio para {$tipo}.");
                }
            }
            foreach ($servicio->alternativas($tipo) as $grupo) {
                $alguno = false;
                foreach ($grupo as $campo) {
                    $valor = $this->input($campo);
                    if ($valor !== null && $valor !== '' && $valor !== 0 && $valor !== '0' && $valor !== []) {
                        $alguno = true;
                    }
                }
                if (! $alguno) {
                    $v->errors()->add($grupo[0], 'Indicá '.implode(' o ', $grupo).'.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date_format' => 'El campo :attribute debe tener formato aaaa-mm-dd.',
            'to.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }
}
