<script setup>
import { computed } from 'vue'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  totales: { type: Object, required: true },
  vista: { type: String, default: 'listado' },
  pais: { type: String, default: 'AR' },
})

/**
 * Los totales llegan de una consulta de agregación sobre TODO el conjunto
 * filtrado, no de la página visible: son los mismos números que mostraba el
 * legacy, que para conseguirlos traía la tabla entera a memoria.
 */
const CAMPOS = {
  listado: [
    { key: 'montoexento', label: 'Exento' },
    { key: 'montonocomputable', label: 'No Comp.', soloAr: true },
    { key: 'netogravado', label: 'Neto Gravado' },
    { key: 'i21', label: 'IVA' },
    { key: 'i105', label: 'IVA 10.5%', soloAr: true },
    { key: 'i27', label: 'IVA 27%', soloAr: true },
    { key: 'i25', label: 'IVA 2.5%', soloAr: true },
    { key: 'retencioniva', label: 'Ret. IVA', soloAr: true },
    { key: 'percepcioniva', label: 'Per. IVA', soloAr: true },
    { key: 'ivatur', label: 'IVA TUR', soloAr: true },
    { key: 'montototal', label: 'TOTAL', destacada: true },
  ],
  subdiario: [
    { key: 'montoexento', label: 'Exento' },
    { key: 'montonocomputable', label: 'No Comp.' },
    { key: 'netogravado', label: 'Neto Gr.' },
    { key: 'idi21', label: 'IVA' },
    { key: 'iin21', label: 'IVA Gasto' },
    { key: 'i105', label: 'IVA 10.5%' },
    { key: 'i2527', label: 'IVA 27/2,5%' },
    { key: 'montototal', label: 'TOTAL', destacada: true },
  ],
}

const campos = computed(() => CAMPOS[props.vista].filter((c) => !(c.soloAr && props.pais === 'CL')))
const hayTotales = computed(() => props.totales?.general && props.totales.porTipo?.length)
</script>

<template>
  <div v-if="hayTotales" class="card mt-4">
    <div class="card-header">
      <h3 class="card-title">Totales</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th class="px-3 py-2 text-left font-semibold">Tipo de documento</th>
            <th class="px-3 py-2 text-right font-semibold">Cant.</th>
            <th v-for="c in campos" :key="c.key" class="whitespace-nowrap px-3 py-2 text-right font-semibold">
              {{ c.label }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="g in totales.porTipo" :key="g.tipodocumento">
            <td class="px-3 py-2 text-gray-700">Total {{ g.tipodocumento }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ g.cantidad }}</td>
            <td
              v-for="c in campos"
              :key="c.key"
              class="whitespace-nowrap px-3 py-2 text-right tabular-nums"
              :class="c.destacada ? 'font-semibold text-gray-900' : 'text-gray-700'"
            >
              {{ formatearImporte(g[c.key]) }}
            </td>
          </tr>
          <tr class="bg-amber-50 font-semibold">
            <td class="px-3 py-2 text-gray-900">Total final</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ totales.general.cantidad }}</td>
            <td v-for="c in campos" :key="c.key" class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
              {{ formatearImporte(totales.general[c.key]) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
