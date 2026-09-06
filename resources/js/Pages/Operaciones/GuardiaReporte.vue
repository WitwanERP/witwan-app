<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  area: { type: String, required: true },
  rango: { type: Object, required: true },
  parametros: { type: Object, required: true },
  // [{ reserva_id, codigo, titular, cliente, telefono_cliente, vendedor, guia, paxs, servicios: [...] }]
  planilla: { type: Array, default: () => [] },
})

const base = `/app/operaciones/guardia/${props.area}`

function exportar() {
  const f = document.createElement('form')
  f.method = 'POST'
  f.action = `${base}/reporte`
  const add = (n, v) => {
    const i = document.createElement('input')
    i.type = 'hidden'
    i.name = n
    i.value = v
    f.appendChild(i)
  }
  add('_token', document.querySelector('meta[name="csrf-token"]')?.content || '')
  props.parametros.ids.forEach((id) => add('ids[]', id))
  add('vini', props.parametros.vini)
  add('vfin', props.parametros.vfin)
  add('resumida', props.parametros.resumida)
  add('export', '1')
  document.body.appendChild(f)
  f.submit()
  f.remove()
}
</script>

<template>
  <div>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3 no-print">
      <Link href="/app" class="hover:text-gray-700">Inicio</Link>
      <span>/</span>
      <span class="font-medium" style="color: #66cc00">Operaciones</span>
      <span>/</span>
      <Link :href="base" class="hover:text-gray-700">Planilla de guardia</Link>
      <span>/</span>
      <span class="text-gray-900 font-semibold">Planilla</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Planilla de guardia</h1>
        <p class="text-gray-500">{{ rango.desde }} al {{ rango.hasta }} · {{ planilla.length }} file{{ planilla.length === 1 ? '' : 's' }}<span v-if="parametros.resumida == 1"> · vista resumida</span></p>
      </div>
      <div class="flex gap-2 no-print">
        <button type="button" class="btn btn-secondary" @click="exportar">Exportar CSV</button>
        <button type="button" class="btn btn-secondary" @click="window.print()">Imprimir</button>
        <Link :href="base" class="btn btn-primary">Volver</Link>
      </div>
    </div>

    <section v-for="r in planilla" :key="r.reserva_id" class="card mb-4">
      <div class="card-header flex-wrap">
        <div>
          <a :href="`/reserva/editar/${r.reserva_id}`" target="_blank" class="font-bold text-blue-700 hover:underline">{{ r.codigo }}</a>
          <span class="mx-1">-</span><b>{{ r.titular }} x {{ r.paxs }}</b>
          <span class="mx-1">-</span>{{ r.cliente }} <span v-if="r.telefono_cliente" class="text-gray-500">({{ r.telefono_cliente }})</span>
          <div class="text-xs text-gray-500 italic">Vendedor: {{ r.vendedor }} <span v-if="r.guia">(GUÍA: {{ r.guia }})</span></div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Fecha in/out</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Servicio (proveedor - ok prov.)</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Teléfono proveedor (normal / emergencia)</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <template v-for="s in r.servicios" :key="s.servicio_id">
              <tr :class="s.en_rango ? 'bg-blue-50' : ''">
                <td class="px-3 py-2 whitespace-nowrap">{{ s.ini }}<template v-if="s.fin && s.fin !== s.ini"><br />{{ s.fin }}</template></td>
                <td class="px-3 py-2">
                  {{ s.nombre }} ({{ s.proveedor }} {{ s.nro_confirmacion }}) - {{ s.paxs }} pax/s
                  <div v-if="s.comentarios" class="text-xs italic text-gray-600 whitespace-pre-line">{{ s.comentarios }}</div>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">{{ s.telefono }} / {{ s.emergencia }}</td>
                <td class="px-3 py-2">{{ s.status }}</td>
              </tr>
              <tr v-if="s.vuelo" :class="s.en_rango ? 'bg-blue-50' : ''">
                <td colspan="4" class="px-3 pb-2 text-xs italic text-gray-700">
                  <span v-if="s.vuelo.pickup">Pickup: {{ s.vuelo.pickup }} · </span>
                  <span v-if="s.vuelo.pickup_nvuelo">Vuelo: {{ s.vuelo.pickup_nvuelo }} · </span>
                  <span v-if="s.vuelo.dropoff">Dropoff: {{ s.vuelo.dropoff }} · </span>
                  <span v-if="s.vuelo.dropoff_nvuelo">Vuelo: {{ s.vuelo.dropoff_nvuelo }}</span>
                </td>
              </tr>
            </template>
            <tr v-if="r.servicios.length === 0">
              <td colspan="4" class="px-3 py-4 text-center text-gray-400">Sin servicios en el rango.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="planilla.length === 0" class="card"><div class="card-body text-center text-gray-500 py-12">Ninguno de los files seleccionados está confirmado o cerrado.</div></div>
  </div>
</template>
