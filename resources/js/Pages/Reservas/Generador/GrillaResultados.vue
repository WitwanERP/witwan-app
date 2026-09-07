<script setup>
import { computed } from 'vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import DetalleResultado from './DetalleResultado.vue'
import { useGenerador } from './useGenerador'

/**
 * Grilla comparativa de la búsqueda: ordenada por mejor total (el servidor ya
 * ordena, sold out al final), "mejor precio" marcado por categoría de estrellas,
 * badges de disponibilidad y promo, y detalle expandible por producto.
 */
const emit = defineEmits(['agregado'])
const g = useGenerador()
const { estado } = g
const filas = computed(() => estado.busqueda.resultados)
const mejorPorEstrellas = computed(() => {
  const m = {}
  for (const f of filas.value) {
    if (f.disponibilidad === 'SO' || !(f.mejor_total > 0)) continue
    const k = f.estrellas ?? '-'
    if (!m[k] || f.mejor_total < m[k].mejor_total) m[k] = f
  }
  return new Set(Object.values(m).map((f) => f.producto_id))
})
const dispCls = (d) => ({ CI: 'badge-success', RQ: 'badge-warning', SO: 'badge-danger' }[d] || 'badge-gray')
const dispTxt = (d) => ({ CI: 'Disponible', RQ: 'A confirmar', SO: 'Sold out' }[d] || d)
const estrellas = (n) => (n ? '★'.repeat(Math.floor(n)) : '')
const abierta = (f) => estado.seleccion?.producto_id === f.producto_id
const pax = (f) => f.habitaciones.reduce((a, h) => a + h.pax.adultos + h.pax.menores + h.pax.infante + h.pax.juniors, 0)
function agregarRapido(f) {
  const lineas = g.agregarDesdeResultado(f, f.habitaciones.map((h) => h.mejor), {})
  emit('agregado', lineas)
}
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">
        Resultados <span class="text-gray-400 font-normal">({{ filas.length }})</span>
        <span v-if="estado.busqueda.candidatos" class="text-xs text-gray-400 font-normal">· {{ estado.busqueda.candidatos }} candidatos · {{ estado.busqueda.ms }} ms</span>
      </h2>
      <span v-if="filas.length" class="text-xs text-gray-500">{{ formatearFecha(filas[0].vigencia_ini) }}<span v-if="filas[0].vigencia_fin && filas[0].vigencia_fin !== filas[0].vigencia_ini"> → {{ formatearFecha(filas[0].vigencia_fin) }}</span> · {{ pax(filas[0]) }} pax</span>
    </div>
    <div v-if="estado.busqueda.truncado" class="px-4 py-2 text-xs text-amber-800 bg-amber-50 border-b border-amber-200">La búsqueda se cortó por cantidad de productos o tiempo: acotá por nombre, estrellas o producto para ver el resto.</div>
    <div v-if="estado.busqueda.corriendo" class="card-body text-sm text-gray-500">Cotizando productos…</div>
    <div v-else-if="estado.busqueda.error" class="card-body text-sm text-red-700">{{ estado.busqueda.error }}</div>
    <div v-else-if="!filas.length && estado.busqueda.hash" class="card-body text-sm text-gray-500">Sin productos con tarifa para esa búsqueda. Probá otras fechas o cargá un servicio manual.</div>
    <div v-else-if="!filas.length" class="card-body text-sm text-gray-400">Completá el formulario y buscá.</div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 bg-gray-50">
            <th class="px-4 py-2">Producto</th><th class="px-2 py-2">Ciudad</th><th class="px-2 py-2">Disponibilidad</th><th class="px-2 py-2">Opciones</th>
            <th class="px-2 py-2 text-right">Mejor total</th><th class="px-2 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="f in filas" :key="f.producto_id">
            <tr class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer" :class="abierta(f) ? 'bg-blue-50' : ''" @click="g.seleccionar(f)">
              <td class="px-4 py-2">
                <div class="font-medium text-gray-900 flex items-center gap-2 flex-wrap">
                  {{ f.nombre }}
                  <span v-if="f.estrellas" class="text-amber-500 text-xs">{{ estrellas(f.estrellas) }}</span>
                  <span v-if="mejorPorEstrellas.has(f.producto_id)" class="badge badge-info">Mejor precio{{ f.estrellas ? ' ' + Math.floor(f.estrellas) + '★' : '' }}</span>
                  <span v-if="f.promo" class="badge badge-success" :title="f.promo.nota">Promo{{ f.promo.texto ? ' ' + f.promo.texto : '' }}</span>
                </div>
                <div class="text-xs text-gray-500">{{ f.proveedor.nombre }}<span v-if="f.noches && !['EXC', 'GUI', 'TRN', 'TRE'].includes(f.tipo)"> · {{ f.noches }} {{ ['ASV', 'AUT', 'CRU'].includes(f.tipo) ? 'día' : 'noche' }}{{ f.noches !== 1 ? 's' : '' }}</span></div>
              </td>
              <td class="px-2 py-2 text-gray-700">{{ f.ciudad.nombre }}</td>
              <td class="px-2 py-2"><span class="badge" :class="dispCls(f.disponibilidad)">{{ dispTxt(f.disponibilidad) }}</span></td>
              <td class="px-2 py-2 text-xs text-gray-600">{{ f.habitaciones.map((h) => h.opciones.length).join(' + ') }}<span v-if="f.habitaciones.length > 1"> (por hab.)</span></td>
              <td class="px-2 py-2 text-right tabular-nums font-semibold whitespace-nowrap">{{ formatearImporte(f.mejor_total) }} <span class="text-xs text-gray-500">{{ f.moneda }}</span></td>
              <td class="px-2 py-2 text-right whitespace-nowrap">
                <button type="button" class="text-xs text-blue-600 hover:underline mr-3" @click.stop="g.seleccionar(f)">{{ abierta(f) ? 'Cerrar' : 'Ver opciones' }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="f.disponibilidad === 'SO'" title="Agregar con la mejor opción" @click.stop="agregarRapido(f)">+ Agregar</button>
              </td>
            </tr>
            <tr v-if="abierta(f)">
              <td colspan="6" class="p-0"><DetalleResultado :fila="f" @agregado="(l) => emit('agregado', l)" /></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
