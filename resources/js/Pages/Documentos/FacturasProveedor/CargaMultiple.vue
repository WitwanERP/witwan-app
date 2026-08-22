<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SelectorProveedor from './components/SelectorProveedor.vue'
import { calcularTotales, formatearImporte } from './calculo.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
  opciones: { type: Object, required: true },
  monedas: { type: Array, default: () => [] },
  baseUrl: { type: String, required: true },
})

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'
const numBase = fieldBase + ' text-right'

const monedaBasica = props.monedas.find((m) => m.basica)?.id ?? ''

function documentoVacio() {
  return {
    facturaproveedor_nro: '',
    facturaproveedor_tipodocumento: 'Factura',
    facturaproveedor_tipofactura: '',
    fecha: '',
    fechacontable: '',
    vencimiento: '',
    exento: 0,
    nocomputable: 0,
    especial: 0,
    general: 0,
    monto27: 0,
    monto25: 0,
    ivatotal: 0,
    ivatur: 0,
    retencioniva: 0,
    retencioniibb: 0,
    percepcioniva: 0,
    percepcioniibb: 0,
    retencionganancias: 0,
    percepcionganancias: 0,
    otrosimpuestos: 0,
  }
}

const form = useForm({
  cabecera: {
    fk_proveedor_id: '',
    tipomovimiento: 'Gasto',
    fk_plancuenta_id: '',
    fk_moneda_id: monedaBasica,
    cotizacion: 1,
    fk_proyecto_id: '',
    areaimputacion: {},
    descripcion: '',
  },
  documentos: [documentoVacio()],
})

const totalesPorFila = computed(() =>
  form.documentos.map((d) => calcularTotales(d, props.opciones.calculo, []))
)

const totalGeneral = computed(() =>
  totalesPorFila.value.reduce((acc, t) => acc + t.montototal, 0)
)

function agregar() {
  form.documentos.push(documentoVacio())
}

/** El legacy sólo permitía agregar filas, nunca quitarlas. */
function quitar(i) {
  if (form.documentos.length === 1) return
  form.documentos.splice(i, 1)
}

function duplicar(i) {
  form.documentos.splice(i + 1, 0, { ...form.documentos[i], facturaproveedor_nro: '' })
}

function guardar() {
  form.post(`${props.baseUrl}/multiple`, { preserveScroll: true })
}

