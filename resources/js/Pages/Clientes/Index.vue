<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // Paginador de Laravel: { data, links, from, to, total, ... }
  clientes: { type: Object, required: true },
  filtros: { type: Object, default: () => ({ search: '', estado: '', sort: 'cliente_nombre', dir: 'asc' }) },
})

const form = reactive({
  search: props.filtros.search ?? '',
  estado: props.filtros.estado ?? '',
})

let debounce = null
watch(
  form,
  () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => {
      router.get('/app/clientes', { search: form.search || undefined, estado: form.estado || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      })
    }, 300)
  },
)

function limpiar() {
  form.search = ''
  form.estado = ''
}
</script>

<template>
  <div>
    <!-- Encabezado -->
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
        <p class="text-gray-500">{{ clientes.total }} cliente{{ clientes.total === 1 ? '' : 's' }} en total</p>
      </div>
      <Link href="/app/clientes/create" class="btn btn-primary">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Cliente
      </Link>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
      <div class="card-body flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="form.search"
            type="text"
            placeholder="Buscar por nombre, razón social o CUIT…"
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <select
          v-model="form.estado"
          class="py-2 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">Todos los estados</option>
          <option value="S">Habilitados</option>
          <option value="N">Deshabilitados</option>
        </select>
        <button
          v-if="form.search || form.estado"
          type="button"
          class="btn btn-secondary"
          @click="limpiar"
        >
          Limpiar
        </button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">CUIT</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contacto</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ciudad</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="c in clientes.data" :key="c.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">{{ c.nombre || '—' }}</div>
                <div v-if="c.razonSocial && c.razonSocial !== c.nombre" class="text-sm text-gray-500">{{ c.razonSocial }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ c.cuit || '—' }}</td>
              <td class="px-4 py-3 text-sm">
                <div v-if="c.email" class="text-gray-700">{{ c.email }}</div>
                <div v-if="c.telefono" class="text-gray-500">{{ c.telefono }}</div>
                <span v-if="!c.email && !c.telefono" class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ c.ciudad || '—' }}</td>
              <td class="px-4 py-3">
                <span class="badge" :class="c.habilitado ? 'badge-success' : 'badge-gray'">
                  {{ c.habilitado ? 'Habilitado' : 'Deshabilitado' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="`/app/clientes/${c.id}`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                  Ver
                </Link>
              </td>
            </tr>

            <!-- Estado vacío -->
            <tr v-if="clientes.data.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                No se encontraron clientes.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="clientes.total > 0" class="card-footer flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
          Mostrando <span class="font-medium">{{ clientes.from }}</span>–<span class="font-medium">{{ clientes.to }}</span>
          de <span class="font-medium">{{ clientes.total }}</span>
        </p>
        <div class="flex flex-wrap gap-1">
          <component
            :is="link.url ? 'Link' : 'span'"
            v-for="(link, i) in clientes.links"
            :key="i"
            :href="link.url || undefined"
            preserve-scroll
            class="px-3 py-1.5 text-sm rounded-md border"
            :class="[
              link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300',
              link.url ? 'hover:bg-gray-50' : 'opacity-50 cursor-default',
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </div>
</template>
