<script setup>
import { computed } from 'vue'
import { formatearImporte } from '../calculo.js'

const props = defineProps({
  form: { type: Object, required: true },
  tasas: { type: Object, required: true },
  totales: { type: Object, required: true },
  pais: { type: String, default: 'AR' },
  exentoBloqueado: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
})

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-right text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100 disabled:text-gray-500'

const pct = (v) => `${formatearImporte(Number(v) * 100, 1)}%`

/**
 * Las bases imponibles se declaran acá para que la variante por país sea un
 * filtro sobre datos y no un v-if por campo, como en la vista del legacy.
 * Las alícuotas vienen del servidor: este componente no conoce ninguna.
 */
const bases = computed(() =>
  [
    { campo: 'exento', label: 'Exento', iva: null },
    { campo: 'nocomputable', label: 'No computable', iva: null, soloAr: true },
    { campo: 'general', label: `Gravado ${pct(props.tasas.general)}`, iva: 'ivaGeneral' },
    { campo: 'especial', label: `Gravado ${pct(props.tasas.especial)}`, iva: 'ivaEspecial', soloAr: true },
    { campo: 'monto27', label: `Gravado ${pct(props.tasas.monto27)}`, iva: 'iva27', soloAr: true },
    { campo: 'monto25', label: `Gravado ${pct(props.tasas.monto25)}`, iva: 'iva25', soloAr: true },
  ].filter((b) => !(b.soloAr && props.pais === 'CL'))
)

const otros = computed(() =>
  [
    { campo: 'ivatur', label: 'IVA turismo (resta)', soloAr: true },
    { campo: 'retencioniva', label: 'Retención IVA' },
    { campo: 'percepcioniva', label: 'Percepción IVA' },
    { campo: 'retencioniibb', label: 'Retención IIBB', soloAr: true },
    { campo: 'percepcioniibb', label: 'Percepción IIBB', soloAr: true },
    { campo: 'retencionganancias', label: 'Retención Ganancias', soloAr: true },
    { campo: 'percepcionganancias', label: 'Percepción Ganancias', soloAr: true },
    { campo: 'otrosimpuestos', label: 'Otros impuestos' },
  ].filter((o) => !(o.soloAr && props.pais === 'CL'))
)
</script>

<template>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Importes</h3>
    </div>
    <div class="card-body">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="py-2 text-left font-semibold">Concepto</th>
              <th class="py-2 text-right font-semibold">Base imponible</th>
              <th class="py-2 text-right font-semibold">IVA calculado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="b in bases" :key="b.campo">
              <td class="py-2 pr-4 text-gray-700">{{ b.label }}</td>
              <td class="w-40 py-2">
                <input
                  v-model="form[b.campo]"
                  type="number"
                  step="0.01"
                  :class="fieldBase"
                  :disabled="disabled || (b.campo === 'exento' && exentoBloqueado)"
                  :title="b.campo === 'exento' && exentoBloqueado ? 'Lo determina la suma de los conceptos adicionales' : ''"
                />
              </td>
              <td class="w-32 py-2 text-right tabular-nums text-gray-600">
                {{ b.iva ? formatearImporte(totales[b.iva]) : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="o in otros" :key="o.campo">
          <label class="form-label">{{ o.label }}</label>
          <input v-model="form[o.campo]" type="number" step="0.01" :class="fieldBase" :disabled="disabled" />
          <p v-if="errors[o.campo]" class="form-error">{{ errors[o.campo] }}</p>
        </div>

        <div>
          <label class="form-label">IVA total</label>
          <input
            v-model="form.ivatotal"
            type="number"
            step="0.01"
            :class="fieldBase"
            :disabled="disabled || !tasas.ivatotalEditable"
            :title="tasas.ivatotalEditable ? 'Se puede editar en esta licencia' : 'Se calcula a partir de las bases imponibles'"
          />
          <p v-if="!tasas.ivatotalEditable" class="form-hint">Calculado: {{ formatearImporte(totales.ivaCalculado) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
