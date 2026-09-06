<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  filtros: { type: Object, default: () => ({}) },
  conFiltros: { type: Boolean, default: false },
  opciones: { type: Object, required: true },
  filas: { type: Array, default: () => [] },
})

const base = `/app/operaciones/autorizar/${props.area}`
const form = reactive({ ...props.filtros })
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function buscar() {
  const p = { buscar: 1 }
  for (const [k, v] of Object.entries(form)) if (v !== '' && v !== null) p[k] = v
  router.get(base, p, { preserveState: true, preserveScroll: true })
}
function limpiar() {
  router.get(base)
}
const fmt = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #66cc00">Operaciones</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Autorizar reservas <span class="text-gray-500 font-normal">({{ area }})</span></span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Autorizar reservas</h1>
        <p class="text-gray-500">
          <span v-if="conFiltros">{{ filas.length }} file{{ filas.length === 1 ? '' : 's' }}</span>
          <span v-else>Sin filtros se muestran los files no autorizados con servicios en los próximos 21 días.</span>
        </p>
      </div>
    </div>

    <form class="card mb-4" @submit.prevent="buscar">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div>
          <label class="form-label">Código</label>
          <input v-model="form.codigo" type="text" :class="fieldBase" placeholder="Varios separados por *" />
        </div>
        <div>
          <label class="form-label">Titular</label>
          <input v-model="form.titular" type="text" :class="fieldBase" />
        </div>
        <div>
          <label class="form-label">Cliente</label>
          <select v-model="form.cliente" :class="fieldBase">
            <option value="">Todos</option>
            <option v-for="o in opciones.clientes" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Vendedor</label>
          <select v-model="form.fk_vendedor_id" :class="fieldBase">
            <option value="">Todos</option>
            <option v-for="o in opciones.vendedores" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">¿Autorizadas?</label>
          <select v-model="form.autorizado" :class="fieldBase">
            <option value="">Indistinto</option>
            <option value="1">Sí</option>
            <option value="0">No</option>
          </select>
        </div>
      </div>
      <div class="card-footer"><button type="submit" class="btn btn-primary">Buscar</button></div>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">File</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Pax</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Cliente</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Total</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="f in filas" :key="f.reserva_id" class="hover:bg-gray-50">
              <td class="px-3 py-2 font-semibold">{{ f.codigo }}</td>
              <td class="px-3 py-2">{{ f.fecha }}</td>
              <td class="px-3 py-2">{{ f.titular }} x {{ f.pax }}</td>
              <td class="px-3 py-2">{{ f.cliente }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ f.moneda }} {{ fmt.format(f.total) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <span v-if="f.autorizado" class="badge badge-success mr-2">Autorizada</span>
                <Link :href="f.link" class="btn btn-sm btn-success">{{ f.autorizado ? 'Ver' : 'Autorizar' }}</Link>
              </td>
            </tr>
            <tr v-if="filas.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500">No hay files para mostrar.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
