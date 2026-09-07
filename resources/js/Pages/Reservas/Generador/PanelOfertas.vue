<script setup>
import { computed } from 'vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import { useGenerador } from './useGenerador'

/**
 * Ofertas y comparativas sobre el producto que el vendedor tiene abierto:
 *  - alternativas más baratas de la misma categoría en la grilla (sin ir al servidor),
 *  - la misma estadía corrida ±3 días,
 *  - promociones vigentes en el destino y destacados,
 *  - qué pagó este cliente por el mismo producto o por el mismo tipo en la ciudad.
 */
const props = defineProps({ fila: { type: Object, required: true }, total: { type: Number, default: 0 } })
const g = useGenerador()
const { estado } = g
const alternativas = computed(() => g.alternativas(props.fila))
const o = computed(() => (estado.ofertas.producto_id === props.fila.producto_id ? estado.ofertas : null))
const datos = computed(() => o.value?.datos || null)
const pedir = () => g.pedirOfertas(props.fila, props.total)
const signo = (n) => (n > 0 ? '+' : '') + formatearImporte(n)
const dispCls = (d) => ({ CI: 'badge-success', RQ: 'badge-warning', SO: 'badge-danger' }[d] || 'badge-gray')
const masBarataFecha = computed(() => {
  const f = (datos.value?.fechas_cercanas || []).filter((x) => x.total !== null && x.total > 0)
  if (!f.length) return null
  return f.reduce((m, x) => (x.total < m.total ? x : m), f[0])
})
</script>

