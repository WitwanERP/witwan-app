<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  baseUrl: { type: String, required: true },
  filtros: { type: Object, default: () => ({}) },
  documentos: { type: Array, default: () => [] },
  error: { type: String, default: null },
  disponible: { type: Boolean, default: false },
})

const fieldBase =
  'rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none'

const form = reactive({
  desde: props.filtros.desde ?? '',
  hasta: props.filtros.hasta ?? '',
})

function buscar() {
  router.get(`${props.baseUrl}/dte-chile`, { ...form }, { preserveState: true })
}

const encabezado = (d) => d.detalle?.encabezado?.Emisor ?? {}
const totales = (d) => d.detalle?.encabezado?.Totales ?? {}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Documentos electrónicos recibidos</h1>
        <p class="text-gray-500">Consulta al servicio de DTE de Chile.</p>
      </div>
      <Link :href="baseUrl" class="btn btn-secondary">Volver al listado</Link>
    </div>

    <div v-if="!disponible" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      El servicio de DTE no está disponible en esta instalación (falta la extensión SOAP de PHP o las
      credenciales del webservice).
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="buscar">
          <div>
            <label class="form-label">Desde</label>
            <input v-model="form.desde" type="date" :class="fieldBase" />
          </div>
          <div>
            <label class="form-label">Hasta</label>
            <input v-model="form.hasta" type="date" :class="fieldBase" />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="!disponible">Consultar</button>
        </form>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      {{ error }}
    </div>

    <div v-if="documentos.length" class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left font-semibold">Emisor</th>
              <th class="px-3 py-2 text-left font-semibold">Razón social</th>
              <th class="px-3 py-2 text-left font-semibold">Tipo</th>
              <th class="px-3 py-2 text-left font-semibold">Folio</th>
              <th class="px-3 py-2 text-right font-semibold">Total</th>
              <th class="px-3 py-2 text-left font-semibold">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="d in documentos" :key="d.clave">
              <td class="px-3 py-2 text-gray-700">{{ d.emisor }}</td>
              <td class="px-3 py-2 text-gray-700">{{ encabezado(d).RznSoc || '—' }}</td>
              <td class="px-3 py-2 text-gray-700">{{ d.tipo }}</td>
              <td class="px-3 py-2 text-gray-700">{{ d.folio }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-gray-900">{{ totales(d).MntTotal ?? '—' }}</td>
              <td class="px-3 py-2">
                <span v-if="d.facturaId" class="badge badge-success">Ya cargada</span>
                <span v-else class="badge badge-gray">Pendiente</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="!error && filtros.desde" class="card">
      <div class="card-body py-8 text-center text-gray-500">
        No se recibieron documentos en ese período.
      </div>
    </div>
  </div>
</template>
