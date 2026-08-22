<script setup>
import { computed } from 'vue'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  conceptos: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-right text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

const total = computed(() =>
  props.conceptos.reduce((acc, c) => acc + (Number(props.modelValue[c.clave]) || 0), 0)
)

function actualizar(clave, valor) {
  emit('update:modelValue', { ...props.modelValue, [clave]: valor })
}
</script>

<template>
  <div v-if="conceptos.length" class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Conceptos adicionales</h3>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="c in conceptos" :key="c.clave">
          <label class="form-label">{{ c.label }}</label>
          <input
            :value="modelValue[c.clave] ?? ''"
            type="number"
            step="0.01"
            :class="fieldBase"
            :disabled="disabled"
            @input="actualizar(c.clave, $event.target.value)"
          />
        </div>
      </div>
      <!-- Regla del legacy (scriptfactura3ro.js:31-38): la suma de los conceptos
           PISA el campo exento, no se suma encima. -->
      <p class="form-hint mt-3">
        La suma de estos conceptos ({{ formatearImporte(total) }}) reemplaza el importe exento.
      </p>
    </div>
  </div>
</template>