const columnasImporte = [
  { campo: 'exento', label: 'Exento' },
  { campo: 'nocomputable', label: 'No comp.' },
  { campo: 'general', label: 'Gravado' },
  { campo: 'especial', label: '10,5%' },
  { campo: 'monto27', label: '27%' },
  { campo: 'monto25', label: '2,5%' },
  { campo: 'ivatur', label: 'IVA TUR' },
  { campo: 'otrosimpuestos', label: 'Otros' },
]
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Carga múltiple de facturas</h1>
        <p class="text-gray-500">Una cabecera común y un documento por fila.</p>
      </div>
      <Link :href="baseUrl" class="btn btn-secondary">Cancelar</Link>
    </div>

    <form @submit.prevent="guardar">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Datos comunes</h3></div>
        <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-3">
          <SelectorProveedor v-model="form.cabecera.fk_proveedor_id" :base-url="baseUrl" />

          <div>
            <label class="form-label font-bold">Tipo de gasto</label>
            <select v-model="form.cabecera.tipomovimiento" :class="fieldBase">
              <option v-for="(label, valor) in opciones.tiposMovimiento" :key="valor" :value="valor">
                {{ label }}
              </option>
            </select>
          </div>

          <div>
            <label class="form-label font-bold">Cuenta contable</label>
            <select v-model="form.cabecera.fk_plancuenta_id" :class="fieldBase">
              <option value="">Seleccione una opción</option>
              <option v-for="(nombre, id) in opciones.plancuenta" :key="id" :value="id">{{ nombre }}</option>
            </select>
          </div>

          <div>
            <label class="form-label font-bold">Moneda</label>
            <select v-model="form.cabecera.fk_moneda_id" :class="fieldBase">
              <option v-for="m in monedas" :key="m.id" :value="m.id">{{ m.id }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">Cotización</label>
            <input v-model="form.cabecera.cotizacion" type="number" step="0.0001" :class="numBase" />
          </div>

          <div>
            <label class="form-label">Proyecto</label>
            <select v-model="form.cabecera.fk_proyecto_id" :class="fieldBase">
              <option value="">—</option>
              <option v-for="(nombre, id) in opciones.proyectos" :key="id" :value="id">{{ nombre }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header flex items-center justify-between">
          <h3 class="card-title">Documentos ({{ form.documentos.length }})</h3>
          <button type="button" class="btn btn-sm btn-secondary" @click="agregar">Agregar documento</button>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
              <tr>
                <th class="px-2 py-2 text-left font-semibold">#</th>
                <th class="px-2 py-2 text-left font-semibold">T.Doc.</th>
                <th class="px-2 py-2 text-left font-semibold">Número</th>
                <th class="px-2 py-2 text-left font-semibold">Fecha</th>
                <th v-for="c in columnasImporte" :key="c.campo" class="px-2 py-2 text-right font-semibold">
                  {{ c.label }}
                </th>
                <th class="px-2 py-2 text-right font-semibold">IVA</th>
                <th class="px-2 py-2 text-right font-semibold">Total</th>
                <th class="px-2 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(d, i) in form.documentos" :key="i" class="align-top">
                <td class="px-2 py-2 text-gray-500">{{ i + 1 }}</td>
                <td class="px-2 py-2">
                  <select v-model="d.facturaproveedor_tipodocumento" class="rounded border border-gray-300 px-2 py-1 text-sm">
                    <option v-for="(label, valor) in opciones.tiposDocumento" :key="valor" :value="valor">
                      {{ label }}
                    </option>
                  </select>
                </td>
                <td class="px-2 py-2">
                  <input v-model="d.facturaproveedor_nro" type="text" class="w-32 rounded border border-gray-300 px-2 py-1 text-sm" />
                </td>
                <td class="px-2 py-2">
                  <input v-model="d.fecha" type="date" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                </td>
                <td v-for="c in columnasImporte" :key="c.campo" class="px-2 py-2">
                  <input
                    v-model="d[c.campo]"
                    type="number"
                    step="0.01"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-right text-sm"
                  />
                </td>
                <td class="px-2 py-2 text-right tabular-nums text-gray-600">
                  {{ formatearImporte(totalesPorFila[i].soloiva) }}
                </td>
                <td class="px-2 py-2 text-right tabular-nums font-semibold text-gray-900">
                  {{ formatearImporte(totalesPorFila[i].montototal) }}
                </td>
                <td class="whitespace-nowrap px-2 py-2 text-right">
                  <button type="button" class="text-xs text-blue-600 hover:underline" @click="duplicar(i)">
                    Duplicar
                  </button>
                  <button
                    type="button"
                    class="ml-2 text-xs text-red-600 hover:underline disabled:text-gray-300"
                    :disabled="form.documentos.length === 1"
                    @click="quitar(i)"
                  >
                    Quitar
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-amber-50">
              <tr>
                <td :colspan="columnasImporte.length + 5" class="px-2 py-2 text-right font-semibold text-gray-700">
                  Total de los {{ form.documentos.length }} documentos
                </td>
                <td class="px-2 py-2 text-right tabular-nums font-bold">{{ formatearImporte(totalGeneral) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="sticky bottom-0 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur lg:-mx-6 lg:px-6">
        <div class="flex items-center gap-4">
          <p class="text-sm text-gray-500">
            Se crean todos o ninguno: si un documento falla, no se guarda nada.
          </p>
          <button type="submit" class="btn btn-primary ml-auto" :disabled="form.processing">
            {{ form.processing ? 'Guardando…' : `Crear ${form.documentos.length} factura(s)` }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
