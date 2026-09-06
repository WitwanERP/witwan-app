<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RangoFechas from '@/Components/RangoFechas.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  filtros: { type: Object, required: true },
  opciones: { type: Object, required: true },
  filas: { type: Array, default: () => [] },
})

const base = `/app/operaciones/guardia/${props.area}`
const form = reactive({
  rango: { desde: props.filtros.from, hasta: props.filtros.to },
  tipo: [...(props.filtros.tipo || [])],
  tiporeserva: [...(props.filtros.tiporeserva || [])],
  guia: props.filtros.guia || '',
  cliente: props.filtros.cliente || '',
  proveedor: props.filtros.proveedor || '',
  fk_cadenacliente_id: props.filtros.fk_cadenacliente_id || '',
  titular: props.filtros.titular || '',
  codigo: props.filtros.codigo || '',
  resumida: props.filtros.resumida || '0',
})
const fieldBase =
  'w-full rounded-lg border border-gray-300 bg-gray-50 py-2 px-3 text-sm text-gray-800 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20'

function buscar() {
  const p = { from: form.rango.desde, to: form.rango.hasta, resumida: form.resumida }
  for (const k of ['guia', 'cliente', 'proveedor', 'fk_cadenacliente_id', 'titular', 'codigo']) if (form[k] !== '') p[k] = form[k]
  if (form.tipo.length) p.tipo = form.tipo
  if (form.tiporeserva.length) p.tiporeserva = form.tiporeserva
  router.get(base, p, { preserveState: true, preserveScroll: true })
}
function limpiar() {
  router.get(base)
}

// Selección de files para la planilla.
const seleccion = ref(new Set())
const todos = computed({
  get: () => props.filas.length > 0 && props.filas.every((f) => seleccion.value.has(f.reserva_id)),
  set: (v) => {
    seleccion.value = new Set(v ? props.filas.map((f) => f.reserva_id) : [])
  },
})
function toggle(id) {
  const s = new Set(seleccion.value)
  s.has(id) ? s.delete(id) : s.add(id)
  seleccion.value = s
}
function generar(exportar) {
  if (seleccion.value.size === 0) return
  const datos = { ids: [...seleccion.value], vini: form.rango.desde, vfin: form.rango.hasta, resumida: form.resumida }
  if (exportar) {
    // Descarga: formulario POST clásico (Inertia no maneja archivos).
    const f = document.createElement('form')
    f.method = 'POST'
    f.action = `${base}/reporte`
    f.target = '_blank'
    const add = (n, v) => {
      const i = document.createElement('input')
      i.type = 'hidden'
      i.name = n
      i.value = v
      f.appendChild(i)
    }
    add('_token', document.querySelector('meta[name="csrf-token"]')?.content || '')
    datos.ids.forEach((id) => add('ids[]', id))
    add('vini', datos.vini)
    add('vfin', datos.vfin)
    add('resumida', datos.resumida)
    add('export', '1')
    document.body.appendChild(f)
    f.submit()
    f.remove()
    return
  }
  router.post(`${base}/reporte`, datos)
}
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #66cc00">Operaciones</span>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Planilla de guardia <span class="text-gray-500 font-normal">({{ area }})</span></span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Planilla de guardia</h1>
        <p class="text-gray-500">{{ filas.length }} file{{ filas.length === 1 ? '' : 's' }} con servicios entre {{ filtros.from }} y {{ filtros.to }}</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-primary" :disabled="seleccion.size === 0" @click="generar(false)">Generar planilla ({{ seleccion.size }})</button>
        <button type="button" class="btn btn-secondary" :disabled="seleccion.size === 0" @click="generar(true)">Exportar CSV</button>
      </div>
    </div>

    <form class="card mb-4" @submit.prevent="buscar">
      <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <button type="button" class="text-sm font-medium text-gray-500 hover:text-red-600" @click="limpiar">Limpiar</button>
      </div>
      <div class="card-body grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sm:col-span-2"><RangoFechas v-model="form.rango" label="Fechas de servicio" /></div>
        <div>
          <label class="form-label">Tipo de producto</label>
          <select v-model="form.tipo" multiple size="4" :class="fieldBase">
            <option v-for="o in opciones.tipos" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Tipo de file</label>
          <select v-model="form.tiporeserva" multiple size="4" :class="fieldBase">
            <option v-for="o in opciones.tiposReserva" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Guía</label>
          <select v-model="form.guia" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.guias" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Cliente</label>
          <select v-model="form.cliente" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.clientes" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Proveedor</label>
          <select v-model="form.proveedor" :class="fieldBase"><option value="">Todos</option><option v-for="o in opciones.proveedores" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Cadena de agencias</label>
          <select v-model="form.fk_cadenacliente_id" :class="fieldBase"><option value="">Todas</option><option v-for="o in opciones.cadenas" :key="o.value" :value="o.value">{{ o.label }}</option></select>
        </div>
        <div>
          <label class="form-label">Titular / pasajero</label>
          <input v-model="form.titular" type="text" :class="fieldBase" />
        </div>
        <div>
          <label class="form-label">Código</label>
          <input v-model="form.codigo" type="text" :class="fieldBase" />
        </div>
        <div>
          <label class="form-label">Vista resumida</label>
          <div class="flex gap-4 text-sm h-9 items-center">
            <label class="flex items-center gap-1"><input type="radio" value="1" v-model="form.resumida" /> Sí</label>
            <label class="flex items-center gap-1"><input type="radio" value="0" v-model="form.resumida" /> No</label>
          </div>
        </div>
      </div>
      <div class="card-footer"><button type="submit" class="btn btn-primary">Buscar</button></div>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2"><input type="checkbox" v-model="todos" /></th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">File</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Pax</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Guía</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Cliente</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Proveedor</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Tel. / Emergencia</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Observaciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="f in filas" :key="f.reserva_id" class="hover:bg-gray-50 align-top">
              <td class="px-3 py-2"><input type="checkbox" :checked="seleccion.has(f.reserva_id)" @change="toggle(f.reserva_id)" /></td>
              <td class="px-3 py-2 font-semibold"><a :href="`/reserva/editar/${f.reserva_id}`" target="_blank" class="text-blue-700 hover:underline">{{ f.codigo }}</a></td>
              <td class="px-3 py-2 whitespace-nowrap">{{ f.fecha }}</td>
              <td class="px-3 py-2">{{ f.titular }} x {{ f.pax }}</td>
              <td class="px-3 py-2">{{ f.guia || '—' }}</td>
              <td class="px-3 py-2">{{ f.cliente }}<div v-if="f.cadena" class="text-xs text-gray-400">{{ f.cadena }}</div></td>
              <td class="px-3 py-2">{{ f.proveedor }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ f.telefono }}<br /><span class="text-red-700">{{ f.emergencia }}</span></td>
              <td class="px-3 py-2 text-xs text-gray-600 whitespace-pre-line max-w-xs">{{ f.observaciones }}</td>
            </tr>
            <tr v-if="filas.length === 0">
              <td colspan="9" class="px-4 py-12 text-center text-gray-500">No hay files en el rango.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
