<script setup>
import { ref } from 'vue'
import FormBusqueda from './FormBusqueda.vue'
import GrillaResultados from './GrillaResultados.vue'
import PanelCrossSelling from './PanelCrossSelling.vue'
import { useGenerador } from './useGenerador'

/**
 * Paso 2: búsqueda por tipo de producto. Cada tipo tiene su formulario propio
 * (hotel por noches y habitaciones, traslado por origen/destino, excursión por
 * día…), aunque todo termine como una fila de `servicio`. Las pestañas las
 * decide el servidor según qué tipos tienen productos habilitados en el área.
 */
const g = useGenerador()
const { props, estado } = g
const aviso = ref('')
let t = null

function servicioManual() {
  g.agregarLinea({ _abierto: true })
  g.irA('carrito')
}
function agregado(lineas) {
  aviso.value = `${lineas.length} servicio${lineas.length !== 1 ? 's' : ''} agregado${lineas.length !== 1 ? 's' : ''} al carrito (${estado.carrito.length} en total).`
  clearTimeout(t)
  t = setTimeout(() => (aviso.value = ''), 5000)
}
</script>

<template>
  <div class="space-y-4">
    <section class="card">
      <div class="card-header !pb-0 !pt-2 flex-col items-stretch gap-2">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <nav v-if="props.pestanas.length" class="flex flex-wrap gap-1 -mb-px">
            <button
              v-for="p in props.pestanas"
              :key="p.tipo"
              type="button"
              class="px-3 py-2 text-sm rounded-t-md border border-b-0"
              :class="estado.busqueda.tipo === p.tipo ? 'bg-white border-gray-300 text-blue-700 font-semibold' : 'bg-gray-100 border-transparent text-gray-600 hover:text-gray-900'"
              @click="g.cambiarTipo(p.tipo)"
            >{{ p.nombre }}</button>
          </nav>
          <span v-else class="text-sm text-gray-500 py-2">No hay tipos de producto con tarifas cargadas para {{ props.area }}.</span>
          <button type="button" class="text-xs text-blue-600 hover:underline pb-2" @click="servicioManual">+ Cargar un servicio manual (sin tarifa)</button>
        </div>
      </div>
      <div v-if="g.pestana.value" class="card-body"><FormBusqueda /></div>
    </section>

    <div v-if="aviso" class="rounded-md border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 flex items-center justify-between">
      <span>{{ aviso }}</span>
      <button type="button" class="text-xs text-green-900 underline" @click="g.irA('carrito')">Ir al carrito</button>
    </div>

    <PanelCrossSelling @agregado="agregado" />

    <GrillaResultados v-if="g.pestana.value" @agregado="agregado" />

    <div class="flex items-center justify-between">
      <button type="button" class="btn btn-secondary" @click="g.irA('contexto')">← Cliente</button>
      <button type="button" class="btn btn-primary" @click="g.irA('carrito')">Carrito ({{ estado.carrito.length }}) →</button>
    </div>
  </div>
</template>
