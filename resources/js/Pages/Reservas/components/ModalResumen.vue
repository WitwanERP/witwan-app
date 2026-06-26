<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  id: { type: Number, required: true },
  baseUrl: { type: String, required: true },
})
const emit = defineEmits(['cerrar'])

const cargando = ref(true)
const data = ref(null)
const error = ref(false)

onMounted(async () => {
  try {
    const res = await fetch(`${props.baseUrl}/resumen/${props.id}`, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error('error')
    data.value = await res.json()
  } catch (e) {
    error.value = true
  } finally {
    cargando.value = false
  }
})

function num(v) {
  return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="emit('cerrar')">
    <div class="w-full max-w-2xl max-h-[85vh] overflow-auto rounded-lg bg-white shadow-xl">
      <div class="card-header sticky top-0">
        <h3 class="card-title">Resumen de reserva #{{ id }}</h3>
        <button type="button" class="text-gray-500 hover:text-red-600" @click="emit('cerrar')">✕</button>
      </div>
      <div class="card-body">
        <div v-if="cargando" class="py-8 text-center text-gray-500">Cargando…</div>
        <div v-else-if="error" class="py-8 text-center text-red-600">No se pudo cargar el resumen.</div>
        <div v-else-if="data" class="space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">Código:</span> <strong>{{ data.reserva.codigo }}</strong></div>
            <div><span class="text-gray-500">Estado:</span> {{ data.reserva.status }}</div>
            <div><span class="text-gray-500">Cliente:</span> {{ data.reserva.cliente_nombre }}</div>
            <div><span class="text-gray-500">Titular:</span> {{ data.reserva.titular }}</div>
            <div><span class="text-gray-500">Total:</span> {{ data.reserva.moneda }} {{ num(data.reserva.total) }}</div>
            <div><span class="text-gray-500">Saldo:</span> {{ num(data.reserva.saldo) }}</div>
          </div>

          <div>
            <h4 class="mb-1 text-sm font-semibold text-gray-700">Servicios</h4>
            <ul class="text-xs text-gray-600">
              <li v-for="(l, i) in data.reserva.serviciostxt" :key="i">{{ l }}</li>
            </ul>
          </div>

          <div v-if="data.historial && data.historial.length">
            <h4 class="mb-1 text-sm font-semibold text-gray-700">Historial</h4>
            <ul class="max-h-48 overflow-auto text-xs text-gray-600">
              <li v-for="(h, i) in data.historial" :key="i" class="border-b border-gray-100 py-1">
                {{ h.historial_date }} · {{ h.historial_campo }}: {{ h.historial_valor }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
