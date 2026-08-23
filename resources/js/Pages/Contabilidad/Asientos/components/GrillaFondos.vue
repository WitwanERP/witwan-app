<script setup>
import { computed } from 'vue'
import { formatearImporte } from '@/lib/formato'

/**
 * Grilla del movimiento de fondos: cada línea es un monto que sale de una cuenta
 * y entra en otra, y genera su propio asiento de dos movimientos
 * (fondos.php:218-256).
 *
 * Diferencias con formfondos.php:
 *
 *  1. El legacy pinta 8 bloques "Operación N" fijos, cada uno con tres campos
 *     apilados verticalmente: para cargar tres movimientos hay que scrollear
 *     ocho bloques. Acá es una tabla y las filas se agregan cuando hacen falta.
 *  2. Se avisa si ingreso y egreso son la misma cuenta. El legacy lo grababa: un
 *     asiento que debita y acredita la misma cuenta, o sea nada, pero que
 *     ensucia el mayor.
 *  3. Hay total al pie. El legacy lo calcula sólo en el servidor, así que hasta
 *     no guardar no sabías cuánto ibas a mover.
 */
const props = defineProps({
  lineas: { type: Array, required: true },
  cuentas: { type: Array, required: true },
  moneda: { type: String, default: '' },
  errores: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['agregar', 'quitar'])

const num = (v) => {
  const n = parseFloat(String(v ?? '').replace(',', '.'))

  return Number.isFinite(n) ? n : 0
}

const total = computed(() => props.lineas.reduce((s, l) => s + num(l.monto), 0))

const cargadas = computed(() => props.lineas.filter((l) => num(l.monto)).length)

const mismaCuenta = (l) => l.ingreso && l.egreso && Number(l.ingreso) === Number(l.egreso)

const hayError = computed(() =>
  props.lineas.some((l) => (num(l.monto) && (!l.ingreso || !l.egreso)) || mismaCuenta(l))
)

defineExpose({ total, hayError })

const celda =
  'w-full rounded border border-gray-300 bg-white px-2 py-1 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/30'
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Operaciones</h3>
      <span class="text-sm text-gray-500">{{ cargadas }} con monto</span>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th class="w-10 px-2 py-2 text-left font-semibold">#</th>
            <th class="w-40 px-2 py-2 text-right font-semibold">Monto</th>
            <th class="min-w-64 px-2 py-2 text-left font-semibold">Cuenta de ingreso</th>
            <th class="min-w-64 px-2 py-2 text-left font-semibold">Cuenta de egreso</th>
            <th class="w-10 px-2 py-2"></th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          <tr v-for="(linea, i) in lineas" :key="i" class="align-top">
            <td class="px-2 py-1 text-gray-400">{{ i + 1 }}</td>

            <td class="px-2 py-1">
              <input
                v-model="linea.monto"
                type="number"
                step="0.01"
                min="0"
                :class="celda + ' text-right tabular-nums'"
              />
            </td>

            <td class="px-2 py-1">
              <select v-model="linea.ingreso" :class="celda">
                <option value="">—</option>
                <option v-for="c in cuentas" :key="c.id" :value="c.id">{{ c.label }}</option>
              </select>
            </td>

            <td class="px-2 py-1">
              <select v-model="linea.egreso" :class="celda">
                <option value="">—</option>
                <option v-for="c in cuentas" :key="c.id" :value="c.id">{{ c.label }}</option>
              </select>
              <p v-if="mismaCuenta(linea)" class="form-error">
                El ingreso y el egreso no pueden ser la misma cuenta.
              </p>
              <p v-else-if="num(linea.monto) && (!linea.ingreso || !linea.egreso)" class="form-error">
                Falta elegir las dos cuentas.
              </p>
              <p v-if="errores[`lineas.${i}.egreso`]" class="form-error">{{ errores[`lineas.${i}.egreso`] }}</p>
            </td>

            <td class="px-2 py-1 text-right">
              <button
                v-if="lineas.length > 1"
                type="button"
                class="text-gray-400 hover:text-red-600"
                :aria-label="`Quitar la línea ${i + 1}`"
                @click="emit('quitar', i)"
              >
                &times;
              </button>
            </td>
          </tr>
        </tbody>

        <tfoot class="border-t-2 border-gray-200 bg-gray-50">
          <tr>
            <th class="px-2 py-2 text-left font-semibold text-gray-700">Total</th>
            <th class="px-2 py-2 text-right tabular-nums font-semibold text-gray-900">
              {{ moneda }} {{ formatearImporte(total) }}
            </th>
            <th colspan="3"></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card-footer">
      <button type="button" class="btn btn-sm btn-secondary" @click="emit('agregar')">Agregar operación</button>
    </div>
  </div>
</template>
