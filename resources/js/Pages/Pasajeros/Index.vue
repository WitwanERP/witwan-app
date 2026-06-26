<script setup>
import { reactive, watch, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  pasajeros: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
})

const form = reactive({
  pasajero_apellido: props.filtros.pasajero_apellido ?? '',
  pasajero_nombre: props.filtros.pasajero_nombre ?? '',
  pasajero_email: props.filtros.pasajero_email ?? '',
  pasajero_id: props.filtros.pasajero_id ?? '',
})

const hayFiltros = computed(() => Object.values(form).some((v) => v !== '' && v !== null))

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

let debounce = null
watch(form, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    const params = {}
    for (const [k, v] of Object.entries(form)) {
      if (v !== '' && v !== null) params[k] = v
    }
    router.get('/app/pasajeros', params, { preserveState: true, preserveScroll: true, replace: true })
  }, 350)
})

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Pasajeros</h1>
        <p class="text-gray-500">{{ pasajeros.total }} pasajero{{ pasajeros.total === 1 ? '' : 's' }} en total</p>
      </div>
      <Link href="/app/pasajeros/create" class="btn btn-primary">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Pasajero
      </Link>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button v-if="hayFiltros" type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="form-label">Apellido</label>
            <input v-model="form.pasajero_apellido" type="text" :class="fieldBase" placeholder="Apellido…" />
          </div>
          <div>
            <label class="form-label">Nombre</label>
            <input v-model="form.pasajero_nombre" type="text" :class="fieldBase" placeholder="Nombre…" />
          </div>
          <div>
            <label class="form-label">Email</label>
            <input v-model="form.pasajero_email" type="text" :class="fieldBase" placeholder="Email…" />
          </div>
          <div>
            <label class="form-label">ID</label>
            <input v-model="form.pasajero_id" type="text" inputmode="numeric" :class="fieldBase" placeholder="ID…" />
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Apellido</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="p in pasajeros.data" :key="p.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-500">{{ p.id }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ p.apellido || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ p.nombre || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ p.email || '—' }}</td>
              <td class="px-4 py-3 text-sm">
                <span v-if="p.esCliente" class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Sí</span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="`/app/pasajeros/${p.id}/edit`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Editar</Link>
              </td>
            </tr>
            <tr v-if="pasajeros.data.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-500">No se encontraron pasajeros.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pasajeros.total > 0" class="card-footer flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
          Mostrando <span class="font-medium">{{ pasajeros.from }}</span>–<span class="font-medium">{{ pasajeros.to }}</span>
          de <span class="font-medium">{{ pasajeros.total }}</span>
        </p>
        <div class="flex flex-wrap gap-1">
          <component
            :is="link.url ? 'Link' : 'span'"
            v-for="(link, i) in pasajeros.links"
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
