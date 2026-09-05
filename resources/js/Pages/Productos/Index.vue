<script setup>
import { computed, reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useEnvio } from '@/lib/envio'

defineOptions({ layout: AppLayout })

/**
 * Listado de productos de un tipo y sistema. Reemplaza a
 * /productos/{tipo}/lista/{sistema} del CI (los 18 controllers comparten esta
 * pantalla: lo que cambia viene en `config`).
 */
const props = defineProps({
  config: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
  registros: { type: Object, required: true },
  opciones: { type: Object, default: () => ({}) },
})

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

const form = reactive({
  producto_nombre: props.filtros.producto_nombre ?? '',
  fk_proveedor_id: props.filtros.fk_proveedor_id ?? '',
  fk_ciudad_id: props.filtros.fk_ciudad_id ?? '',
  habilitar: props.filtros.habilitar ?? '',
  aparece_tarifario: props.filtros.aparece_tarifario ?? '',
  producto_id: props.filtros.producto_id ?? '',
})

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

const { enviando, enviar } = useEnvio()

function eliminar(r) {
  if (!window.confirm(`¿Dar de baja "${r.producto_nombre}"? Deja de aparecer en tarifarios y reservas; los datos se conservan.`)) return
  enviar((op) => router.delete(`${props.config.baseUrl}/${r.producto_id}`, op), { preserveScroll: true })
}
</script>

<template>
  <div>
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link><span>/</span>
      <span class="capitalize">{{ config.sistema }}</span><span>/</span>
      <span class="font-semibold text-gray-900">{{ config.titulo }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ config.titulo }} <span class="text-base font-normal capitalize text-gray-400">· {{ config.sistema }}</span></h1>
        <p class="text-gray-500">{{ registros.total }} producto{{ registros.total === 1 ? '' : 's' }}</p>
      </div>
      <Link v-if="config.permisos.alta" :href="`${config.baseUrl}/create`" class="btn btn-primary">Nuevo</Link>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button v-if="hayFiltros" type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2"><label class="form-label">Nombre</label><input v-model="form.producto_nombre" type="text" :class="fieldBase" placeholder="Buscar por nombre…" /></div>
        <div><label class="form-label">Proveedor</label><select v-model="form.fk_proveedor_id" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
        <div><label class="form-label">Ciudad</label><select v-model="form.fk_ciudad_id" :class="fieldBase"><option value="">Todas</option><option v-for="o in opciones.ciudades" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
        <div><label class="form-label">Habilitado</label><select v-model="form.habilitar" :class="fieldBase"><option value="">Todos</option><option value="1">Sí</option><option value="0">No</option></select></div>
        <div><label class="form-label">En tarifario</label><select v-model="form.aparece_tarifario" :class="fieldBase"><option value="">Todos</option><option value="1">Sí</option><option value="0">No</option></select></div>
      </div>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">ID</th>
              <th class="px-4 py-3 text-left">Nombre</th>
              <th class="px-4 py-3 text-left">Código</th>
              <th class="px-4 py-3 text-left">Proveedor</th>
              <th class="px-4 py-3 text-left">Ciudad</th>
              <th class="px-4 py-3 text-center">Hab.</th>
              <th class="px-4 py-3 text-center">Tarif.</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="r in registros.data" :key="r.producto_id" class="hover:bg-gray-50">
              <td class="px-4 py-2 tabular-nums text-gray-500">{{ r.producto_id }}</td>
              <td class="px-4 py-2"><Link :href="`${config.baseUrl}/${r.producto_id}/edit`" class="font-medium text-gray-900 hover:text-blue-700">{{ r.producto_nombre }}</Link></td>
              <td class="px-4 py-2 text-gray-500">{{ r.producto_codigo || '—' }}</td>
              <td class="px-4 py-2">{{ r.proveedor_nombre || '—' }}</td>
              <td class="px-4 py-2">{{ r.ciudad_nombre || '—' }}</td>
              <td class="px-4 py-2 text-center"><span class="inline-block h-2.5 w-2.5 rounded-full" :class="Number(r.habilitar) ? 'bg-green-500' : 'bg-gray-300'" :title="Number(r.habilitar) ? 'Habilitado' : 'Deshabilitado'"></span></td>
              <td class="px-4 py-2 text-center"><span class="inline-block h-2.5 w-2.5 rounded-full" :class="Number(r.aparece_tarifario) ? 'bg-green-500' : 'bg-gray-300'"></span></td>
              <td class="whitespace-nowrap px-4 py-2 text-right">
                <Link :href="`/app/productos/${r.producto_id}/vigencias`" class="text-sm font-medium text-blue-600 hover:text-blue-800">Tarifas</Link>
                <Link :href="`${config.baseUrl}/${r.producto_id}/edit`" class="ml-3 text-sm font-medium text-blue-600 hover:text-blue-800">Editar</Link>
                <button v-if="config.permisos.borrado" type="button" class="ml-3 text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50" :disabled="enviando" @click="eliminar(r)">Baja</button>
              </td>
            </tr>
            <tr v-if="registros.data.length === 0"><td colspan="8" class="px-4 py-12 text-center text-gray-500">No se encontraron productos.</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="registros.total > 0" class="card-footer flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Mostrando <b>{{ registros.from }}</b>–<b>{{ registros.to }}</b> de <b>{{ registros.total }}</b></p>
        <div class="flex flex-wrap gap-1">
          <component :is="link.url ? 'Link' : 'span'" v-for="(link, i) in registros.links" :key="i" :href="link.url || undefined" preserve-scroll class="rounded-md border px-3 py-1.5 text-sm" :class="[link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-600', link.url ? 'hover:bg-gray-50' : 'cursor-default opacity-50']" v-html="link.label" />
        </div>
      </div>
    </div>
  </div>
</template>
