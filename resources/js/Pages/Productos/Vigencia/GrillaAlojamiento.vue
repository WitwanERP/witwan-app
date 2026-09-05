<script setup>
import { formatearImporte } from '@/lib/formato'

/**
 * Grilla categorías × bases. El server (GrillaTarifas) ya decidió filas y
 * columnas; acá sólo se editan celdas. Cada fila tiene sus propias bases
 * (dependen del max_child de la categoría), por eso la cabecera se repite por
 * categoría en vez de ser una sola tabla.
 */
const props = defineProps({
  filas: { type: Array, required: true },
  tarifarios: { type: Array, default: () => [] },
  /** Celdas editables: { 'cat_base': { categoria, base, costo, venta: {tid: valor} } } */
  celdas: { type: Object, required: true },
  cargamanual: { type: Boolean, default: false },
  /** Preview del server: { 'cat_base': { tid: { venta, ... } } } */
  preview: { type: Object, default: () => ({}) },
  monedaCosto: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['cambio'])

const cls =
  'w-24 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-right text-sm tabular-nums focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100 disabled:text-gray-500'

const clave = (categoria, base) => `${categoria}_${base}`

const esMenor = (base) => !/^\d+$/.test(base)

const nombreBase = (base) => {
  if (!esMenor(base)) return `${base} adulto${base === '1' ? '' : 's'}`
  return { INF: 'Infante', MN: 'Menor', M2: 'Menor 2', JNR: 'Junior' }[base.replace(/\d+$/, '')] + (base.match(/\d+$/) ? ` #${base.match(/\d+$/)[0]}` : '') || base
}

const errorCelda = (categoria, base) => {
  const i = Object.keys(props.celdas).indexOf(clave(categoria, base))
  return props.errors[`tarifas.${i}.costo`] || props.errors[`tarifas.${i}`]
}

const sugerido = (categoria, base, tid) => props.preview[clave(categoria, base)]?.[tid]?.venta

function usarSugerido(categoria, base, tid) {
  const s = sugerido(categoria, base, tid)
  if (s === undefined || s === null) return
  props.celdas[clave(categoria, base)].venta[tid] = s
  emit('cambio')
}
</script>

<template>
  <div class="space-y-5">
    <div v-for="fila in filas" :key="fila.categoria" class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-xs uppercase tracking-wide text-gray-500">
            <th class="py-2 pr-3 text-left font-semibold">
              <span class="text-sm normal-case tracking-normal text-gray-800">{{ fila.nombre }}</span>
              <span v-if="fila.categoria === 0" class="ml-2 text-gray-400">(sin habitaciones: fila general)</span>
            </th>
            <th v-for="b in fila.bases" :key="b" class="px-1 py-2 text-right font-semibold" :class="esMenor(b) ? 'text-amber-700' : ''">
              <div>{{ b }}</div>
              <div class="text-[10px] font-normal normal-case tracking-normal text-gray-400">{{ nombreBase(b) }}</div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr>
            <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">
              Costo <span class="text-gray-400">{{ monedaCosto }}</span>
            </td>
            <td v-for="b in fila.bases" :key="b" class="px-1 py-1.5 text-right">
              <input
                v-model="celdas[clave(fila.categoria, b)].costo"
                type="number"
                step="0.01"
                min="0"
                :class="[cls, errorCelda(fila.categoria, b) ? 'border-red-400' : '']"
                :disabled="disabled"
                :title="errorCelda(fila.categoria, b) || ''"
                @change="emit('cambio')"
              />
            </td>
          </tr>

          <template v-for="t in tarifarios" :key="t.id">
            <tr v-if="!cargamanual" class="text-gray-500">
              <td class="whitespace-nowrap py-1.5 pr-3">
                Venta {{ t.nombre }} <span class="text-gray-400">{{ t.moneda }}</span>
                <span v-if="t.divisor_markup" class="ml-1 text-xs text-gray-400">÷ {{ t.divisor_markup }}</span>
              </td>
              <td v-for="b in fila.bases" :key="b" class="px-1 py-1.5 text-right tabular-nums">
                <span v-if="sugerido(fila.categoria, b, t.id) !== undefined && sugerido(fila.categoria, b, t.id) !== null">
                  {{ formatearImporte(sugerido(fila.categoria, b, t.id)) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
            <tr v-else>
              <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">
                Venta {{ t.nombre }} <span class="text-gray-400">{{ t.moneda }}</span>
              </td>
              <td v-for="b in fila.bases" :key="b" class="px-1 py-1.5 text-right">
                <input
                  v-model="celdas[clave(fila.categoria, b)].venta[t.id]"
                  type="number"
                  step="0.01"
                  min="0"
                  :class="cls"
                  :disabled="disabled"
                  @change="emit('cambio')"
                />
                <button
                  v-if="sugerido(fila.categoria, b, t.id) !== undefined && sugerido(fila.categoria, b, t.id) !== null"
                  type="button"
                  class="block w-24 text-right text-[11px] text-blue-600 hover:underline"
                  :title="`Usar el sugerido: ${formatearImporte(sugerido(fila.categoria, b, t.id))}`"
                  @click="usarSugerido(fila.categoria, b, t.id)"
                >
                  ≈ {{ formatearImporte(sugerido(fila.categoria, b, t.id), 0) }}
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
