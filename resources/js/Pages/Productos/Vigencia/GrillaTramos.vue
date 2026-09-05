<script setup>
import { formatearImporte } from '@/lib/formato'

/**
 * Grilla tramos de pax × tipos de pax (EXC, TRN, GUI, CRU, ASV, TRE, AUT).
 * Los tramos se agregan y quitan acá; las columnas las fija el tipo de
 * producto (config productos.tipos.*.tipos_pax).
 */
const props = defineProps({
  tramos: { type: Array, required: true },
  tiposPax: { type: Array, required: true },
  tarifarios: { type: Array, default: () => [] },
  cargamanual: { type: Boolean, default: false },
  /** Preview del server: { 'i_pax': { tid: { venta } } } */
  preview: { type: Object, default: () => ({}) },
  monedaCosto: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['cambio'])

const cls =
  'w-24 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-right text-sm tabular-nums focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100 disabled:text-gray-500'
const clsPax = 'w-16 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-right text-sm tabular-nums focus:border-blue-500 focus:bg-white focus:outline-none'

function agregar() {
  const ultimo = props.tramos[props.tramos.length - 1]
  const min = ultimo ? Number(ultimo.max) + 1 : 1
  const costos = {}
  const venta = {}
  for (const p of props.tiposPax) costos[p] = null
  for (const t of props.tarifarios) {
    venta[t.id] = {}
    for (const p of props.tiposPax) venta[t.id][p] = null
  }
  props.tramos.push({ min, max: min, costos, venta })
  emit('cambio')
}

function quitar(i) {
  props.tramos.splice(i, 1)
  emit('cambio')
}

const sugerido = (i, pax, tid) => props.preview[`${i}_${pax}`]?.[tid]?.venta

function usarSugerido(i, pax, tid) {
  const s = sugerido(i, pax, tid)
  if (s === undefined || s === null) return
  props.tramos[i].venta[tid][pax] = s
  emit('cambio')
}
</script>

<template>
  <div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-xs uppercase tracking-wide text-gray-500">
            <th class="py-2 pr-2 text-left font-semibold">Pax desde</th>
            <th class="py-2 pr-3 text-left font-semibold">Pax hasta</th>
            <th class="py-2 pr-3 text-left font-semibold">Concepto</th>
            <th v-for="p in tiposPax" :key="p" class="px-1 py-2 text-right font-semibold">{{ p }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <template v-for="(tramo, i) in tramos" :key="i">
            <tr :class="errors[`tramos.${i}`] ? 'bg-red-50' : ''">
              <td class="py-1.5 pr-2">
                <input v-model.number="tramo.min" type="number" min="0" :class="clsPax" :disabled="disabled" @change="emit('cambio')" />
              </td>
              <td class="py-1.5 pr-3">
                <input v-model.number="tramo.max" type="number" min="0" :class="clsPax" :disabled="disabled" @change="emit('cambio')" />
                <p v-if="errors[`tramos.${i}`]" class="text-xs text-red-600">{{ errors[`tramos.${i}`] }}</p>
              </td>
              <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">Costo <span class="text-gray-400">{{ monedaCosto }}</span></td>
              <td v-for="p in tiposPax" :key="p" class="px-1 py-1.5 text-right">
                <input v-model="tramo.costos[p]" type="number" step="0.01" min="0" :class="cls" :disabled="disabled" @change="emit('cambio')" />
              </td>
              <td class="py-1.5 pl-2 text-right">
                <button type="button" class="text-sm text-red-600 hover:underline disabled:opacity-40" :disabled="disabled" @click="quitar(i)">Quitar</button>
              </td>
            </tr>
            <tr v-for="t in tarifarios" :key="`${i}_${t.id}`" :class="cargamanual ? '' : 'text-gray-500'">
              <td colspan="2"></td>
              <td class="whitespace-nowrap py-1.5 pr-3">Venta {{ t.nombre }} <span class="text-gray-400">{{ t.moneda }}</span></td>
              <td v-for="p in tiposPax" :key="p" class="px-1 py-1.5 text-right tabular-nums">
                <template v-if="cargamanual">
                  <input v-model="tramo.venta[t.id][p]" type="number" step="0.01" min="0" :class="cls" :disabled="disabled" @change="emit('cambio')" />
                  <button
                    v-if="sugerido(i, p, t.id) !== undefined && sugerido(i, p, t.id) !== null"
                    type="button"
                    class="block w-24 text-right text-[11px] text-blue-600 hover:underline"
                    @click="usarSugerido(i, p, t.id)"
                  >
                    ≈ {{ formatearImporte(sugerido(i, p, t.id), 0) }}
                  </button>
                </template>
                <template v-else>
                  <span v-if="sugerido(i, p, t.id) !== undefined && sugerido(i, p, t.id) !== null">{{ formatearImporte(sugerido(i, p, t.id)) }}</span>
                  <span v-else class="text-gray-300">—</span>
                </template>
              </td>
              <td></td>
            </tr>
          </template>
          <tr v-if="tramos.length === 0">
            <td :colspan="tiposPax.length + 4" class="py-6 text-center text-gray-500">Sin tramos. Agregue el primero para cargar costos.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <button type="button" class="btn btn-secondary btn-sm mt-3" :disabled="disabled" @click="agregar">+ Agregar tramo</button>
  </div>
</template>