<template>
  <div class="rounded-md border border-amber-200 bg-amber-50/60 p-3 space-y-3">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h4 class="text-sm font-semibold text-amber-900">Ofertas y comparativas</h4>
      <button v-if="!datos" type="button" class="btn btn-secondary btn-sm" :disabled="o?.corriendo" @click="pedir">{{ o?.corriendo ? 'Consultando…' : 'Ver fechas cercanas, promos e historial' }}</button>
      <button v-else type="button" class="text-xs text-amber-900 underline" @click="pedir">Actualizar</button>
    </div>
    <p v-if="o?.error" class="text-sm text-red-700">{{ o.error }}</p>

    <div v-if="alternativas.length">
      <div class="text-xs font-semibold text-gray-700 mb-1">Más baratos en esta búsqueda{{ fila.estrellas ? ` (${Math.floor(fila.estrellas)}★)` : '' }}</div>
      <ul class="text-sm divide-y divide-amber-100">
        <li v-for="a in alternativas" :key="a.producto_id" class="py-1 flex items-center justify-between gap-2">
          <span><b>{{ a.nombre }}</b> <span class="text-xs text-gray-500">{{ a.proveedor.nombre }}</span> <span class="badge ml-1" :class="dispCls(a.disponibilidad)">{{ a.disponibilidad }}</span></span>
          <span class="tabular-nums whitespace-nowrap">{{ formatearImporte(a.mejor_total) }} <span class="text-xs text-green-700">({{ signo(a.mejor_total - fila.mejor_total) }})</span>
            <button type="button" class="text-xs text-blue-600 hover:underline ml-2" @click="g.seleccionar(a)">ver</button></span>
        </li>
      </ul>
    </div>
    <p v-else class="text-xs text-gray-500">No hay alternativas más baratas de la misma categoría en esta búsqueda.</p>

    <template v-if="datos">
      <div>
        <div class="text-xs font-semibold text-gray-700 mb-1">Misma estadía en fechas cercanas <span v-if="masBarataFecha && masBarataFecha.total < total" class="text-green-700 font-normal">· la más barata: {{ formatearFecha(masBarataFecha.from) }} ({{ signo(masBarataFecha.total - total) }})</span></div>
        <div v-if="!datos.fechas_cercanas.length" class="text-xs text-gray-500">Sin fechas alternativas dentro del mínimo permitido.</div>
        <div v-else class="flex flex-wrap gap-2">
          <div v-for="f in datos.fechas_cercanas" :key="f.delta" class="rounded-md border px-2 py-1 text-xs min-w-[120px]" :class="f.total === null ? 'border-gray-200 bg-white text-gray-400' : f.total < total ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'">
            <div class="font-medium text-gray-800">{{ f.delta > 0 ? '+' : '' }}{{ f.delta }} día{{ Math.abs(f.delta) !== 1 ? 's' : '' }} · {{ formatearFecha(f.from) }}<span v-if="f.to && f.to !== f.from"> → {{ formatearFecha(f.to) }}</span></div>
            <div v-if="f.total === null">sin tarifa</div>
            <div v-else class="tabular-nums">{{ formatearImporte(f.total) }} {{ f.moneda }} <span v-if="f.diferencia !== null" :class="f.diferencia < 0 ? 'text-green-700' : f.diferencia > 0 ? 'text-red-600' : 'text-gray-500'">({{ signo(f.diferencia) }})</span> <span class="badge" :class="dispCls(f.disponibilidad)">{{ f.disponibilidad }}</span></div>
          </div>
        </div>
      </div>

      <div>
        <div class="text-xs font-semibold text-gray-700 mb-1">Promociones vigentes en {{ fila.ciudad.nombre || 'el destino' }}</div>
        <div v-if="!datos.promociones.length" class="text-xs text-gray-500">Sin promociones cargadas para estas fechas.</div>
        <ul v-else class="text-sm divide-y divide-amber-100">
          <li v-for="(p, i) in datos.promociones" :key="i" class="py-1 flex items-center justify-between gap-2">
            <span>
              <span class="badge mr-1" :class="p.origen === 'destacado' ? 'badge-info' : 'badge-success'">{{ p.origen === 'destacado' ? 'Destacado' : p.promo ? 'Promo ' + p.promo : 'Promo' }}</span>
              <b>{{ p.nombre }}</b><span v-if="p.nota" class="text-gray-600"> · {{ p.nota }}</span>
              <span v-if="p.vigencia_ini" class="text-xs text-gray-500"> ({{ formatearFecha(p.vigencia_ini) }} → {{ formatearFecha(p.vigencia_fin) }}<span v-if="p.vencimiento">, vence {{ formatearFecha(p.vencimiento) }}</span>)</span>
            </span>
            <span v-if="p.producto_id === fila.producto_id" class="text-xs text-green-700 whitespace-nowrap">este producto</span>
          </li>
        </ul>
      </div>

      <div v-if="datos.historial">
        <div class="text-xs font-semibold text-gray-700 mb-1">Lo que pagó {{ estado.contexto.cliente_label || 'el cliente' }} (12 meses)</div>
        <div v-if="!datos.historial.mismo_producto.length && !datos.historial.mismo_tipo_ciudad" class="text-xs text-gray-500">Sin compras previas comparables.</div>
        <template v-else>
          <ul v-if="datos.historial.mismo_producto.length" class="text-sm divide-y divide-amber-100 mb-2">
            <li v-for="h in datos.historial.mismo_producto" :key="h.codigo" class="py-1 flex items-center justify-between gap-2">
              <span>{{ h.codigo }} · {{ formatearFecha(h.vigencia_ini) }}<span v-if="h.noches"> · {{ h.noches }} noche{{ h.noches !== 1 ? 's' : '' }}</span> · {{ h.pax }} pax</span>
              <span class="tabular-nums whitespace-nowrap">{{ formatearImporte(h.total) }} {{ h.moneda }}<span v-if="h.por_pax_noche" class="text-xs text-gray-500"> ({{ formatearImporte(h.por_pax_noche) }} p/pax/noche)</span></span>
            </li>
          </ul>
          <div v-if="datos.historial.mismo_tipo_ciudad" class="text-sm">
            Promedio en {{ fila.ciudad.nombre }} ({{ datos.historial.mismo_tipo_ciudad.n }} servicio{{ datos.historial.mismo_tipo_ciudad.n !== 1 ? 's' : '' }}):
            <b class="tabular-nums">{{ formatearImporte(datos.historial.mismo_tipo_ciudad.promedio) }} {{ datos.historial.mismo_tipo_ciudad.moneda }}</b>
            <span class="text-xs text-gray-500">(mín {{ formatearImporte(datos.historial.mismo_tipo_ciudad.min) }} · máx {{ formatearImporte(datos.historial.mismo_tipo_ciudad.max) }}<span v-if="datos.historial.mismo_tipo_ciudad.promedio_pax_noche"> · {{ formatearImporte(datos.historial.mismo_tipo_ciudad.promedio_pax_noche) }} p/pax/noche</span>)</span>
            <span v-if="datos.historial.mismo_tipo_ciudad.diferencia !== null" class="ml-2 text-xs" :class="datos.historial.mismo_tipo_ciudad.diferencia > 0 ? 'text-red-600' : 'text-green-700'">Este total: {{ signo(datos.historial.mismo_tipo_ciudad.diferencia) }} respecto del promedio.</span>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
