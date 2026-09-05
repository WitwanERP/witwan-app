<?php

namespace App\Http\Requests\Productos;

use App\Services\Productos\ProductoFormulario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forma del payload del formulario de producto. Las reglas base son fijas y
 * las del tipo se derivan de config/productos.php (tipo de campo → regla), así
 * que un tipo nuevo no necesita un Request nuevo.
 *
 * El legacy no validaba nada: concatenaba el POST con addslashes en el SQL
 * (hotel.php:212-216). Las reglas de negocio (modotarifa por tipo, edades
 * crecientes, ciudad obligatoria) viven en ProductoReglas.
 */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipo = ProductoFormulario::tipoPorSlug((string) $this->route('tipo')) ?? '';
        $sistema = ProductoFormulario::sistemaId((string) $this->route('sistema')) ?? 0;

        $reglas = [
            'producto_nombre' => 'required|string|max:255',
            'fk_proveedor_id' => 'required|integer|min:1',
            'fk_prestador_id' => 'nullable|integer|min:0',
            'producto_codigo' => 'nullable|string|max:50',
            'habilitar' => 'nullable|boolean',
            'aparece_tarifario' => 'nullable|boolean',
            'modotarifa' => 'nullable|string|max:2',
            'disponibilidad' => 'nullable|string|in:,CI,RQ',
            'origen' => 'nullable|integer|min:0',
            'destino' => 'nullable|integer|min:0',

            'bases' => 'nullable|array|max:9',
            'bases.*' => 'integer|min:1|max:9',
            'facilidades' => 'nullable|array',
            'facilidades.*' => 'integer|min:1',
            'destinos' => 'nullable|array',
            'destinos.*.ciudad_id' => 'required_with:destinos|integer|min:1',
            'destinos.*.tipo' => 'nullable|string|in:D,O',
            'galeria' => 'nullable|array',
            'galeria.*.productogaleria_archivo' => 'required_with:galeria|string|max:150',
            'galeria.*.orden' => 'nullable|integer',

            'habitaciones' => 'nullable|array',
            'habitaciones.*.alojamientohabitacion_id' => 'nullable|integer|min:0',
            'habitaciones.*.fk_tarifacategoria_id' => 'required_with:habitaciones|integer|min:0',
            'habitaciones.*.nombre' => 'nullable|string|max:255',
            'habitaciones.*.textolibre' => 'nullable|string|max:255',
            'habitaciones.*.capacidad' => 'nullable|integer|min:0|max:99',
            'habitaciones.*.min_adultos' => 'nullable|integer|min:0|max:99',
            'habitaciones.*.max_adultos' => 'nullable|integer|min:0|max:99',
            'habitaciones.*.max_child' => 'nullable|integer|min:0|max:9',
            'habitaciones.*.max_adultos_child' => 'nullable|integer|min:0|max:99',
            'habitaciones.*.orden' => 'nullable|integer',
            'habitaciones.*.habilitar' => 'nullable|boolean',
        ];

        if ($tipo === '' || $sistema === 0) {
            return $reglas;
        }

        foreach ((new ProductoFormulario)->campos($tipo, $sistema) as $campo) {
            if (isset($reglas[$campo['campo']])) {
                continue;
            }
            $regla = match ($campo['tipo']) {
                'text', 'url' => 'nullable|string|max:'.($campo['max'] ?? 255),
                'textarea', 'itinerario' => 'nullable|string',
                'number' => 'nullable|numeric',
                'boolean' => 'nullable|boolean',
                'select' => isset($campo['lista']) ? 'nullable|in:'.implode(',', array_keys($this->lista($campo['lista']))) : 'nullable|integer|min:0',
                'radio' => 'nullable|in:'.implode(',', array_keys($this->lista($campo['lista'] ?? []))),
                default => null,
            };
            if ($regla !== null) {
                $reglas[$campo['campo']] = $regla;
            }
        }

        return $reglas;
    }

    /** Las listas pueden ser [valor => label] o [valor, valor...]. */
    private function lista(array $lista): array
    {
        return array_is_list($lista) ? array_combine($lista, $lista) : $lista;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'fk_proveedor_id.min' => 'Debe seleccionar un proveedor.',
            'in' => 'El valor de :attribute no es válido.',
        ];
    }
}
