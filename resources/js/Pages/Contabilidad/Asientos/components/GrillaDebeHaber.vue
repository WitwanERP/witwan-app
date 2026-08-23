<script setup>
import { computed } from 'vue'
import BuscadorRemoto from '@/Components/BuscadorRemoto.vue'
import { formatearImporte } from '@/lib/formato'

/**
 * Grilla debe/haber del asiento contable y del asiento en cuenta corriente.
 *
 * Tres diferencias con la del legacy (formasientocontable.php / formasientocta.php):
 *
 *  1. **Filas bajo demanda.** El CI renderiza 200 `<tr>` ocultos y las va
 *     mostrando con un botón "SUMAR CUENTAS": 200 filas × 8 inputs en el DOM
 *     desde el primer render.
 *  2. **El descuadre se ve.** El pie marca en rojo la diferencia y el botón de
 *     guardar queda deshabilitado. El legacy dejaba mandar el form igual y el
 *     servidor tampoco chequeaba, así que se grababan asientos descuadrados.
 *  3. **Contrapartida en un click.** El botón "Cerrar el asiento" completa la
 *     última fila con la diferencia, que es lo que se hace a mano el 100% de
 *     las veces.
 */
const props = defineProps({
  lineas: { type: Array, required: true },
  config: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  moneda: { type: String, default: '' },
  errores: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['agregar', 'quitar'])

const num = (v) => {
  const n = parseFloat(String(v ?? '').replace(',', '.'))

  return Number.isFinite(n) ? n : 0
}

const totalDebe = computed(() => props.lineas.reduce((s, l) => s + num(l.debe), 0))
const totalHaber = computed(() => props.lineas.reduce((s, l) => s + num(l.haber), 0))
const diferencia = computed(() => Math.round((totalDebe.value - totalHaber.value) * 100) / 100)
const balancea = computed(() => Math.abs(diferencia.value) < 0.01)

const conImporte = computed(() => props.lineas.filter((l) => num(l.debe) || num(l.haber)).length)

defineExpose({ balancea, totalDebe, totalHaber, diferencia })

const celda =
  'w-full rounded border border-gray-300 bg-white px-2 py-1 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/30'
const celdaNum = celda + ' text-right tabular-nums'

/**
 * Una línea no puede tener importe en las dos columnas: al cargar uno se limpia
 * el otro. En el legacy se podían llenar los dos y el PHP se quedaba con el debe
 * en silencio (el `elseif` de asientocontable.php:170).
 */
function alCargarDebe(linea) {
  if (num(linea.debe)) linea.haber = ''
}

function alCargarHaber(linea) {
  if (num(linea.haber)) linea.debe = ''
}

/** Completa la última fila vacía con la diferencia, para cerrar el asiento. */
function cerrarAsiento() {
  if (balancea.value) return

  const destino = props.lineas.find((l) => !num(l.debe) && !num(l.haber)) ?? props.lineas[props.lineas.length - 1]
  const falta = Math.abs(diferencia.value).toFixed(2)

  if (diferencia.value > 0) {
    destino.haber = falta
    destino.debe = ''
  } else {
    destino.debe = falta
    destino.haber = ''
  }
}
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Movimientos</h3>
      <span class="text-sm text-gray-500">{{ conImporte }} línea{{ conImporte === 1 ? '' : 's' }} con importe</span>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
          <tr>
            <th class="w-10 px-2 py-2 text-left font-semibold">#</th>
            <th class="min-w-56 px-2 py-2 text-left font-semibold">Cuenta</th>
            <th v-if="config.imputa.includes('cliente')" class="min-w-44 px-2 py-2 text-left font-semibold">Cliente</th>
            <th v-if="config.imputa.includes('proveedor')" class="min-w-44 px-2 py-2 text-left font-semibold">
              Proveedor
            </th>
            <th class="min-w-48 px-2 py-2 text-left font-semibold">Leyenda</th>
            <th v-if="config.imputa.includes('file')" class="min-w-36 px-2 py-2 text-left font-semibold">File</th>
            <th class="w-32 px-2 py-2 text-right font-semibold">Debe</th>
            <th class="w-32 px-2 py-2 text-right font-semibold">Haber</th>
            <th class="w-10 px-2 py-2"></th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          <tr v-for="(linea, i) in lineas" :key="i" class="align-top">
            <td class="px-2 py-1 text-gray-400">{{ i + 1 }}</td>

            <td class="px-2 py-1">
              <BuscadorRemoto
                v-model="linea.cuenta"
                v-model:etiqueta="linea.cuentaLabel"
                :url="`${baseUrl}/cuentas`"
                placeholder="Código o nombre…"
                :minimo="1"
                :clase-input="celda"
              />
              <p v-if="errores[`lineas.${i}.cuenta`]" class="form-error">{{ errores[`lineas.${i}.cuenta`] }}</p>
            </td>

            <td v-if="config.imputa.includes('cliente')" class="px-2 py-1">
              <BuscadorRemoto
                v-model="linea.cliente"
                v-model:etiqueta="linea.clienteLabel"
                :url="`${baseUrl}/clientes`"
                placeholder="Cliente…"
                :clase-input="celda"
              />
            </td>

            <td v-if="config.imputa.includes('proveedor')" class="px-2 py-1">
              <BuscadorRemoto
                v-model="linea.proveedor"
                v-model:etiqueta="linea.proveedorLabel"
                :url="`${baseUrl}/proveedores`"
                placeholder="Proveedor…"
                :clase-input="celda"
              />
            </td>

            <td class="px-2 py-1">
              <input v-model="linea.descripcion" type="text" :class="celda" placeholder="Leyenda" />
            </td>

            <td v-if="config.imputa.includes('file')" class="px-2 py-1">
              <BuscadorRemoto
                v-model="linea.file"
                v-model:etiqueta="linea.fileLabel"
                :url="`${baseUrl}/files`"
                placeholder="Código…"
                :clase-input="celda"
              />
            </td>

            <td class="px-2 py-1">
              <input
                v-model="linea.debe"
                type="number"
                step="0.01"
                min="0"
                :class="celdaNum"
                @input="alCargarDebe(linea)"
              />
            </td>

            <td class="px-2 py-1">
              <input
                v-model="linea.haber"
                type="number"
                step="0.01"
                min="0"
                :class="celdaNum"
                @input="alCargarHaber(linea)"
              />
            </td>

            <td class="px-2 py-1 text-right">
              <button
                v-if="lineas.length > 2"
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

        <tfoot class="border-t-2 border-gray-200 bg-gray-50 text-sm">
          <tr>
            <th colspan="2" class="px-2 py-2 text-left font-semibold text-gray-700">Totales</th>
            <th :colspan="1 + config.imputa.length" class="px-2 py-2"></th>
            <th class="px-2 py-2 text-right tabular-nums font-semibold text-gray-900">
              {{ formatearImporte(totalDebe) }}
            </th>
            <th class="px-2 py-2 text-right tabular-nums font-semibold text-gray-900">
              {{ formatearImporte(totalHaber) }}
            </th>
            <th></th>
          </tr>
          <tr v-if="!balancea">
            <th :colspan="3 + config.imputa.length" class="px-2 py-2 text-left font-normal text-red-600">
              El asiento no balancea.
            </th>
            <th colspan="2" class="px-2 py-2 text-right tabular-nums font-semibold text-red-600">
              Diferencia {{ moneda }} {{ formatearImporte(diferencia) }}
            </th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card-footer flex flex-wrap gap-2">
      <button type="button" class="btn btn-sm btn-secondary" @click="emit('agregar')">Agregar línea</button>
      <button v-if="!balancea" type="button" class="btn btn-sm btn-secondary" @click="cerrarAsiento">
        Cerrar el asiento con la diferencia
      </button>
      <span v-else class="self-center text-sm text-green-700">Balanceado.</span>
    </div>
  </div>
</template>
