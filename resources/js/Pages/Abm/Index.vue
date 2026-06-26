<script setup>
import { reactive, watch, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  config: { type: Object, required: true },
  registros: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
})

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

const form = reactive({})
for (const c of props.config.filtrosLike) form[c] = props.filtros[c] ?? ''

const hayFiltros = computed(() => Object.values(form).some((v) => v !== '' && v !== null))

let debounce = null
watch(form, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    const params = {}
    for (const [k, v] of Object.entries(form)) if (v !== '' && v !== null) params[k] = v
    router.get(props.config.baseUrl, params, { preserveState: true, preserveScroll: true, replace: true })
  }, 350)
})

function limpiar() {
  for (const k of Object.keys(form)) form[k] = ''
}

function mostrar(valor) {
  if (valor === null || valor === undefined || valor === '') return '—'
  return valor
}

// Label de un filtro: usa el de la columna del listado; si no, humaniza el campo.
function labelFiltro(campo) {
  const col = props.config.columnas.find((c) => c.campo === campo)
  if (col) return col.label
  return campo
    .replace(/^[a-z]+_/, '')
    .replace(/_/g, ' ')
    .replace(/^\w/, (c) => c.toUpperCase())
}

function eliminar(id) {
  if (!window.confirm(`¿Eliminar ${props.config.singular} #${id}? Esta acción no se puede deshacer.`)) return
  router.delete(`${props.config.baseUrl}/${id}`, { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ config.titulo }}</h1>
        <p class="text-gray-500">{{ registros.total }} registro{{ registros.total === 1 ? '' : 's' }}</p>
      </div>
      <Link :href="`${config.baseUrl}/create`" class="btn btn-primary">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo {{ config.singular }}
      </Link>
    </div>

    <!-- Filtros -->
    <div v-if="config.filtrosLike.length" class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button v-if="hayFiltros" type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="campo in config.filtrosLike" :key="campo">
            <label class="form-label">{{ labelFiltro(campo) }}</label>
            <input v-model="form[campo]" type="text" :class="fieldBase" :placeholder="`Buscar por ${labelFiltro(campo).toLowerCase()}…`" />
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
              <th v-for="col in config.columnas" :key="col.campo" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ col.label }}</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="r in registros.data" :key="r[config.pk]" class="hover:bg-gray-50">
              <td v-for="col in config.columnas" :key="col.campo" class="px-4 py-3 text-sm text-gray-700">{{ mostrar(r[col.campo]) }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <Link :href="`${config.baseUrl}/${r[config.pk]}/edit`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Editar</Link>
                <button type="button" @click="eliminar(r[config.pk])" class="ml-3 text-red-600 hover:text-red-800 text-sm font-medium">Eliminar</button>
              </td>
            </tr>
            <tr v-if="registros.data.length === 0">
              <td :colspan="config.columnas.length + 1" class="px-4 py-12 text-center text-gray-500">No se encontraron registros.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="registros.total > 0" class="card-footer flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
          Mostrando <span class="font-medium">{{ registros.from }}</span>–<span class="font-medium">{{ registros.to }}</span>
          de <span class="font-medium">{{ registros.total }}</span>
        </p>
        <div class="flex flex-wrap gap-1">
          <component
            :is="link.url ? 'Link' : 'span'"
            v-for="(link, i) in registros.links"
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
