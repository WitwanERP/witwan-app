<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BuscadorRemoto from '@/Components/BuscadorRemoto.vue'
import { formatearImporte } from '@/lib/formato'

defineOptions({ layout: AppLayout })

/**
 * Edición acotada de un asiento ya contabilizado.
 *
 * Los importes y las cuentas se muestran pero NO se pueden tocar, igual que en
 * el legacy (asientocta::guardaredit y fondos::update sólo actualizan banco,
 * descripción, operación, fechas e imputaciones). Cambiar un importe después de
 * contabilizado descuadraría el asiento sin dejar rastro: para eso está anular y
 * volver a cargar.
 */
const props = defineProps({
  config: { type: Object, required: true },
  opciones: { type: Object, required: true },
  asiento: { type: Object, required: true },
  movimientos: { type: Array, default: () => [] },
  totales: { type: Object, required: true },
  periodoAbierto: { type: Boolean, default: true },
})

const form = useForm({
  fecha: props.asiento.fecha ?? '',
  observaciones: props.asiento.observaciones ?? '',
  fk_proyecto_id: props.asiento.fk_proyecto_id || '',
  movimientos: props.movimientos.map((m) => ({
    id: m.id,
    descripcion: m.descripcion,
    banco: m.banco,
    operacion: m.operacion,
    fechaAcreditacion: m.fechaAcreditacion ?? '',
    clienteId: m.clienteId,
    clienteLabel: m.cliente,
    proveedorId: m.proveedorId,
    proveedorLabel: m.proveedor,
    fileId: m.fileId,
    fileLabel: m.file,
  })),
})

/** Los datos que no se editan se leen del prop, indexados por id de movimiento. */
const original = computed(() => Object.fromEntries(props.movimientos.map((m) => [m.id, m])))

const fechaMinima = computed(() => props.config.primeraFechaOperable || undefined)

const celda =
  'w-full rounded border border-gray-300 bg-white px-2 py-1 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/30'

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function enviar() {
  form.put(`${props.config.baseUrl}/${props.asiento.id}`, { preserveScroll: true })
}
</script>

