<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

/**
 * Vista temporal de desarrollo: índice de todas las pantallas de /app tal como
 * están registradas en el router, agrupadas por módulo, con filtro por texto.
 */
const props = defineProps({
  grupos: { type: Object, required: true },
  total: { type: Number, default: 0 },
  rutasMigradas: { type: Object, default: () => ({}) },
})

const filtro = ref('')
const migradas = computed(() => new Set(Object.values(props.rutasMigradas)))
const grupos = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const out = {}
  for (const [g, items] of Object.entries(props.grupos)) {
    const filtrados = q ? items.filter((it) => it.uri.toLowerCase().includes(q) || it.nombre.toLowerCase().includes(q) || it.controlador.toLowerCase().includes(q) || g.includes(q)) : items
    if (filtrados.length) out[g] = filtrados
  }
  return out
})
const cantidad = (items) => items.reduce((a, it) => a + Math.max(1, it.links.length), 0)
</script>

<template>
  <div>
    <div class="mb-4 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Secciones de /app <span class="text-gray-400 font-normal text-lg">({{ total }} rutas)</span></h1>
        <p class="text-gray-500 text-sm">Vista temporal de desarrollo: todo lo que responde a GET bajo /app, leído del router. <span class="badge badge-info">menú</span> = ya abre desde la botonera del CI (config/menu.php).</p>
      </div>
      <input v-model="filtro" type="search" placeholder="Filtrar por url, nombre o controller…" class="w-72 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
      <section v-for="(items, g) in grupos" :key="g" class="card">
        <div class="card-header"><h2 class="card-title uppercase tracking-wide">{{ g }} <span class="text-gray-400 font-normal normal-case">· {{ cantidad(items) }} pantallas</span></h2></div>
        <ul class="card-body divide-y divide-gray-100 !p-0">
          <li v-for="it in items" :key="it.uri" class="px-4 py-2 text-sm">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <template v-if="it.links.length === 1">
                  <Link :href="it.links[0].href" class="text-blue-700 hover:underline font-medium break-all">{{ it.uri }}</Link>
                </template>
                <template v-else>
                  <span class="font-medium text-gray-800 break-all">{{ it.uri }}</span>
                  <div v-if="it.links.length" class="flex flex-wrap gap-1 mt-1">
                    <Link v-for="l in it.links" :key="l.href" :href="l.href" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700 hover:bg-blue-100">{{ l.label }}</Link>
                  </div>
                  <div v-else class="text-xs text-gray-400 mt-0.5">requiere un id: abrir desde su listado</div>
                </template>
                <div class="text-xs text-gray-400 truncate">{{ it.controlador }}<span v-if="it.nombre"> · {{ it.nombre }}</span></div>
              </div>
              <span v-if="migradas.has(it.uri) || it.links.some((l) => migradas.has(l.href))" class="badge badge-info shrink-0">menú</span>
            </div>
          </li>
        </ul>
      </section>
    </div>
    <p v-if="!Object.keys(grupos).length" class="text-sm text-gray-500">Nada coincide con el filtro.</p>
  </div>
</template>
