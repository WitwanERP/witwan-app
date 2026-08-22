<script setup>
import { formatearImporte } from '../calculo.js'

defineProps({
  totales: { type: Object, required: true },
  moneda: { type: String, default: '' },
  // Diferencia detectada contra el recálculo del servidor.
  desvio: { type: Number, default: 0 },
  procesando: { type: Boolean, default: false },
  modo: { type: String, default: 'crear' },
})

defineEmits(['guardar'])
</script>

<template>
  <div class="sticky bottom-0 z-10 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur lg:-mx-6 lg:px-6">
    <div class="flex flex-wrap items-center gap-x-8 gap-y-2">
      <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">Neto</div>
        <div class="tabular-nums font-semibold text-gray-800">{{ formatearImporte(totales.montosiniva) }}</div>
      </div>
      <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">IVA</div>
        <div class="tabular-nums font-semibold text-gray-800">{{ formatearImporte(totales.soloiva) }}</div>
      </div>
      <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">Ret. / Perc.</div>
        <div class="tabular-nums font-semibold text-gray-800">{{ formatearImporte(totales.retper) }}</div>
      </div>
      <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
        <div class="tabular-nums text-lg font-bold text-gray-900">
          {{ moneda }} {{ formatearImporte(totales.montototal) }}
        </div>
      </div>

      <!-- Red de seguridad: si el cálculo del navegador y el del servidor no
           coinciden, gana el servidor y se avisa. -->
      <p v-if="desvio" class="text-sm text-amber-700">
        El servidor calculó una diferencia de {{ formatearImporte(desvio) }}. Se guardará el valor del servidor.
      </p>

      <button
        type="button"
        class="btn btn-primary ml-auto"
        :disabled="procesando"
        @click="$emit('guardar')"
      >
        {{ procesando ? 'Guardando…' : modo === 'editar' ? 'Guardar cambios' : 'Crear factura' }}
      </button>
    </div>
  </div>
</template>
