<script setup>
import { computed } from 'vue'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  config: { type: Object, required: true },
  sumasValidas: { type: Array, default: () => [100] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-right text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

const suma = computed(() =>
  Object.values(props.modelValue).reduce((acc, v) => acc + (Number(v) || 0), 0)
)

// El servidor valida lo mismo: acá es sólo para avisar antes de enviar.
const valida = computed(() => suma.value === 0 || props.sumasValidas.includes(Math.round(suma.value * 100) / 100))

function actualizar(id, valor) {
  emit('update:modelValue', { ...props.modelValue, [id]: valor })
}
</script>

<template>
  <div v-if="config.visible" class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">{{ config.titulo }}</h3>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <div v-for="(nombre, id) in config.areas" :key="id">
          <label class="form-label">{{ nombre }}</label>
          <input
            :value="modelValue[id] ?? ''"
            type="number"
            step="0.01"
            min="0"
            max="200"
            :class="fieldBase"
            :disabled="disabled"
            @input="actualizar(id, $event.target.value)"
          />
        </div>
      </div>

      <p class="mt-3 text-sm" :class="valida ? 'text-gray-500' : 'text-red-600'">
        Suma: {{ formatearImporte(suma) }}%
        <span v-if="!valida"> — debe sumar {{ sumasValidas.join(' o ') }}%.</span>
      </p>
    </div>
  </div>
</template>