<template>
  <form @submit.prevent="enviar">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          Editar {{ config.singular }} N° {{ asiento.numero }}
        </h1>
        <p class="text-gray-500">
          {{ asiento.moneda }} {{ formatearImporte(asiento.monto) }} · {{ movimientos.length }} movimientos
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link :href="`${config.baseUrl}/${asiento.id}`" class="btn btn-secondary">Ver</Link>
        <Link :href="config.baseUrl" class="btn btn-secondary">Volver al listado</Link>
      </div>
    </div>

    <div
      v-if="!periodoAbierto"
      class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
    >
      La fecha de este asiento quedó fuera del período contable abierto: los cambios se van a rechazar al guardar.
    </div>

    <div
      class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
    >
      Los importes y las cuentas no se editan: un cambio ahí descuadraría el asiento ya contabilizado. Para
      corregirlos, anulá el asiento y cargalo de nuevo.
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="form-label">Fecha</label>
            <input v-model="form.fecha" type="date" :class="fieldBase" :min="fechaMinima" required />
            <p v-if="form.errors.fecha" class="form-error">{{ form.errors.fecha }}</p>
            <p v-else class="form-hint">Se replica en la fecha de todos los movimientos.</p>
          </div>

          <div v-if="config.usaProyecto">
            <label class="form-label">Proyecto</label>
            <select v-model="form.fk_proyecto_id" :class="fieldBase">
              <option value="">—</option>
              <option v-for="p in opciones.proyectos" :key="p.id" :value="p.id">{{ p.label }}</option>
            </select>
          </div>

          <div class="lg:col-span-2">
            <label class="form-label">Observaciones</label>
            <input v-model="form.observaciones" type="text" :class="fieldBase" />
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Movimientos</h3>
        <span class="text-sm text-gray-500">
          Debe {{ formatearImporte(totales.debe) }} · Haber {{ formatearImporte(totales.haber) }}
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
            <tr>
              <th class="min-w-48 px-2 py-2 text-left font-semibold">Cuenta</th>
              <th class="w-28 px-2 py-2 text-right font-semibold">Debe</th>
              <th class="w-28 px-2 py-2 text-right font-semibold">Haber</th>
              <th class="min-w-48 px-2 py-2 text-left font-semibold">Leyenda</th>
              <th class="min-w-36 px-2 py-2 text-left font-semibold">Banco</th>
              <th class="min-w-36 px-2 py-2 text-left font-semibold">Operación</th>
              <th class="w-40 px-2 py-2 text-left font-semibold">Acreditación</th>
              <th v-if="config.imputa.includes('cliente')" class="min-w-44 px-2 py-2 text-left font-semibold">
                Cliente
              </th>
              <th v-if="config.imputa.includes('proveedor')" class="min-w-44 px-2 py-2 text-left font-semibold">
                Proveedor
              </th>
              <th v-if="config.imputa.includes('file')" class="min-w-36 px-2 py-2 text-left font-semibold">File</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <tr v-for="(m, i) in form.movimientos" :key="m.id" class="align-top">
              <!-- Cuenta e importes: sólo lectura. -->
              <td class="px-2 py-2 text-gray-900">
                {{ original[m.id].cuenta || `#${original[m.id].cuentaId}` }}
                <span v-if="original[m.id].cuentaCodigo" class="ml-1 text-xs text-gray-400">
                  {{ original[m.id].cuentaCodigo }}
                </span>
              </td>
              <td class="px-2 py-2 text-right tabular-nums text-gray-700">
                {{ original[m.id].debe === null ? '' : formatearImporte(original[m.id].debe) }}
              </td>
              <td class="px-2 py-2 text-right tabular-nums text-gray-700">
                {{ original[m.id].haber === null ? '' : formatearImporte(original[m.id].haber) }}
              </td>

              <td class="px-2 py-1">
                <input v-model="m.descripcion" type="text" :class="celda" />
              </td>
              <td class="px-2 py-1">
                <input v-model="m.banco" type="text" :class="celda" />
              </td>
              <td class="px-2 py-1">
                <input v-model="m.operacion" type="text" :class="celda" />
              </td>
              <td class="px-2 py-1">
                <input v-model="m.fechaAcreditacion" type="date" :class="celda" />
                <p v-if="form.errors[`movimientos.${i}.fechaAcreditacion`]" class="form-error">
                  {{ form.errors[`movimientos.${i}.fechaAcreditacion`] }}
                </p>
              </td>

              <td v-if="config.imputa.includes('cliente')" class="px-2 py-1">
                <BuscadorRemoto
                  v-model="m.clienteId"
                  v-model:etiqueta="m.clienteLabel"
                  :url="`${config.baseUrl}/clientes`"
                  placeholder="Cliente…"
                  :clase-input="celda"
                />
              </td>
              <td v-if="config.imputa.includes('proveedor')" class="px-2 py-1">
                <BuscadorRemoto
                  v-model="m.proveedorId"
                  v-model:etiqueta="m.proveedorLabel"
                  :url="`${config.baseUrl}/proveedores`"
                  placeholder="Proveedor…"
                  :clase-input="celda"
                />
              </td>
              <td v-if="config.imputa.includes('file')" class="px-2 py-1">
                <BuscadorRemoto
                  v-model="m.fileId"
                  v-model:etiqueta="m.fileLabel"
                  :url="`${config.baseUrl}/files`"
                  placeholder="Código…"
                  :clase-input="celda"
                />
              </td>
            </tr>

            <tr v-if="!form.movimientos.length">
              <td colspan="10" class="px-3 py-8 text-center text-gray-500">
                Este asiento no tiene movimientos para editar.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <button type="submit" class="btn btn-primary" :disabled="form.processing">
        {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
      </button>
      <Link :href="`${config.baseUrl}/${asiento.id}`" class="btn btn-secondary">Cancelar</Link>
    </div>
  </form>
</template>
