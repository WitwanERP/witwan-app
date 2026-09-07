<script setup>
import { formatearImporte } from '@/lib/formato'
import { useGenerador } from './useGenerador'

/**
 * "Completar el viaje": tras agregar un servicio, complementarios del destino
 * (traslados, excursiones, asistencia…) cotizados para las mismas fechas y pax.
 * Primero lo que históricamente se vendió junto con ese producto o en esa ciudad.
 */
const emit = defineEmits(['agregado'])
const g = useGenerador()
const { estado } = g
const cross = estado.cross
const dispCls = (d) => ({ CI: 'badge-success', RQ: 'badge-warning', SO: 'badge-danger' }[d] || 'badge-gray')
function agregar(item) {
  const lineas = g.agregarDesdeResultado(item, item.habitaciones.map((h) => h.mejor), {})
  emit('agregado', lineas)
}
const yaEnCarrito = (item) => estado.carrito.some((l) => l.fk_producto_id === item.producto_id && l.vigencia_ini === item.vigencia_ini)
</script>

<template>
  <section v-if="cross.corriendo || cross.grupos.length || cross.error" class="card border-green-200">
    <div class="card-header bg-green-50">
      <h2 class="card-title text-green-900">Completar el viaje <span v-if="cross.base" class="text-green-700 font-normal text-xs">· para {{ cross.base }}</span></h2>
      <button type="button" class="text-xs text-green-800 hover:underline" @click="g.cerrarCross()">Cerrar</button>
    </div>
    <div v-if="cross.corriendo" class="card-body text-sm text-gray-500">Buscando traslados, excursiones y asistencia para esas fechas…</div>
    <div v-else-if="cross.error" class="card-body text-sm text-red-700">{{ cross.error }}</div>
    <div v-else class="card-body grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div v-for="gr in cross.grupos" :key="gr.tipo">
        <h3 class="text-sm font-semibold text-gray-800 mb-1">{{ gr.nombre }}</h3>
        <ul class="divide-y divide-gray-100 rounded-md border border-gray-200">
          <li v-for="it in gr.items" :key="it.producto_id" class="px-3 py-2 flex items-center justify-between gap-3 text-sm">
            <div class="min-w-0">
              <div class="font-medium text-gray-900 truncate">{{ it.nombre }} <span v-if="it.coocurrencia" class="badge badge-info ml-1" :title="`Se vendió junto en ${it.coocurrencia} reserva(s)`">×{{ it.coocurrencia }} juntos</span><span v-if="it.promo" class="badge badge-success ml-1">Promo</span></div>
              <div class="text-xs text-gray-500 truncate">{{ it.proveedor.nombre }}<span v-if="it.habitaciones[0]?.opciones[0]?.nombre && it.habitaciones[0].opciones[0].nombre !== '0'"> · {{ it.habitaciones[0].opciones[0].nombre }}</span> <span class="badge" :class="dispCls(it.disponibilidad)">{{ it.disponibilidad }}</span></div>
            </div>
            <div class="text-right whitespace-nowrap">
              <div class="font-semibold tabular-nums">{{ formatearImporte(it.mejor_total) }} <span class="text-xs text-gray-500">{{ it.moneda }}</span></div>
              <button v-if="!yaEnCarrito(it)" type="button" class="text-xs text-blue-600 hover:underline" :disabled="it.disponibilidad === 'SO'" @click="agregar(it)">+ Agregar</button>
              <span v-else class="text-xs text-green-700">en el carrito</span>
            </div>
          </li>
        </ul>
      </div>
      <p v-if="!cross.grupos.length" class="text-sm text-gray-500">No hay complementarios con tarifa en ese destino para esas fechas.</p>
    </div>
  </section>
</template>
