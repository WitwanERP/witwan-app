<script setup>
import { computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RangoFechas from '@/Components/RangoFechas.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // { titulo, area, grupo, baseUrl, ayuda, filtros: [{campo,label,tipo,opciones?}], columnas: [{campo,label,tipo?,total?}], agruparPor, consultado }
  config: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
  // [{ clave, filas: [...], totales: {campo: n} }]
  grupos: { type: Array, default: () => [] },
})

const COLOR_AREA = { Configuración: '#FF9900', Administración: '#c9a300', Receptivo: '#66CC00', Operador: '#FF33FF', Minorista: '#00B5B5', Operaciones: '#66CC00' }
const colorArea = computed(() => COLOR_AREA[props.config.area] || '#c9a300')

const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

// Estado del formulario de filtros a partir de lo que devolvió el server.
const form = reactive({})
for (const f of props.config.filtros) {
  if (f.tipo === 'rango') form[f.campo] = { desde: props.filtros[f.campo] || '', hasta: props.filtros[`${f.campo}_to`] || '' }
  else if (f.tipo === 'multi') form[f.campo] = Array.isArray(props.filtros[f.campo]) ? props.filtros[f.campo] : []
  else form[f.campo] = props.filtros[f.campo] ?? f.default ?? ''
}

function params() {
  const p = { buscar: 1 }
  for (const f of props.config.filtros) {
    const v = form[f.campo]
    if (f.tipo === 'rango') {
      if (v.desde) p[f.campo] = v.desde
      if (v.hasta) p[`${f.campo}_to`] = v.hasta
    } else if (Array.isArray(v)) {
      if (v.length) p[f.campo] = v
    } else if (v !== '' && v !== null && v !== undefined) {
      p[f.campo] = v
    }
  }
  return p
}

function buscar() {
  router.get(props.config.baseUrl, params(), { preserveState: true, preserveScroll: true })
}
function limpiar() {
  router.get(props.config.baseUrl, {}, { preserveState: false })
}
const urlExport = computed(() => {
  const q = new URLSearchParams()
  for (const [k, v] of Object.entries(params())) {
    if (Array.isArray(v)) v.forEach((x) => q.append(`${k}[]`, x))
    else q.set(k, v)
  }
  return `${props.config.baseUrl}/export?${q.toString()}`
})

const totalFilas = computed(() => props.grupos.reduce((n, g) => n + g.filas.length, 0))
const hayTotales = computed(() => props.config.columnas.some((c) => c.total))

const fmtNum = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function mostrar(col, valor) {
  if (valor === null || valor === undefined || valor === '') return '—'
  if (col.tipo === 'num') return fmtNum.format(Number(valor) || 0)
  return valor
}
function esLink(col, fila) {
  return col.link && fila[col.link]
}
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" :style="{ color: colorArea }">{{ config.area }}</span>
      <span v-if="config.grupo">/</span>
      <span v-if="config.grupo">{{ config.grupo }}</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">{{ config.titulo }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ config.titulo }}</h1>
        <p class="text-gray-500">
          <span v-if="config.consultado">{{ totalFilas }} fila{{ totalFilas === 1 ? '' : 's' }}</span>
          <span v-else>Complete los filtros y presione Buscar.</span>
        </p>
      </div>
      <div class="flex gap-2">
        <a v-for="a in config.acciones || []" :key="a.label" :href="a.href" :target="a.target || '_self'" class="btn btn-primary">{{ a.label }}</a>
        <a v-if="config.consultado && totalFilas > 0" :href="urlExport" class="btn btn-secondary">Exportar CSV</a>
      </div>
    </div>

    <div v-if="config.limite && totalFilas >= config.limite" class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 mb-4">
      Se muestran los primeros {{ config.limite }} registros. Ajuste los filtros para acotar la búsqueda.
    </div>

    <div v-if="config.ayuda" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-4">{{ config.ayuda }}</div>

    <form class="card mb-4" @submit.prevent="buscar">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <template v-for="f in config.filtros" :key="f.campo">
            <div v-if="f.tipo === 'rango'" class="sm:col-span-2">
              <RangoFechas v-model="form[f.campo]" :label="f.label" />
            </div>
            <div v-else-if="f.tipo === 'select'">
              <label class="form-label">{{ f.label }}</label>
              <select v-model="form[f.campo]" :class="fieldBase">
                <option value="">Todos</option>
                <option v-for="o in f.opciones || []" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div v-else-if="f.tipo === 'multi'">
              <label class="form-label">{{ f.label }}</label>
              <select v-model="form[f.campo]" multiple :class="fieldBase" size="4">
                <option v-for="o in f.opciones || []" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div v-else-if="f.tipo === 'bool'">
              <label class="form-label">{{ f.label }}</label>
              <select v-model="form[f.campo]" :class="fieldBase">
                <option value="">Indistinto</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
            </div>
            <div v-else-if="f.tipo === 'date'">
              <label class="form-label">{{ f.label }}</label>
              <input v-model="form[f.campo]" type="date" :class="fieldBase" />
            </div>
            <div v-else>
              <label class="form-label">{{ f.label }}</label>
              <input v-model="form[f.campo]" type="text" :class="fieldBase" :placeholder="f.placeholder || ''" />
            </div>
          </template>
        </div>
      </div>
      <div class="card-footer flex items-center gap-2">
        <button type="submit" class="btn btn-primary">Buscar</button>
      </div>
    </form>

    <div v-if="config.consultado && grupos.length === 0" class="card"><div class="card-body text-center text-gray-500 py-12">No se encontraron registros.</div></div>

    <div v-for="g in grupos" :key="g.clave ?? 'unico'" class="card mb-4">
      <div v-if="g.clave !== null" class="card-header"><h3 class="card-title">{{ g.clave || '(sin valor)' }}</h3><span class="text-sm text-gray-500">{{ g.filas.length }} fila{{ g.filas.length === 1 ? '' : 's' }}</span></div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th v-for="col in config.columnas" :key="col.campo" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap" :class="col.tipo === 'num' ? 'text-right' : 'text-left'">{{ col.label }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="(fila, i) in g.filas" :key="i" class="hover:bg-gray-50">
              <td v-for="col in config.columnas" :key="col.campo" class="px-3 py-1.5 text-gray-700 align-top" :class="[col.tipo === 'num' ? 'text-right whitespace-nowrap tabular-nums' : '', col.tipo === 'pre' ? 'whitespace-pre-line' : '']">
                <template v-if="col.tipo === 'acciones'">
                  <span class="flex flex-wrap gap-x-2 gap-y-0.5 whitespace-nowrap">
                    <a v-for="a in fila[col.campo] || []" :key="a.label" :href="a.href" :target="a.target || '_blank'" class="text-xs font-medium hover:underline" :class="a.peligro ? 'text-red-600' : 'text-blue-600'">{{ a.label }}</a>
                  </span>
                </template>
                <a v-else-if="esLink(col, fila)" :href="fila[col.link]" class="text-blue-600 hover:underline" target="_blank">{{ mostrar(col, fila[col.campo]) }}</a>
                <template v-else>{{ mostrar(col, fila[col.campo]) }}</template>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="hayTotales" class="bg-gray-50 font-semibold">
            <tr>
              <td v-for="(col, i) in config.columnas" :key="col.campo" class="px-3 py-2" :class="col.tipo === 'num' ? 'text-right tabular-nums' : ''">
                <template v-if="col.total">{{ fmtNum.format(g.totales[col.campo] || 0) }}</template>
                <template v-else-if="i === 0">TOTAL</template>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
