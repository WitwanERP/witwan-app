<script setup>
import { computed, reactive } from 'vue'
import { formatearFecha, formatearImporte } from '@/lib/formato'
import PanelOfertas from './PanelOfertas.vue'
import { inputSm, useGenerador } from './useGenerador'

/**
 * Detalle de un producto de la grilla: opciones (categoría / régimen) por
 * habitación con costo, venta, IVA, total, cupo y vencimiento, la política de
 * cancelación y, en excursiones/traslados, pick-up y drop-off. "Agregar" arma
 * una línea de carrito por habitación con la opción elegida.
 */
const props = defineProps({ fila: { type: Object, required: true } })
const emit = defineEmits(['agregado'])
const g = useGenerador()
const sel = computed(() => (g.estado.seleccion?.producto_id === props.fila.producto_id ? g.estado.seleccion : null))
const extras = reactive({ pickup: '', dropoff: '', hora_pickup: '' })
const elegir = (i, op) => {
  if (sel.value) sel.value.elegidas[i] = { categoria: op.categoria, regimen: op.regimen }
}
const esElegida = (i, op) => {
  const e = sel.value?.elegidas[i]
  return e ? e.categoria === op.categoria && e.regimen === op.regimen : false
}
const total = computed(() => (sel.value ? g.totalSeleccion(props.fila, sel.value.elegidas) : props.fila.mejor_total))
const dispCls = (d) => ({ CI: 'badge-success', RQ: 'badge-warning', SO: 'badge-danger' }[d] || 'badge-gray')
const dispTxt = (d) => ({ CI: 'Disponible', RQ: 'A confirmar', SO: 'Sold out' }[d] || d)
function agregar() {
  if (!sel.value) return
  const lineas = g.agregarDesdeResultado(props.fila, sel.value.elegidas, extras)
  emit('agregado', lineas)
}
const paxTexto = (h) => [h.pax.adultos ? `${h.pax.adultos} adulto${h.pax.adultos !== 1 ? 's' : ''}` : '', h.pax.menores ? `${h.pax.menores} menor${h.pax.menores !== 1 ? 'es' : ''}` : '', h.pax.infante ? `${h.pax.infante} infante${h.pax.infante !== 1 ? 's' : ''}` : '', h.pax.juniors ? `${h.pax.juniors} junior${h.pax.juniors !== 1 ? 's' : ''}` : ''].filter(Boolean).join(', ')
</script>

<template>
  <div v-if="sel" class="border-t border-blue-200 bg-blue-50/40 px-4 py-3 space-y-3">
    <div v-for="(h, i) in fila.habitaciones" :key="h.indice">
      <div class="text-xs font-semibold text-gray-700 mb-1">
        <span v-if="fila.habitaciones.length > 1">Habitación {{ i + 1 }} · </span>{{ paxTexto(h) }}<span v-if="h.edades.length" class="text-gray-500"> (edades {{ h.edades.join(', ') }})</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm bg-white rounded-md border border-gray-200">
          <thead>
            <tr class="text-left text-xs text-gray-500">
              <th class="px-2 py-1"></th><th class="px-2 py-1">Opción</th><th class="px-2 py-1">Régimen</th>
              <th class="px-2 py-1 text-right">Costo</th><th class="px-2 py-1 text-right">Venta</th><th class="px-2 py-1 text-right">IVA</th><th class="px-2 py-1 text-right">Imp.</th><th class="px-2 py-1 text-right">Total</th>
              <th class="px-2 py-1">Cupo</th><th class="px-2 py-1">Vence pago</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="op in h.opciones" :key="op.categoria + '-' + op.regimen" class="border-t border-gray-100 cursor-pointer hover:bg-blue-50" :class="esElegida(i, op) ? 'bg-blue-100/70' : ''" @click="elegir(i, op)">
              <td class="px-2 py-1"><input type="radio" :name="`op-${fila.producto_id}-${i}`" :checked="esElegida(i, op)" @change="elegir(i, op)" /></td>
              <td class="px-2 py-1 font-medium text-gray-800">{{ op.nombre }}<span v-if="op.textodescuento" class="badge badge-success ml-2">Promo {{ op.textodescuento }}</span><div v-if="op.nota_promocion" class="text-xs text-green-700">{{ op.nota_promocion }}</div></td>
              <td class="px-2 py-1 text-gray-600">{{ op.regimen_nombre || '—' }}</td>
              <td class="px-2 py-1 text-right tabular-nums text-gray-600">{{ formatearImporte(op.costoenmoneda) }}</td>
              <td class="px-2 py-1 text-right tabular-nums">{{ formatearImporte(op.venta) }}</td>
              <td class="px-2 py-1 text-right tabular-nums text-gray-600">{{ formatearImporte(op.iva) }}</td>
              <td class="px-2 py-1 text-right tabular-nums text-gray-600">{{ formatearImporte(op.impuestos) }}</td>
              <td class="px-2 py-1 text-right tabular-nums font-semibold">{{ formatearImporte(op.total) }} <span class="text-xs text-gray-500">{{ op.moneda }}</span></td>
              <td class="px-2 py-1"><span class="badge" :class="dispCls(op.disponibilidad)">{{ dispTxt(op.disponibilidad) }}<span v-if="op.disponibilidad === 'CI' && op.cupo > 0 && op.cupo < 10"> ({{ op.cupo }})</span></span></td>
              <td class="px-2 py-1 text-xs text-gray-600">{{ op.vencepago ? formatearFecha(op.vencepago) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="fila.pickup" class="grid grid-cols-2 md:grid-cols-6 gap-2">
      <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Pick-up</label><input v-model="extras.pickup" type="text" maxlength="200" placeholder="Hotel / dirección" :class="inputSm" /></div>
      <div><label class="block text-xs text-gray-600 mb-1">Hora</label><input v-model="extras.hora_pickup" type="time" :class="inputSm" /></div>
      <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Drop-off</label><input v-model="extras.dropoff" type="text" maxlength="200" :class="inputSm" /></div>
    </div>

    <PanelOfertas :fila="fila" :total="total" />

    <div class="flex items-start justify-between gap-4 flex-wrap">
      <p v-if="fila.politica_cancelacion" class="text-xs text-gray-600 max-w-2xl"><b>Cancelación:</b> {{ fila.politica_cancelacion }}</p>
      <p v-else class="text-xs text-gray-400">Sin política de cancelación cargada.</p>
      <div class="flex items-center gap-3">
        <div class="text-right">
          <div class="text-xs text-gray-500">Total elegido</div>
          <div class="text-lg font-bold tabular-nums">{{ formatearImporte(total) }} <span class="text-sm text-gray-500">{{ fila.moneda }}</span></div>
        </div>
        <button type="button" class="btn btn-primary" @click="agregar">Agregar al carrito</button>
      </div>
    </div>
  </div>
</template>
