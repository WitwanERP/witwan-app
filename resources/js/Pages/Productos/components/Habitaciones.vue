<script setup>
/**
 * Categorías/habitaciones del producto (alojamientohabitacion) dentro del mismo
 * formulario, no por AJAX aparte como en el CI (producto.php:935). La regla
 * "con tarifas no se borra ni deshabilita" la aplica HabitacionService; acá se
 * refleja con `tiene_tarifas` para no ofrecer lo que va a fallar.
 */
const props = defineProps({
  habitaciones: { type: Array, required: true },
  categorias: { type: Array, default: () => [] },
  productoId: { type: Number, default: 0 },
  errors: { type: Object, default: () => ({}) },
})

const cls = 'w-full rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-sm focus:border-blue-500 focus:bg-white focus:outline-none'
const clsN = cls + ' text-right tabular-nums'

function agregar() {
  props.habitaciones.push({ alojamientohabitacion_id: 0, fk_tarifacategoria_id: 0, nombre: '', textolibre: '', capacidad: 2, min_adultos: 1, max_adultos: 2, max_child: 0, max_adultos_child: 0, orden: props.habitaciones.length + 1, habilitar: 1, tiene_tarifas: false })
}
function quitar(i) {
  props.habitaciones.splice(i, 1)
}
</script>

<template>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Habitaciones / categorías</h3>
      <button type="button" class="btn btn-secondary btn-sm" @click="agregar">+ Agregar</button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2 text-left">Categoría</th>
            <th class="px-3 py-2 text-left">Nombre propio</th>
            <th class="px-3 py-2 text-right">Cap.</th>
            <th class="px-3 py-2 text-right">Mín. ad.</th>
            <th class="px-3 py-2 text-right">Máx. ad.</th>
            <th class="px-3 py-2 text-right">Máx. men.</th>
            <th class="px-3 py-2 text-right">Ad. c/men.</th>
            <th class="px-3 py-2 text-right">Orden</th>
            <th class="px-3 py-2 text-center">Hab.</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(h, i) in habitaciones" :key="i" :class="errors[`habitaciones.${i}.fk_tarifacategoria_id`] || errors[`habitaciones.${i}.habilitar`] ? 'bg-red-50' : ''">
            <td class="px-3 py-1.5" style="min-width: 180px">
              <select v-model.number="h.fk_tarifacategoria_id" :class="cls" :disabled="Number(h.alojamientohabitacion_id) > 0">
                <option :value="0">Seleccione…</option>
                <option v-for="c in categorias" :key="c.value" :value="c.value">{{ c.label }}</option>
              </select>
              <p v-if="errors[`habitaciones.${i}.fk_tarifacategoria_id`]" class="text-xs text-red-600">{{ errors[`habitaciones.${i}.fk_tarifacategoria_id`] }}</p>
            </td>
            <td class="px-3 py-1.5" style="min-width: 160px"><input v-model="h.nombre" type="text" :class="cls" placeholder="(usa el de la categoría)" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.capacidad" type="number" min="0" max="99" :class="clsN" style="width: 4.5rem" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.min_adultos" type="number" min="0" max="99" :class="clsN" style="width: 4.5rem" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.max_adultos" type="number" min="0" max="99" :class="clsN" style="width: 4.5rem" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.max_child" type="number" min="0" max="9" :class="clsN" style="width: 4.5rem" :title="'Genera las columnas de menores en la grilla de tarifas'" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.max_adultos_child" type="number" min="0" max="99" :class="clsN" style="width: 4.5rem" /></td>
            <td class="px-3 py-1.5"><input v-model.number="h.orden" type="number" :class="clsN" style="width: 4rem" /></td>
            <td class="px-3 py-1.5 text-center">
              <input v-model="h.habilitar" type="checkbox" :true-value="1" :false-value="0" :disabled="h.tiene_tarifas" :title="h.tiene_tarifas ? 'Tiene tarifas cargadas: no se puede deshabilitar' : ''" />
              <p v-if="errors[`habitaciones.${i}.habilitar`]" class="text-xs text-red-600">{{ errors[`habitaciones.${i}.habilitar`] }}</p>
            </td>
            <td class="whitespace-nowrap px-3 py-1.5 text-right">
              <a v-if="productoId && Number(h.alojamientohabitacion_id) > 0" :href="`/app/productos/${productoId}/cupos/${h.fk_tarifacategoria_id}`" class="text-xs text-blue-600 hover:underline">Cupos</a>
              <button v-if="!h.tiene_tarifas" type="button" class="ml-3 text-xs text-red-600 hover:underline" @click="quitar(i)">Quitar</button>
              <span v-else class="ml-3 text-xs text-gray-400" title="Tiene tarifas cargadas">con tarifas</span>
            </td>
          </tr>
          <tr v-if="habitaciones.length === 0"><td colspan="10" class="px-3 py-6 text-center text-gray-500">Sin habitaciones. Sin categorías, las tarifas se cargan en una fila "General".</td></tr>
        </tbody>
      </table>
    </div>
    <p v-if="errors.habitaciones" class="px-4 py-2 text-sm text-red-600">{{ errors.habitaciones }}</p>
  </div>
</template>
